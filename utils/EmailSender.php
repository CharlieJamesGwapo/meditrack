<?php
/**
 * Email Sender - Professional Email Notifications
 * Uses PHPMailer for sending emails via SMTP
 */

// Always load PHPMailer from local directory (vendor autoload doesn't include it)
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class EmailSender {
    private $mailer;

    public function __construct() {
        try {
            $this->mailer = new PHPMailer(true);
            $this->configureSMTP();
        } catch (\Exception $e) {
            error_log("EmailSender init error: " . $e->getMessage());
            $this->mailer = null;
        }
    }

    private function configureSMTP() {
        try {
            // Load config if not already loaded
            $configFile = __DIR__ . '/../config/config.php';
            if (!defined('SMTP_HOST') && file_exists($configFile)) {
                require_once $configFile;
            }

            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'pforcapstone@gmail.com';
            $this->mailer->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;

            // SSL options for local development
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Sender info
            $this->mailer->setFrom(
                defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'pforcapstone@gmail.com',
                defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'MediTrack Hospital System'
            );

            // Email settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
        } catch (\Exception $e) {
            error_log("SMTP Configuration Error: " . $e->getMessage());
        }
    }

    /**
     * Send QR Code Email
     */
    public function sendQRCodeEmail($patientEmail, $patientName, $appointmentData, $qrImageBase64) {
        // If PHPMailer not available, use fallback
        if ($this->mailer === null) {
            return $this->sendSimpleEmail($patientEmail, $patientName, $appointmentData);
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($patientEmail, $patientName);

            $this->mailer->Subject = 'Your Appointment QR Code - MediTrack';

            $emailBody = $this->getQREmailTemplate($patientName, $appointmentData, $qrImageBase64);
            $this->mailer->Body = $emailBody;

            // Plain text version
            $this->mailer->AltBody = $this->getPlainTextVersion($patientName, $appointmentData);

            $this->mailer->send();
            error_log("✅ QR email sent successfully to: {$patientEmail}");
            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (\Exception $e) {
            error_log("❌ QR email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }

    /**
     * Fallback: Simple PHP mail()
     */
    private function sendSimpleEmail($patientEmail, $patientName, $appointmentData) {
        $subject = 'Your Appointment Confirmation - MediTrack';
        $message = $this->getPlainTextVersion($patientName, $appointmentData);

        $headers = "From: MediTrack <noreply@meditrack.com>\r\n";
        $headers .= "Reply-To: noreply@meditrack.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $sent = @mail($patientEmail, $subject, $message, $headers);

        if ($sent) {
            return ['success' => true, 'message' => 'Email sent via PHP mail()'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email'];
        }
    }

    /**
     * Professional HTML Email Template
     */
    private function getQREmailTemplate($patientName, $appointmentData, $qrImageBase64) {
        $appointmentNumber = $appointmentData['appointment_number'] ?? 'N/A';
        $doctorName = $appointmentData['doctor_name'] ?? 'N/A';
        $department = $appointmentData['department'] ?? 'N/A';
        $date = $appointmentData['date'] ?? 'N/A';
        $time = $appointmentData['time'] ?? 'N/A';
        $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost/meditrack';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment QR Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f7fa; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                MediTrack Hospital
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #d1fae5; font-size: 14px;">
                                Your Health, Our Priority
                            </p>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 40px 30px 20px 30px;">
                            <h2 style="margin: 0 0 15px 0; color: #1f2937; font-size: 24px; font-weight: 600;">
                                Hello, {$patientName}!
                            </h2>
                            <p style="margin: 0; color: #6b7280; font-size: 16px; line-height: 1.6;">
                                Your appointment has been confirmed! Below is your unique QR code for quick check-in.
                            </p>
                        </td>
                    </tr>

                    <!-- QR Code -->
                    <tr>
                        <td align="center" style="padding: 20px 30px;">
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 30px; display: inline-block;">
                                <img src="{$qrImageBase64}" alt="Appointment QR Code" style="width: 250px; height: 250px; border: 4px solid #10b981; border-radius: 8px;" />
                                <p style="margin: 15px 0 0 0; color: #6b7280; font-size: 13px;">
                                    Scan this QR code at the reception
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Appointment Details -->
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px;">
                                <h3 style="margin: 0 0 15px 0; color: #065f46; font-size: 18px; font-weight: 600;">
                                    Appointment Details
                                </h3>
                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                    <tr>
                                        <td style="color: #6b7280; font-size: 14px; font-weight: 600; width: 40%;">Appointment #:</td>
                                        <td style="color: #1f2937; font-size: 14px; font-weight: 500;">{$appointmentNumber}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; font-size: 14px; font-weight: 600;">Doctor:</td>
                                        <td style="color: #1f2937; font-size: 14px; font-weight: 500;">{$doctorName}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; font-size: 14px; font-weight: 600;">Department:</td>
                                        <td style="color: #1f2937; font-size: 14px; font-weight: 500;">{$department}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; font-size: 14px; font-weight: 600;">Date:</td>
                                        <td style="color: #1f2937; font-size: 14px; font-weight: 500;">{$date}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; font-size: 14px; font-weight: 600;">Time:</td>
                                        <td style="color: #1f2937; font-size: 14px; font-weight: 500;">{$time}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Instructions -->
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="background-color: #eff6ff; border-radius: 8px; padding: 20px;">
                                <h3 style="margin: 0 0 12px 0; color: #1e40af; font-size: 16px; font-weight: 600;">
                                    Important Instructions
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #4b5563; font-size: 14px; line-height: 1.8;">
                                    <li>Arrive 15 minutes before your appointment time</li>
                                    <li>Present this QR code at the reception desk</li>
                                    <li>QR code expires in 24 hours</li>
                                    <li>Bring your ID and insurance card</li>
                                    <li>Download or print this email for reference</li>
                                </ul>
                            </div>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td align="center" style="padding: 20px 30px 40px 30px;">
                            <a href="{$appUrl}/pages/patient-dashboard.html" style="display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);">
                                View My Dashboard
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 13px;">
                                Need help? Contact us at <a href="mailto:support@meditrack.com" style="color: #10b981; text-decoration: none;">support@meditrack.com</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                &copy; 2025 MediTrack Hospital System. All rights reserved.
                            </p>
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
     * Plain text version for email clients that don't support HTML
     */
    private function getPlainTextVersion($patientName, $appointmentData) {
        $appointmentNumber = $appointmentData['appointment_number'] ?? 'N/A';
        $doctorName = $appointmentData['doctor_name'] ?? 'N/A';
        $department = $appointmentData['department'] ?? 'N/A';
        $date = $appointmentData['date'] ?? 'N/A';
        $time = $appointmentData['time'] ?? 'N/A';
        $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost/meditrack';

        return <<<TEXT
MediTrack Hospital - Appointment Confirmation

Hello, {$patientName}!

Your appointment has been confirmed. Please find your appointment details below:

APPOINTMENT DETAILS:
-------------------
Appointment #: {$appointmentNumber}
Doctor: {$doctorName}
Department: {$department}
Date: {$date}
Time: {$time}

IMPORTANT INSTRUCTIONS:
----------------------
- Arrive 15 minutes before your appointment time
- Present your QR code at the reception desk
- QR code expires in 24 hours
- Bring your ID and insurance card

You can view your QR code by logging into your patient dashboard:
{$appUrl}/pages/patient-dashboard.html

Need help? Contact us at support@meditrack.com

MediTrack Hospital System. All rights reserved.
TEXT;
    }
}
