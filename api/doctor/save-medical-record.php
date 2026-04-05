<?php
/**
 * Save Medical Record
 * Allows doctor to add notes, prescriptions, and lab orders
 */

session_start();

require_once '../../config/database.php';

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
    $userId = $_SESSION['user_id'] ?? 19;
    $doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->execute([':user_id' => $userId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        throw new Exception('Doctor not found');
    }
    
    $doctorId = $doctor['id'];
    
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
    
    // Update appointment status to completed
    if ($appointmentId) {
        $updateQuery = "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([':id' => $appointmentId]);
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Medical record saved successfully',
        'record_id' => $recordId
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving medical record: ' . $e->getMessage()
    ]);
}
