<?php
/**
 * Request OTP for Password Reset
 * Sends a 6-digit OTP to user's registered email using PHPMailer
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
    
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if email exists in users table
    // Try to get user with different possible column names
    $query = "SELECT id, username, email FROM users WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If user found, try to get their name (check which column exists)
    if ($user) {
        // Check if full_name column exists, otherwise use username
        $checkQuery = "SHOW COLUMNS FROM users LIKE 'full_name'";
        $checkStmt = $db->query($checkQuery);
        $hasFullName = $checkStmt->fetch();
        
        if ($hasFullName) {
            $nameQuery = "SELECT full_name FROM users WHERE id = :id";
            $nameStmt = $db->prepare($nameQuery);
            $nameStmt->bindParam(':id', $user['id']);
            $nameStmt->execute();
            $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
            $user['display_name'] = $nameResult['full_name'] ?? $user['username'];
        } else {
            // Try first_name and last_name
            $checkQuery = "SHOW COLUMNS FROM users LIKE 'first_name'";
            $checkStmt = $db->query($checkQuery);
            $hasFirstName = $checkStmt->fetch();
            
            if ($hasFirstName) {
                $nameQuery = "SELECT first_name, last_name FROM users WHERE id = :id";
                $nameStmt = $db->prepare($nameQuery);
                $nameStmt->bindParam(':id', $user['id']);
                $nameStmt->execute();
                $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
                $user['display_name'] = trim(($nameResult['first_name'] ?? '') . ' ' . ($nameResult['last_name'] ?? ''));
            } else {
                $user['display_name'] = $user['username'];
            }
        }
    }
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode(['success' => true, 'message' => 'If this email is registered, you will receive an OTP']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = sprintf('%06d', mt_rand(0, 999999));
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    
    // Set expiry time (10 minutes from now)
    $expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Delete any existing password reset requests for this email
    $deleteQuery = "DELETE FROM password_resets WHERE email = :email";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindParam(':email', $email);
    $deleteStmt->execute();
    
    // Insert new password reset request
    $insertQuery = "INSERT INTO password_resets (email, otp, reset_token, expires_at, created_at) 
                    VALUES (:email, :otp, :reset_token, :expires_at, NOW())";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':otp', $otp);
    $insertStmt->bindParam(':reset_token', $resetToken);
    $insertStmt->bindParam(':expires_at', $expiryTime);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to create password reset request');
    }
    
    // Send email using PHPMailer
    $mail = EmailConfig::getMailer();
    
    if (!$mail) {
        throw new Exception('Failed to initialize email system');
    }
    
    try {
        // Recipients
        $mail->addAddress($email, $user['display_name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - MediTrack';
        
        // Email body with professional design
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
                .otp-box { background: #f0fdf4; border: 2px solid #10b981; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .otp-code { font-size: 36px; font-weight: bold; color: #059669; letter-spacing: 8px; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 10px 10px; }
                .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; padding: 12px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 MediTrack</h1>
                    <p>Password Reset Request</p>
                </div>
                
                <div class="content">
                    <h2>Hello, ' . htmlspecialchars($user['display_name']) . '</h2>
                    
                    <p>We received a request to reset your password. Use the OTP code below to verify your identity:</p>
                    
                    <div class="otp-box">
                        <p style="margin: 0; font-size: 14px; color: #6b7280;">Your OTP Code</p>
                        <div class="otp-code">' . $otp . '</div>
                        <p style="margin: 10px 0 0 0; font-size: 12px; color: #6b7280;">Valid for 10 minutes</p>
                    </div>
                    
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This OTP will expire in <strong>10 minutes</strong></li>
                        <li>Never share this code with anyone</li>
                        <li>If you didn\'t request this, please ignore this email</li>
                    </ul>
                    
                    <div class="warning">
                        <strong>⚠️ Security Alert:</strong><br>
                        If you did not request a password reset, please ignore this email or contact support immediately.
                    </div>
                    
                    <p>For security reasons, this OTP can only be used once.</p>
                    
                    <p>Best regards,<br>
                    <strong>MediTrack Support Team</strong></p>
                </div>
                
                <div class="footer">
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; ' . date('Y') . ' MediTrack Hospital System. All rights reserved.</p>
                    <p style="margin-top: 10px;">
                        <a href="' . APP_URL . '" style="color: #10b981; text-decoration: none;">Visit MediTrack</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "Hello " . $user['display_name'] . ",\n\n" .
                        "Your OTP for password reset is: " . $otp . "\n\n" .
                        "This code will expire in 10 minutes.\n\n" .
                        "If you didn't request this, please ignore this email.\n\n" .
                        "Best regards,\nMediTrack Support Team";
        
        $mail->send();
        
        // Log the activity
        logAudit($db, $user['id'], 'password_reset_requested', 'password_resets', null, 'OTP sent to email');
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP sent successfully to your email'
        ]);
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        throw new Exception('Failed to send email. Please try again later.');
    }
    
} catch (PDOException $e) {
    error_log("Database Error in request-otp.php: " . $e->getMessage());
    
    // Check if it's a table doesn't exist error
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Database table not found. Please run setup-password-reset.sql in phpMyAdmin first.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    error_log("Request OTP Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
