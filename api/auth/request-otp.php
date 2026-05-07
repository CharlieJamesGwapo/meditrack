<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Valid email is required'], 400);
}

// Re-load env to know whether we're in development. config.php only exposes
// APP_URL via define(); the raw 'ENVIRONMENT' key is not promoted to a constant,
// so we read env.php again here for the dev-mode gate below.
$envForOtp = require __DIR__ . '/../../env.php';
$isDev = ($envForOtp['ENVIRONMENT'] ?? 'production') === 'development';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal whether email exists
        sendJSON(['success' => true, 'message' => 'If this email is registered, an OTP has been sent']);
        return; // stop execution — $user is null beyond this point
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

    // Email the OTP. In production this is the ONLY way the user receives it.
    // In development we also surface it in the response for easier testing.
    $emailSent = false;
    try {
        $emailSent = (new Mailer())->sendOTP($email, $otp);
    } catch (Exception $e) {
        error_log("Request OTP mail error: " . $e->getMessage());
    }

    if (!$isDev && !$emailSent) {
        // Don't strand the user with an OTP that never reached them in prod.
        sendJSON(['success' => false, 'message' => 'Could not send OTP email. Please try again later.'], 500);
    }

    logActivity($db, $user['id'], $user['username'], $user['role'] ?? 'patient', 'UPDATE', 'Auth', $user['id'], "OTP requested for password reset");

    $response = [
        'success'    => true,
        'message'    => 'OTP sent to your email',
        'expires_at' => $expires_at,
    ];
    if ($isDev) {
        // Dev convenience only — surfaces in forgot-password.html as a "Development OTP" banner.
        $response['otp'] = $otp;
    }
    sendJSON($response);

} catch (Exception $e) {
    error_log("Request OTP error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to generate OTP. Please try again.'], 500);
}
