<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$role = getCurrentUserRole();
if (!in_array($role, ['doctor', 'admin'])) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 403);
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['appointment_id'])) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $appointmentId = $data['appointment_id'];

    // Verify appointment exists
    $checkQuery = "SELECT id, status FROM appointments WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':id' => $appointmentId]);
    $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if ($appointment['status'] === 'completed') {
        sendJSON(['success' => false, 'message' => 'Appointment is already completed'], 400);
    }

    if ($appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Cannot complete a cancelled appointment'], 400);
    }

    // Update status to completed
    $updateQuery = "UPDATE appointments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $appointmentId]);

    logAudit($db, getCurrentUserId(), 'complete_appointment', 'appointments', $appointmentId, 'Appointment completed');

    sendJSON(['success' => true, 'message' => 'Appointment marked as completed']);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
