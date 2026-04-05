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
$patient_id = (int) ($input['patient_id'] ?? 0);
$status     = sanitizeInput($input['status'] ?? '');

if (!$patient_id || !in_array($status, ['active', 'inactive'])) {
    sendJSON(['success' => false, 'message' => 'Valid patient_id and status (active|inactive) are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT p.user_id, p.full_name FROM patients p WHERE p.id = :pid LIMIT 1");
    $stmt->execute([':pid' => $patient_id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    $db->prepare("UPDATE users SET status = :status WHERE id = :uid")
       ->execute([':status' => $status, ':uid' => $patient['user_id']]);

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Patients', $patient_id,
        "Patient status set to $status: " . $patient['full_name']);

    sendJSON(['success' => true, 'message' => "Patient status updated to $status"]);

} catch (Exception $e) {
    error_log("admin update-patient-status error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update patient status'], 500);
}
