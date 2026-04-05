<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['appointment_id'])) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];
    $appointmentId = $data['appointment_id'];

    // Get patient ID
    $patientQuery = "SELECT id FROM patients WHERE user_id = :user_id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':user_id', $userId);
    $patientStmt->execute();
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    // Verify appointment belongs to this patient
    $verifyQuery = "SELECT id, status FROM appointments 
                    WHERE id = :appointment_id AND patient_id = :patient_id";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([
        ':appointment_id' => $appointmentId,
        ':patient_id' => $patient['id']
    ]);
    
    $appointment = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if ($appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Appointment is already cancelled'], 400);
    }

    if ($appointment['status'] === 'completed') {
        sendJSON(['success' => false, 'message' => 'Cannot cancel completed appointment'], 400);
    }

    // Update appointment status
    $updateQuery = "UPDATE appointments SET 
                      status = 'cancelled',
                      updated_at = CURRENT_TIMESTAMP
                    WHERE id = :appointment_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':appointment_id', $appointmentId);
    $updateStmt->execute();

    // Log audit
    logAudit($db, $userId, 'cancel_appointment', 'appointments', $appointmentId, 'Appointment cancelled by patient');

    sendJSON([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
