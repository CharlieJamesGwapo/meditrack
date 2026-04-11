<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Valid email is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal whether email exists
        sendJSON(['success' => true, 'message' => 'If this email is registered, an OTP has been sent']);
    }

    // Delete old OTPs for this email
    $db->prepare("DELETE FROM password_resets WHERE email = :email")
       ->execute([':email' => $email]);

    // Generate 6-digit OTP
    $otp = sprintf('%06d', random_int(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $db->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (:email, :otp, :expires_at)");
    $stmt->execute([
        ':email'      => $email,
        ':otp'        => $otp,
        ':expires_at' => $expires_at
    ]);

    logActivity($db, $user['id'], $user['username'], 'patient', 'UPDATE', 'Auth', $user['id'], "OTP requested for password reset");

    sendJSON([
        'success'    => true,
        'message'    => 'OTP generated successfully',
        'otp'        => $otp,
        'expires_at' => $expires_at
    ]);

} catch (Exception $e) {
    error_log("Request OTP error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to generate OTP. Please try again.'], 500);
}
