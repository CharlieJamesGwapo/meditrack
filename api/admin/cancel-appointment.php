<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, status, appointment_number FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if (in_array($appointment['status'], ['completed', 'cancelled'])) {
        sendJSON(['success' => false, 'message' => 'Cannot cancel a ' . $appointment['status'] . ' appointment'], 400);
    }

    $db->prepare("UPDATE appointments SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = 'admin', cancel_reason = 'Cancelled by admin' WHERE id = :aid")
       ->execute([':aid' => $appointment_id]);

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Appointments', $appointment_id,
        "Admin cancelled appointment " . $appointment['appointment_number']);

    sendJSON(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    error_log("admin cancel-appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to cancel appointment'], 500);
}
