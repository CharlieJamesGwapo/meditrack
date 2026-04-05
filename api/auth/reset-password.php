<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email        = sanitizeInput($input['email'] ?? '');
$otp          = sanitizeInput($input['otp'] ?? '');
$new_password = $input['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($new_password)) {
    sendJSON(['success' => false, 'message' => 'Email, OTP, and new password are required'], 400);
}
if (strlen($new_password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate OTP
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = :email AND otp = :otp AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':email' => $email, ':otp' => $otp]);
    $reset = $stmt->fetch();

    if (!$reset) {
        sendJSON(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
    }

    // Get user
    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJSON(['success' => false, 'message' => 'User not found'], 404);
    }

    $db->beginTransaction();

    $password_hash = password_hash($new_password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
       ->execute([':hash' => $password_hash, ':id' => $user['id']]);

    $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id")
       ->execute([':id' => $reset['id']]);

    $db->commit();

    logActivity($db, $user['id'], $user['username'], 'patient', 'UPDATE', 'Auth', $user['id'], "Password reset successfully");

    sendJSON(['success' => true, 'message' => 'Password reset successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("Reset password error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to reset password. Please try again.'], 500);
}
