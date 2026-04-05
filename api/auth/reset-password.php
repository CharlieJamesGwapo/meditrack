<?php
/**
 * Reset Password
 * Updates user's password after OTP verification
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../config/email.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || empty($input['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    if (!isset($input['reset_token']) || empty($input['reset_token'])) {
        echo json_encode(['success' => false, 'message' => 'Reset token is required']);
        exit;
    }
    
    if (!isset($input['new_password']) || empty($input['new_password'])) {
        echo json_encode(['success' => false, 'message' => 'New password is required']);
        exit;
    }
    
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $resetToken = trim($input['reset_token']);
    $newPassword = $input['new_password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
        exit;
    }
    
    if (!preg_match('/[a-z]/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter']);
        exit;
    }
    
    if (!preg_match('/[0-9]/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit;
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character']);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify reset token
    $query = "SELECT id, email, reset_token, expires_at, verified, used 
              FROM password_resets 
              WHERE email = :email 
              AND reset_token = :reset_token 
              AND verified = 1 
              AND used = 0
              ORDER BY created_at DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':reset_token', $resetToken);
    $stmt->execute();
    
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resetRequest) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        exit;
    }
    
    // Check if token has expired
    $currentTime = date('Y-m-d H:i:s');
    if ($currentTime > $resetRequest['expires_at']) {
        echo json_encode(['success' => false, 'message' => 'Reset token has expired. Please start the process again.']);
        exit;
    }
    
    // Get user
    $userQuery = "SELECT id, username, email FROM users WHERE email = :email AND status = 'active' LIMIT 1";
    $userStmt = $db->prepare($userQuery);
    $userStmt->bindParam(':email', $email);
    $userStmt->execute();
    
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    
    // Update user password
    $updateQuery = "UPDATE users SET password_hash = :password, updated_at = NOW() WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':user_id', $user['id']);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update password');
    }
    
    // Mark reset token as used
    $markUsedQuery = "UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = :id";
    $markUsedStmt = $db->prepare($markUsedQuery);
    $markUsedStmt->bindParam(':id', $resetRequest['id']);
    $markUsedStmt->execute();
    
    // Log the activity
    logAudit($db, $user['id'], 'password_reset_completed', 'users', $user['id'], 'Password reset successfully');
    
    // Send confirmation email (optional)
    try {
        $mail = EmailConfig::getMailer();
        
        if ($mail) {
            $mail->addAddress($email, $user['username']);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Confirmation - MediTrack';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
                .success-box { background: #f0fdf4; border: 2px solid #10b981; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 10px 10px; }
                .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 MediTrack</h1>
                    <p>Password Reset Confirmation</p>
                </div>
                
                <div class="content">
                    <div class="success-box">
                        <h2 style="color: #059669; margin: 0;">✓ Password Reset Successful</h2>
                    </div>
                    
                    <p>Hello,</p>
                    
                    <p>Your password has been successfully reset on <strong>' . date('F j, Y \a\t g:i A') . '</strong>.</p>
                    
                    <p>You can now login to your MediTrack account using your new password.</p>
                    
                    <div class="warning">
                        <strong>⚠️ Security Alert:</strong><br>
                        If you did not perform this action, please contact our support team immediately.
                    </div>
                    
                    <p><strong>Security Tips:</strong></p>
                    <ul>
                        <li>Never share your password with anyone</li>
                        <li>Use a unique password for your MediTrack account</li>
                        <li>Enable two-factor authentication if available</li>
                        <li>Change your password regularly</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    <strong>MediTrack Support Team</strong></p>
                </div>
                
                <div class="footer">
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; ' . date('Y') . ' MediTrack Hospital System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Your password has been successfully reset.\n\n" .
                        "If you did not perform this action, please contact support immediately.\n\n" .
                        "Best regards,\nMediTrack Support Team";
        
            $mail->send();
        }
    } catch (Exception $e) {
        // Log error but don't fail the password reset
        error_log("Failed to send confirmation email: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>
