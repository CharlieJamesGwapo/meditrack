<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('staff')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password     = $input['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    sendJSON(['success' => false, 'message' => 'Current password and new password are required'], 400);
}
if (strlen($new_password) < 6) {
    sendJSON(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }

    $new_hash = password_hash($new_password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid")
       ->execute([':hash' => $new_hash, ':uid' => $userId]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'staff', 'UPDATE', 'Auth', $userId, "Staff password changed");

    sendJSON(['success' => true, 'message' => 'Password changed successfully']);

} catch (Exception $e) {
    error_log("staff change-password error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to change password'], 500);
}
