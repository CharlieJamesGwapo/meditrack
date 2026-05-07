<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input   = json_decode(file_get_contents('php://input'), true);
$user_id = (int) ($input['user_id'] ?? 0);
$status  = sanitizeInput($input['status'] ?? '');

if (!$user_id || !in_array($status, ['active', 'inactive'], true)) {
    sendJSON(['success' => false, 'message' => 'user_id and status (active|inactive) are required'], 400);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $u = $stmt->fetch();
    if (!$u || $u['role'] !== 'staff') {
        sendJSON(['success' => false, 'message' => 'Staff user not found'], 404);
    }

    $db->prepare("UPDATE users SET status = :s WHERE id = :uid")
       ->execute([':s' => $status, ':uid' => $user_id]);

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Staff', $user_id, "Set staff user $user_id status=$status");

    sendJSON(['success' => true, 'message' => "Staff status updated to $status"]);
} catch (Exception $e) {
    error_log("update-staff-status error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update staff status'], 500);
}
