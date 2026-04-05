<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn() || getCurrentUserRole() !== 'doctor') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

$appointment_id = $data['appointment_id'] ?? null;
$chief_complaint = sanitizeInput($data['chief_complaint'] ?? '');
$symptoms = sanitizeInput($data['symptoms'] ?? '');
$vital_signs = $data['vital_signs'] ?? [];
$diagnosis = sanitizeInput($data['diagnosis'] ?? '');
$prescription = sanitizeInput($data['prescription'] ?? '');
$lab_tests_ordered = sanitizeInput($data['lab_tests_ordered'] ?? '');
$follow_up_date = $data['follow_up_date'] ?? null;
$notes = sanitizeInput($data['notes'] ?? '');

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get appointment details
    $appointmentQuery = "SELECT * FROM appointments WHERE id = :id AND doctor_id = :doctor_id";
    $appointmentStmt = $db->prepare($appointmentQuery);
    $appointmentStmt->execute([':id' => $appointment_id, ':doctor_id' => $_SESSION['profile_id']]);

    if ($appointmentStmt->rowCount() === 0) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    $appointment = $appointmentStmt->fetch();

    // Start transaction
    $db->beginTransaction();

    // Insert or update visit
    $visitQuery = "INSERT INTO visits (appointment_id, patient_id, doctor_id, chief_complaint, symptoms, vital_signs, diagnosis, prescription, lab_tests_ordered, follow_up_date, notes) 
                   VALUES (:appointment_id, :patient_id, :doctor_id, :chief_complaint, :symptoms, :vital_signs, :diagnosis, :prescription, :lab_tests_ordered, :follow_up_date, :notes)
                   ON DUPLICATE KEY UPDATE
                   chief_complaint = :chief_complaint,
                   symptoms = :symptoms,
                   vital_signs = :vital_signs,
                   diagnosis = :diagnosis,
                   prescription = :prescription,
                   lab_tests_ordered = :lab_tests_ordered,
                   follow_up_date = :follow_up_date,
                   notes = :notes,
                   updated_at = NOW()";
    
    $visitStmt = $db->prepare($visitQuery);
    $visitStmt->execute([
        ':appointment_id' => $appointment_id,
        ':patient_id' => $appointment['patient_id'],
        ':doctor_id' => $appointment['doctor_id'],
        ':chief_complaint' => $chief_complaint,
        ':symptoms' => $symptoms,
        ':vital_signs' => json_encode($vital_signs),
        ':diagnosis' => $diagnosis,
        ':prescription' => $prescription,
        ':lab_tests_ordered' => $lab_tests_ordered,
        ':follow_up_date' => $follow_up_date,
        ':notes' => $notes
    ]);

    // Update appointment status to completed
    $updateQuery = "UPDATE appointments SET status = 'completed', completed_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $appointment_id]);

    // Create notification for patient
    $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id) 
                   VALUES ((SELECT user_id FROM patients WHERE id = :patient_id), 'update', 'Visit Completed', 'Your visit has been completed. Check your medical records.', :appointment_id)";
    $notifStmt = $db->prepare($notifQuery);
    $notifStmt->execute([':patient_id' => $appointment['patient_id'], ':appointment_id' => $appointment_id]);

    $db->commit();

    // Log audit
    logAudit($db, getCurrentUserId(), 'create_visit', 'visits', $appointment_id, 'Medical record created');

    sendJSON([
        'success' => true,
        'message' => 'Visit record saved successfully'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
