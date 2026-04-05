<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../utils/QRCodeGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

$patient_id = $_SESSION['profile_id'] ?? $data['patient_id'] ?? null;
$doctor_id = $data['doctor_id'] ?? null;
$appointment_date = $data['appointment_date'] ?? null;
$appointment_time = $data['appointment_time'] ?? null;
$reason_for_visit = sanitizeInput($data['reason_for_visit'] ?? '');
$priority = $data['priority'] ?? 'normal';

if (!$patient_id || !$doctor_id || !$appointment_date || !$appointment_time) {
    sendJSON(['success' => false, 'message' => 'Missing required fields'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate doctor availability
    $dayOfWeek = date('l', strtotime($appointment_date));
    $scheduleQuery = "SELECT * FROM doctor_schedules 
                      WHERE doctor_id = :doctor_id 
                      AND day_of_week = :day_of_week 
                      AND is_active = 1
                      AND :appointment_time BETWEEN start_time AND end_time";
    $scheduleStmt = $db->prepare($scheduleQuery);
    $scheduleStmt->execute([
        ':doctor_id' => $doctor_id,
        ':day_of_week' => $dayOfWeek,
        ':appointment_time' => $appointment_time
    ]);

    if ($scheduleStmt->rowCount() === 0) {
        sendJSON(['success' => false, 'message' => 'Doctor not available at selected time'], 400);
    }

    // Check for existing appointments at the same time
    $conflictQuery = "SELECT id FROM appointments 
                      WHERE doctor_id = :doctor_id 
                      AND appointment_date = :appointment_date 
                      AND appointment_time = :appointment_time 
                      AND status NOT IN ('cancelled', 'no_show')";
    $conflictStmt = $db->prepare($conflictQuery);
    $conflictStmt->execute([
        ':doctor_id' => $doctor_id,
        ':appointment_date' => $appointment_date,
        ':appointment_time' => $appointment_time
    ]);

    if ($conflictStmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'Time slot already booked'], 409);
    }

    // Start transaction
    $db->beginTransaction();

    // Generate appointment number
    $appointment_number = 'APT' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Insert appointment
    $insertQuery = "INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason_for_visit, priority, status, created_by) 
                    VALUES (:appointment_number, :patient_id, :doctor_id, :appointment_date, :appointment_time, :reason_for_visit, :priority, 'scheduled', :created_by)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        ':appointment_number' => $appointment_number,
        ':patient_id' => $patient_id,
        ':doctor_id' => $doctor_id,
        ':appointment_date' => $appointment_date,
        ':appointment_time' => $appointment_time,
        ':reason_for_visit' => $reason_for_visit,
        ':priority' => $priority,
        ':created_by' => getCurrentUserId()
    ]);

    $appointment_id = $db->lastInsertId();

    // Generate QR code
    $qrGenerator = new QRCodeGenerator($db);
    $qrData = $qrGenerator->generateQRCode($appointment_id);

    // Create notification for patient
    $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id) 
                   VALUES ((SELECT user_id FROM patients WHERE id = :patient_id), 'appointment', 'Appointment Booked', 'Your appointment has been scheduled successfully.', :appointment_id)";
    $notifStmt = $db->prepare($notifQuery);
    $notifStmt->execute([':patient_id' => $patient_id, ':appointment_id' => $appointment_id]);

    // Commit transaction
    $db->commit();

    // Log audit
    logAudit($db, getCurrentUserId(), 'create_appointment', 'appointments', $appointment_id, 'Appointment created');

    // Get appointment details
    $detailQuery = "SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name, d.specialization 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = :id";
    $detailStmt = $db->prepare($detailQuery);
    $detailStmt->execute([':id' => $appointment_id]);
    $appointment = $detailStmt->fetch();

    sendJSON([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment' => $appointment,
        'qr_code' => $qrData
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
