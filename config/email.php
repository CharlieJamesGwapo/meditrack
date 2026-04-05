<?php
// Email Configuration for PHPMailer
// This file contains email settings for sending notifications

class EmailConfig {
    // SMTP Settings
    const SMTP_HOST = 'smtp.gmail.com';  // Gmail SMTP server
    const SMTP_PORT = 587;                // TLS port
    const SMTP_SECURE = 'tls';            // TLS encryption
    
    // Email Credentials (Update with your actual email)
    const SMTP_USERNAME = 'pforcapstone@gmail.com';  // Your Gmail address
    const SMTP_PASSWORD = 'rtegcvlllmtaxnin';        // Your Gmail App Password (no spaces)
    
    // Sender Information
    const FROM_EMAIL = 'noreply@meditrack.com';
    const FROM_NAME = 'MediTrack Hospital System';
    
    // System Settings
    const ENABLE_EMAIL = true;  // Set to false to disable email sending
    const DEBUG_MODE = false;   // Set to true for debugging
    
    /**
     * Get PHPMailer instance with configuration
     */
    public static function getMailer() {
        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/../PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = self::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = self::SMTP_USERNAME;
            $mail->Password = self::SMTP_PASSWORD;
            $mail->SMTPSecure = self::SMTP_SECURE;
            $mail->Port = self::SMTP_PORT;
            
            // Sender
            $mail->setFrom(self::FROM_EMAIL, self::FROM_NAME);
            
            // Debug mode
            if (self::DEBUG_MODE) {
                $mail->SMTPDebug = 2;
            }
            
            return $mail;
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send doctor account creation email
     */
    public static function sendDoctorAccountEmail($doctorData) {
        if (!self::ENABLE_EMAIL) {
            return ['success' => true, 'message' => 'Email disabled'];
        }
        
        try {
            $mail = self::getMailer();
            if (!$mail) {
                return ['success' => false, 'message' => 'Email configuration error'];
            }
            
            // Recipient
            $mail->addAddress($doctorData['email'], $doctorData['full_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to MediTrack - Your Doctor Account';
            
            $mail->Body = self::getDoctorWelcomeEmailTemplate($doctorData);
            $mail->AltBody = self::getDoctorWelcomeEmailPlainText($doctorData);
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * HTML Email Template for Doctor Account
     */
    private static function getDoctorWelcomeEmailTemplate($data) {
        $loginUrl = 'http://localhost/meditrack/pages/login.html';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981; }
                .credentials { background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .button { display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
                .highlight { color: #10b981; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 Welcome to MediTrack</h1>
                    <p>Your Doctor Account Has Been Created</p>
                </div>
                
                <div class="content">
                    <h2>Hello Dr. ' . htmlspecialchars($data['full_name']) . ',</h2>
                    
                    <p>Welcome to MediTrack Hospital Management System! Your doctor account has been successfully created.</p>
                    
                    <div class="info-box">
                        <h3>📋 Your Account Details</h3>
                        <p><strong>Full Name:</strong> ' . htmlspecialchars($data['full_name']) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($data['email']) . '</p>
                        <p><strong>Department:</strong> ' . htmlspecialchars($data['department']) . '</p>
                        <p><strong>Specialization:</strong> ' . htmlspecialchars($data['specialization']) . '</p>
                    </div>
                    
                    <div class="credentials">
                        <h3>🔐 Login Credentials</h3>
                        <p><strong>Username:</strong> <span class="highlight">' . htmlspecialchars($data['username']) . '</span></p>
                        <p><strong>Password:</strong> <span class="highlight">' . htmlspecialchars($data['password']) . '</span></p>
                        <p style="color: #ef4444; font-size: 14px;">⚠️ Please change your password after first login</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . $loginUrl . '" class="button">Login to Your Account</a>
                    </div>
                    
                    <div class="info-box">
                        <h3>📱 Getting Started</h3>
                        <ol>
                            <li>Click the login button above or visit: <a href="' . $loginUrl . '">' . $loginUrl . '</a></li>
                            <li>Enter your username and password</li>
                            <li>Complete your profile information</li>
                            <li>Set your availability schedule</li>
                            <li>Start managing your appointments</li>
                        </ol>
                    </div>
                    
                    <p><strong>Need Help?</strong><br>
                    If you have any questions or need assistance, please contact the administrator or IT support.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated email from MediTrack Hospital Management System</p>
                    <p>© 2025 MediTrack. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    /**
     * Plain Text Email for Doctor Account
     */
    private static function getDoctorWelcomeEmailPlainText($data) {
        $loginUrl = 'http://localhost/meditrack/pages/login.html';
        
        return "
Welcome to MediTrack Hospital Management System!

Hello Dr. {$data['full_name']},

Your doctor account has been successfully created.

YOUR ACCOUNT DETAILS:
- Full Name: {$data['full_name']}
- Email: {$data['email']}
- Department: {$data['department']}
- Specialization: {$data['specialization']}

LOGIN CREDENTIALS:
- Username: {$data['username']}
- Password: {$data['password']}

⚠️ IMPORTANT: Please change your password after first login

LOGIN URL: {$loginUrl}

GETTING STARTED:
1. Visit the login page
2. Enter your username and password
3. Complete your profile information
4. Set your availability schedule
5. Start managing your appointments

Need help? Contact the administrator or IT support.

---
This is an automated email from MediTrack Hospital Management System
© 2025 MediTrack. All rights reserved.
        ";
    }
}
