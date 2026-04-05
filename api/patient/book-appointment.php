<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../utils/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required = ['doctor_id', 'appointment_date', 'appointment_time', 'reason_for_visit'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendJSON(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];

    // Get patient ID
    $patientQuery = "SELECT id FROM patients WHERE user_id = :user_id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':user_id', $userId);
    $patientStmt->execute();
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    $patientId = $patient['id'];
    $doctorId = $data['doctor_id'];
    $appointmentDate = $data['appointment_date'];
    $appointmentTime = $data['appointment_time'];
    $reasonForVisit = sanitizeInput($data['reason_for_visit']);

    // Verify doctor exists and is active
    $doctorQuery = "SELECT id, department FROM doctors WHERE id = :doctor_id AND status = 'active'";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->bindParam(':doctor_id', $doctorId);
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor not found or not available'], 404);
    }

    // Check if time slot is available (exclude only cancelled, no_show, and completed)
    $checkQuery = "SELECT id FROM appointments
                   WHERE doctor_id = :doctor_id
                   AND appointment_date = :appointment_date
                   AND appointment_time = :appointment_time
                   AND status NOT IN ('cancelled', 'no_show', 'completed')";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([
        ':doctor_id' => $doctorId,
        ':appointment_date' => $appointmentDate,
        ':appointment_time' => $appointmentTime
    ]);

    if ($checkStmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 400);
    }

    // Generate appointment number
    $appointmentNumber = 'APT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // Start transaction
    $db->beginTransaction();

    // Insert appointment
    $insertQuery = "INSERT INTO appointments 
                    (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, 
                     reason_for_visit, status, priority, created_at) 
                    VALUES 
                    (:appointment_number, :patient_id, :doctor_id, :appointment_date, :appointment_time, 
                     :reason_for_visit, 'scheduled', 'normal', CURRENT_TIMESTAMP)";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        ':appointment_number' => $appointmentNumber,
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':appointment_date' => $appointmentDate,
        ':appointment_time' => $appointmentTime,
        ':reason_for_visit' => $reasonForVisit
    ]);

    $appointmentId = $db->lastInsertId();

    // Commit transaction
    $db->commit();

    // Log audit
    logAudit($db, $userId, 'book_appointment', 'appointments', $appointmentId, 'New appointment booked');
    
    // Get patient and doctor details first
    $detailsQuery = "SELECT 
                        p.full_name as patient_name,
                        p.email as patient_email,
                        p.contact_number as patient_phone,
                        d.full_name as doctor_name,
                        d.specialization,
                        d.department,
                        d.email as doctor_email,
                        du.id as doctor_user_id
                     FROM appointments a
                     JOIN patients p ON a.patient_id = p.id
                     JOIN doctors d ON a.doctor_id = d.id
                     LEFT JOIN users du ON d.user_id = du.id
                     WHERE a.id = :appointment_id";
    $detailsStmt = $db->prepare($detailsQuery);
    $detailsStmt->execute([
        ':appointment_id' => $appointmentId
    ]);
    $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Create notification for patient
    $notificationQuery = "INSERT INTO notifications (user_id, type, title, message, related_id, created_at) 
                          VALUES (:user_id, 'appointment', :title, :message, :related_id, CURRENT_TIMESTAMP)";
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->execute([
        ':user_id' => $userId,
        ':title' => 'Appointment Confirmed',
        ':message' => "Your appointment with Dr. {$details['doctor_name']} on " . date('M j, Y', strtotime($appointmentDate)) . " at " . date('g:i A', strtotime($appointmentTime)) . " has been confirmed.",
        ':related_id' => $appointmentId
    ]);
    
    // Create notification for doctor
    if ($details['doctor_user_id']) {
        $doctorNotificationStmt = $db->prepare($notificationQuery);
        $doctorNotificationStmt->execute([
            ':user_id' => $details['doctor_user_id'],
            ':title' => 'New Appointment',
            ':message' => "New appointment from {$details['patient_name']} on " . date('M j, Y', strtotime($appointmentDate)) . " at " . date('g:i A', strtotime($appointmentTime)) . ".",
            ':related_id' => $appointmentId
        ]);
    }

    // Send email notifications
    try {
        $emailService = new EmailService();
        $appointmentDetails = [
            'appointment_number' => $appointmentNumber,
            'doctor_name' => $details['doctor_name'],
            'patient_name' => $details['patient_name'],
            'patient_phone' => $details['patient_phone'],
            'specialization' => $details['specialization'],
            'department' => $details['department'],
            'date' => date('F j, Y', strtotime($appointmentDate)),
            'time' => date('g:i A', strtotime($appointmentTime)),
            'reason' => $reasonForVisit
        ];
        
        // Send confirmation email to patient
        $patientEmailSent = $emailService->sendAppointmentConfirmation(
            $details['patient_email'],
            $details['patient_name'],
            $appointmentDetails
        );
        
        if ($patientEmailSent) {
            error_log("Appointment confirmation email sent to patient: {$details['patient_email']}");
        }
        
        // Send notification email to doctor
        if (!empty($details['doctor_email'])) {
            $doctorEmailSent = $emailService->sendDoctorAppointmentNotification(
                $details['doctor_email'],
                $details['doctor_name'],
                $appointmentDetails
            );
            
            if ($doctorEmailSent) {
                error_log("Appointment notification email sent to doctor: {$details['doctor_email']}");
            }
        }
    } catch (Exception $emailError) {
        // Log error but don't fail the appointment booking
        error_log("Email notification error: " . $emailError->getMessage());
    }

    sendJSON([
        'success' => true,
        'message' => 'Appointment booked successfully! A confirmation email has been sent.',
        'appointment' => [
            'id' => $appointmentId,
            'appointment_number' => $appointmentNumber,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
            'doctor_name' => $details['doctor_name'],
            'specialization' => $details['specialization']
        ]
    ]);

} catch (Exception $e) {
    if (isset($db) && $db && $db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
