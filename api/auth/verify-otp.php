<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($input['email'] ?? '');
$otp   = sanitizeInput($input['otp'] ?? '');

if (empty($email) || empty($otp)) {
    sendJSON(['success' => false, 'message' => 'Email and OTP are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = :email AND otp = :otp AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':email' => $email, ':otp' => $otp]);
    $reset = $stmt->fetch();

    if (!$reset) {
        sendJSON(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
    }

    // Mark as verified (but not used yet — used after password reset)
    $db->prepare("UPDATE password_resets SET verified = 1 WHERE id = :id")
       ->execute([':id' => $reset['id']]);

    sendJSON(['success' => true, 'message' => 'OTP verified successfully']);

} catch (Exception $e) {
    error_log("verify-otp error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to verify OTP'], 500);
}
