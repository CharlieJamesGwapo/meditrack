<?php
/**
 * Email Service using PHPMailer
 * Handles all email notifications for MediTrack
 */

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    private function configureSMTP() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = SMTP_HOST;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = SMTP_USERNAME;
            $this->mailer->Password   = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = SMTP_PORT;
            
            // Debug settings (enable for testing, disable in production)
            $this->mailer->SMTPDebug  = 2; // 0 = off, 2 = verbose debugging
            $this->mailer->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str");
            };
            
            // Additional settings for better compatibility
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Sender info
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
            error_log("Email configured: " . SMTP_USERNAME . " via " . SMTP_HOST);
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send registration success email
     */
    public function sendRegistrationEmail($recipientEmail, $recipientName, $username) {
        try {
            error_log("Attempting to send email to: $recipientEmail");
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail, $recipientName);
            
            $this->mailer->Subject = '🎉 Welcome to MediTrack - Account Created Successfully';
            
            $emailBody = $this->getRegistrationEmailTemplate($recipientName, $username);
            $this->mailer->Body = $emailBody;
            
            // Plain text version
            $plainText = "Welcome to MediTrack!\n\n";
            $plainText .= "Hi $recipientName,\n\n";
            $plainText .= "Your account has been created successfully!\n\n";
            $plainText .= "Username: $username\n";
            $plainText .= "Login at: " . APP_URL . "/pages/login.html\n\n";
            $plainText .= "Thank you for choosing MediTrack!\n";
            $this->mailer->AltBody = $plainText;
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("✅ Email sent successfully to: $recipientEmail");
            } else {
                error_log("❌ Email failed to send to: $recipientEmail");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("❌ Email sending error: " . $this->mailer->ErrorInfo);
            error_log("Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get professional registration email template
     */
    private function getRegistrationEmailTemplate($name, $username) {
        $appUrl = APP_URL;
        $currentYear = date('Y');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MediTrack</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0fdf4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0fdf4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header with green gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: bold;">
                                🏥 MediTrack
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #dcfce7; font-size: 16px;">
                                Healthcare Management System
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 40px 30px 20px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: #dcfce7; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 48px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <h2 style="color: #10b981; font-size: 28px; margin: 0 0 20px 0; text-align: center;">
                                Welcome to MediTrack!
                            </h2>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Dear <strong style="color: #10b981;">{$name}</strong>,
                            </p>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Congratulations! Your account has been created successfully. You can now access all features of MediTrack healthcare management system.
                            </p>
                            
                            <!-- Account Details Box -->
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 30px 0; border-radius: 8px;">
                                <h3 style="color: #059669; font-size: 18px; margin: 0 0 15px 0;">
                                    📋 Your Account Details
                                </h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Username:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$username}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Account Type:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">Patient</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Status:</td>
                                        <td style="padding: 8px 0;">
                                            <span style="background-color: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;">Active</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Features List -->
                            <div style="margin: 30px 0;">
                                <h3 style="color: #059669; font-size: 18px; margin: 0 0 15px 0;">
                                    ✨ What You Can Do
                                </h3>
                                <ul style="color: #374151; font-size: 15px; line-height: 1.8; padding-left: 20px; margin: 0;">
                                    <li>Book and manage appointments with doctors</li>
                                    <li>View your medical history and records</li>
                                    <li>Receive appointment reminders and notifications</li>
                                    <li>Access QR codes for quick check-in</li>
                                    <li>Update your profile and medical information</li>
                                </ul>
                            </div>
                            
                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 40px 0 30px 0;">
                                <a href="{$appUrl}/index.html" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                    Login to Your Account
                                </a>
                            </div>
                            
                            <!-- Security Notice -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px;">
                                <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                    <strong>🔒 Security Tip:</strong> Keep your password secure and never share it with anyone. If you didn't create this account, please contact us immediately.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@meditrack.com" style="color: #10b981; text-decoration: none;">support@meditrack.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                © {$currentYear} MediTrack. All rights reserved.<br>
                                Region XIII - Caraga Administrative Region, Philippines
                            </p>
                            <div style="margin-top: 20px;">
                                <a href="{$appUrl}" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">Visit Website</a>
                                <span style="color: #d1d5db;">|</span>
                                <a href="{$appUrl}/pages/privacy.html" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">Privacy Policy</a>
                                <span style="color: #d1d5db;">|</span>
                                <a href="{$appUrl}/pages/terms.html" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">Terms of Service</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($recipientEmail, $recipientName, $appointmentDetails) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail, $recipientName);
            
            $this->mailer->Subject = 'Appointment Confirmed - MediTrack';
            
            $emailBody = $this->getAppointmentEmailTemplate($recipientName, $appointmentDetails);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = strip_tags($emailBody);
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function getAppointmentEmailTemplate($name, $details) {
        $appUrl = APP_URL;
        $currentYear = date('Y');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmation</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0fdf4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0fdf4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: bold;">
                                🏥 MediTrack
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #dcfce7; font-size: 16px;">
                                Appointment Confirmation
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 40px 30px 20px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: #dcfce7; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 48px;">📅</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <h2 style="color: #10b981; font-size: 28px; margin: 0 0 20px 0; text-align: center;">
                                Appointment Confirmed!
                            </h2>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Dear <strong style="color: #10b981;">{$name}</strong>,
                            </p>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                Your appointment has been successfully booked. Please find the details below:
                            </p>
                            
                            <!-- Appointment Details Box -->
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 30px 0; border-radius: 8px;">
                                <h3 style="color: #059669; font-size: 18px; margin: 0 0 15px 0;">
                                    📋 Appointment Details
                                </h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;">Appointment Number:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['appointment_number']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Doctor:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['doctor_name']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Specialization:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$details['specialization']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Department:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$details['department']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Date:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['date']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Time:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['time']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Status:</td>
                                        <td style="padding: 8px 0;">
                                            <span style="background-color: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;">CONFIRMED</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Important Information -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px;">
                                <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                    <strong>⏰ Important:</strong> Please arrive 15 minutes before your scheduled appointment time. Bring a valid ID and any relevant medical documents.
                                </p>
                            </div>
                            
                            <!-- What to Bring -->
                            <div style="margin: 30px 0;">
                                <h3 style="color: #059669; font-size: 18px; margin: 0 0 15px 0;">
                                    📝 What to Bring
                                </h3>
                                <ul style="color: #374151; font-size: 15px; line-height: 1.8; padding-left: 20px; margin: 0;">
                                    <li>Valid government-issued ID</li>
                                    <li>Previous medical records (if any)</li>
                                    <li>List of current medications</li>
                                    <li>Insurance card (if applicable)</li>
                                </ul>
                            </div>
                            
                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 40px 0 30px 0;">
                                <a href="{$appUrl}/pages/patient-dashboard.html" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                    View My Appointments
                                </a>
                            </div>
                            
                            <!-- Cancellation Policy -->
                            <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 8px;">
                                <p style="color: #991b1b; font-size: 14px; margin: 0; line-height: 1.5;">
                                    <strong>📌 Cancellation Policy:</strong> If you need to cancel or reschedule, please do so at least 24 hours in advance through your dashboard or by contacting us.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@meditrack.com" style="color: #10b981; text-decoration: none;">support@meditrack.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                © {$currentYear} MediTrack. All rights reserved.<br>
                                Region XIII - Caraga Administrative Region, Philippines
                            </p>
                            <div style="margin-top: 20px;">
                                <a href="{$appUrl}" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">Visit Website</a>
                                <span style="color: #d1d5db;">|</span>
                                <a href="{$appUrl}/pages/patient-dashboard.html" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">My Dashboard</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Send appointment notification email to doctor
     */
    public function sendDoctorAppointmentNotification($recipientEmail, $doctorName, $appointmentDetails) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail, $doctorName);
            
            $this->mailer->Subject = 'New Appointment Scheduled - MediTrack';
            
            $emailBody = $this->getDoctorAppointmentEmailTemplate($doctorName, $appointmentDetails);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = strip_tags($emailBody);
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function getDoctorAppointmentEmailTemplate($doctorName, $details) {
        $appUrl = APP_URL;
        $currentYear = date('Y');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Appointment Notification</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0fdf4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0fdf4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: bold;">
                                🏥 MediTrack
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #dcfce7; font-size: 16px;">
                                New Appointment Notification
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Notification Icon -->
                    <tr>
                        <td style="padding: 40px 30px 20px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: #dbeafe; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 48px;">🔔</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <h2 style="color: #10b981; font-size: 28px; margin: 0 0 20px 0; text-align: center;">
                                New Appointment Scheduled
                            </h2>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Dear <strong style="color: #10b981;">Dr. {$doctorName}</strong>,
                            </p>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                A new appointment has been scheduled with you. Please review the details below:
                            </p>
                            
                            <!-- Appointment Details Box -->
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 30px 0; border-radius: 8px;">
                                <h3 style="color: #059669; font-size: 18px; margin: 0 0 15px 0;">
                                    📋 Appointment Details
                                </h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;">Appointment Number:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['appointment_number']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Patient Name:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['patient_name']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Patient Phone:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$details['patient_phone']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Date:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['date']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Time:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; font-weight: bold;">{$details['time']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Reason for Visit:</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">{$details['reason']}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Status:</td>
                                        <td style="padding: 8px 0;">
                                            <span style="background-color: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;">SCHEDULED</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Important Information -->
                            <div style="background-color: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 8px;">
                                <p style="color: #1e40af; font-size: 14px; margin: 0; line-height: 1.5;">
                                    <strong>📌 Note:</strong> This appointment has been confirmed and the patient has been notified. Please review your schedule and prepare accordingly.
                                </p>
                            </div>
                            
                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 40px 0 30px 0;">
                                <a href="{$appUrl}/pages/doctor-dashboard.html" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                    View in Dashboard
                                </a>
                            </div>
                            
                            <!-- Reminder -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px;">
                                <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                    <strong>⏰ Reminder:</strong> You can view all your appointments and patient details in your dashboard. Make sure to check in the patient when they arrive.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@meditrack.com" style="color: #10b981; text-decoration: none;">support@meditrack.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                © {$currentYear} MediTrack. All rights reserved.<br>
                                Region XIII - Caraga Administrative Region, Philippines
                            </p>
                            <div style="margin-top: 20px;">
                                <a href="{$appUrl}" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">Visit Website</a>
                                <span style="color: #d1d5db;">|</span>
                                <a href="{$appUrl}/pages/doctor-dashboard.html" style="color: #10b981; text-decoration: none; font-size: 12px; margin: 0 10px;">My Dashboard</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
