<?php
/**
 * Save Medical Record
 * Allows doctor to add notes, prescriptions, and lab orders
 */

session_start();

require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    $appointmentId = $data['appointment_id'] ?? null;
    $patientId = $data['patient_id'] ?? null;
    $chiefComplaint = $data['chief_complaint'] ?? '';
    $symptoms = $data['symptoms'] ?? '';
    $diagnosis = $data['diagnosis'] ?? '';
    $prescription = $data['prescription'] ?? '';
    $labTests = $data['lab_tests'] ?? '';
    $notes = $data['notes'] ?? '';
    $followUpDate = $data['follow_up_date'] ?? null;
    
    // Vital signs
    $vitalBP = $data['vital_bp'] ?? '';
    $vitalTemp = $data['vital_temp'] ?? '';
    $vitalPulse = $data['vital_pulse'] ?? '';
    $vitalWeight = $data['vital_weight'] ?? '';
    
    // Combine vital signs
    $vitalSigns = json_encode([
        'blood_pressure' => $vitalBP,
        'temperature' => $vitalTemp,
        'pulse' => $vitalPulse,
        'weight' => $vitalWeight
    ]);
    
    // Get doctor ID
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    if ($_SESSION['role'] !== 'doctor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    $userId = $_SESSION['user_id'];
    $doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->execute([':user_id' => $userId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        throw new Exception('Doctor not found');
    }
    
    $doctorId = $doctor['id'];

    // Validate appointment belongs to this doctor and patient
    if ($appointmentId) {
        $aptCheck = $db->prepare("SELECT patient_id FROM appointments WHERE id = :id AND doctor_id = :doctor_id");
        $aptCheck->execute([':id' => $appointmentId, ':doctor_id' => $doctorId]);
        $aptRow = $aptCheck->fetch(PDO::FETCH_ASSOC);

        if (!$aptRow) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found or not assigned to you']);
            exit;
        }
        // Use the patient_id from the appointment to prevent mismatch
        $patientId = $aptRow['patient_id'];
    }

    // Start transaction
    $db->beginTransaction();
    
    // Insert medical record
    $insertQuery = "INSERT INTO medical_records 
                    (patient_id, doctor_id, appointment_id, chief_complaint, symptoms, 
                     diagnosis, prescription, lab_tests, vital_signs, notes, follow_up_date, created_at) 
                    VALUES 
                    (:patient_id, :doctor_id, :appointment_id, :chief_complaint, :symptoms, 
                     :diagnosis, :prescription, :lab_tests, :vital_signs, :notes, :follow_up_date, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':appointment_id' => $appointmentId,
        ':chief_complaint' => $chiefComplaint,
        ':symptoms' => $symptoms,
        ':diagnosis' => $diagnosis,
        ':prescription' => $prescription,
        ':lab_tests' => $labTests,
        ':vital_signs' => $vitalSigns,
        ':notes' => $notes,
        ':follow_up_date' => $followUpDate
    ]);
    
    $recordId = $db->lastInsertId();
    
    // Also insert into visits table for doctor-side history
    if ($appointmentId) {
        $visitQuery = "INSERT INTO visits
                       (appointment_id, patient_id, doctor_id, chief_complaint, symptoms,
                        vital_signs, diagnosis, prescription, lab_tests_ordered, follow_up_date, notes)
                       VALUES
                       (:appointment_id, :patient_id, :doctor_id, :chief_complaint, :symptoms,
                        :vital_signs, :diagnosis, :prescription, :lab_tests_ordered, :follow_up_date, :notes)
                       ON DUPLICATE KEY UPDATE
                       chief_complaint = VALUES(chief_complaint),
                       symptoms = VALUES(symptoms),
                       vital_signs = VALUES(vital_signs),
                       diagnosis = VALUES(diagnosis),
                       prescription = VALUES(prescription),
                       lab_tests_ordered = VALUES(lab_tests_ordered),
                       follow_up_date = VALUES(follow_up_date),
                       notes = VALUES(notes),
                       updated_at = NOW()";
        $visitStmt2 = $db->prepare($visitQuery);
        $visitStmt2->execute([
            ':appointment_id' => $appointmentId,
            ':patient_id' => $patientId,
            ':doctor_id' => $doctorId,
            ':chief_complaint' => $chiefComplaint,
            ':symptoms' => $symptoms,
            ':vital_signs' => $vitalSigns,
            ':diagnosis' => $diagnosis,
            ':prescription' => $prescription,
            ':lab_tests_ordered' => $labTests,
            ':follow_up_date' => $followUpDate,
            ':notes' => $notes
        ]);
    }

    // Update appointment status to completed
    if ($appointmentId) {
        $updateQuery = "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([':id' => $appointmentId]);
    }

    // Notify patient
    if ($patientId) {
        try {
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id)
                           VALUES ((SELECT user_id FROM patients WHERE id = :patient_id), 'update', 'Medical Record Added', 'Your doctor has added a medical record for your visit. Check your Medical Records tab.', :appointment_id)";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->execute([':patient_id' => $patientId, ':appointment_id' => $appointmentId]);
        } catch (Exception $e) {
            // Don't fail the save if notification fails
        }
    }

    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Medical record saved successfully',
        'record_id' => $recordId
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving medical record: ' . $e->getMessage()
    ]);
}
