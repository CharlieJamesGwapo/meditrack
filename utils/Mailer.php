<?php
/**
 * Simple SMTP Mailer using PHP's built-in mail() with SMTP
 * Uses cPanel email for sending OTP and notifications
 */
class Mailer {
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        $env = require __DIR__ . '/../env.php';
        $this->smtpHost  = $env['SMTP_HOST']  ?? 'merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com';
        $this->smtpPort  = $env['SMTP_PORT']  ?? 465;
        $this->smtpUser  = $env['SMTP_USER']  ?? 'meditrack@merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com';
        $this->smtpPass  = $env['SMTP_PASS']  ?? '';
        $this->fromEmail = $env['SMTP_FROM']  ?? $this->smtpUser;
        $this->fromName  = $env['SMTP_NAME']  ?? 'Internal Medicine OPD';
    }

    /**
     * Send email using SMTP socket connection
     */
    public function send($to, $subject, $htmlBody) {
        // Suppress warnings to prevent breaking JSON output
        $prevErrorReporting = error_reporting(0);
        try {
            $socket = @fsockopen('ssl://' . $this->smtpHost, $this->smtpPort, $errno, $errstr, 10);
            if (!$socket) {
                error_log("SMTP Connection failed: $errstr ($errno)");
                return false;
            }

            $this->getResponse($socket);
            $this->sendCommand($socket, "EHLO " . gethostname());
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->smtpUser));
            $this->sendCommand($socket, base64_encode($this->smtpPass));
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->sendCommand($socket, "RCPT TO:<{$to}>");
            $this->sendCommand($socket, "DATA");

            $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";

            @fwrite($socket, $headers . $htmlBody . "\r\n.\r\n");
            $this->getResponse($socket);

            $this->sendCommand($socket, "QUIT");
            @fclose($socket);
            error_reporting($prevErrorReporting);
            return true;

        } catch (Exception $e) {
            error_reporting($prevErrorReporting);
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP email
     */
    public function sendOTP($to, $otp) {
        $subject = "IM-OPD - Password Reset OTP";
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#0f766e,#0284c7);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
                <p style='color:#ccfbf1;margin:5px 0 0;'>Internal Medicine OPD Management System</p>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>You requested a password reset. Use this OTP code:</p>
                <div style='text-align:center;margin:25px 0;'>
                    <span style='font-size:32px;font-weight:bold;letter-spacing:8px;color:#0f766e;background:#f0fdfa;padding:15px 30px;border-radius:8px;border:2px dashed #14b8a6;'>{$otp}</span>
                </div>
                <p style='color:#6b7280;font-size:14px;'>This code expires in <strong>15 minutes</strong>.</p>
                <p style='color:#6b7280;font-size:14px;'>If you didn't request this, please ignore this email.</p>
            </div>
            <p style='text-align:center;color:#9ca3af;font-size:12px;margin-top:15px;'>Internal Medicine OPD Management System</p>
        </div>";
        return $this->send($to, $subject, $body);
    }

    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($to, $patientName, $appointmentNumber, $date, $time, $doctorName) {
        $subject = "IM-OPD - Appointment Confirmed #{$appointmentNumber}";
        $formattedDate = date('F j, Y', strtotime($date));
        $formattedTime = date('g:i A', strtotime($time));
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#0f766e,#0284c7);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>Hi <strong>{$patientName}</strong>,</p>
                <p style='color:#374151;'>Your appointment has been confirmed!</p>
                <table style='width:100%;margin:20px 0;border-collapse:collapse;'>
                    <tr><td style='padding:8px;color:#6b7280;'>Appointment #</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$appointmentNumber}</td></tr>
                    <tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;'>Date</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedDate}</td></tr>
                    <tr><td style='padding:8px;color:#6b7280;'>Time</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedTime}</td></tr>
                    <tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;'>Doctor</td><td style='padding:8px;font-weight:bold;color:#0f766e;'>{$doctorName}</td></tr>
                </table>
                <p style='color:#6b7280;font-size:14px;'>Please arrive 10 minutes early. Bring your QR code for quick check-in.</p>
            </div>
        </div>";
        return $this->send($to, $subject, $body);
    }

    /**
     * Send patient self-cancellation confirmation
     */
    public function sendCancellationConfirmation($to, $patientName, $appointmentNumber, $date, $time) {
        $subject = "IM-OPD - Appointment Cancelled #{$appointmentNumber}";
        $formattedDate = date('F j, Y', strtotime($date));
        $formattedTime = date('g:i A', strtotime($time));
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#0f766e,#0284c7);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>Hi <strong>{$patientName}</strong>,</p>
                <p style='color:#374151;'>Your appointment has been cancelled as requested.</p>
                <table style='width:100%;margin:20px 0;border-collapse:collapse;'>
                    <tr><td style='padding:8px;color:#6b7280;'>Appointment #</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$appointmentNumber}</td></tr>
                    <tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;'>Date</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedDate}</td></tr>
                    <tr><td style='padding:8px;color:#6b7280;'>Time</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedTime}</td></tr>
                </table>
                <p style='color:#6b7280;font-size:14px;'>You may book a new appointment anytime from your dashboard.</p>
            </div>
        </div>";
        return $this->send($to, $subject, $body);
    }

    /**
     * Send doctor day-cancelled notification
     */
    public function sendDayCancelled($to, $patientName, $appointmentNumber, $date, $time, $reason) {
        $subject = "IM-OPD - Appointment Cancelled (Doctor Unavailable) #{$appointmentNumber}";
        $formattedDate = date('F j, Y', strtotime($date));
        $formattedTime = date('g:i A', strtotime($time));
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#b91c1c,#ea580c);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>Hi <strong>{$patientName}</strong>,</p>
                <p style='color:#374151;'>We regret to inform you that your appointment on <strong>{$formattedDate}</strong> at <strong>{$formattedTime}</strong> (#{$appointmentNumber}) has been cancelled because the doctor is unavailable.</p>
                <p style='color:#374151;'><strong>Reason:</strong> {$safeReason}</p>
                <p style='color:#6b7280;font-size:14px;'>Please do not come to the clinic on this date. You may rebook an appointment from your dashboard at your earliest convenience.</p>
            </div>
        </div>";
        return $this->send($to, $subject, $body);
    }

    private function sendCommand($socket, $command) {
        @fwrite($socket, $command . "\r\n");
        return $this->getResponse($socket);
    }

    private function getResponse($socket) {
        $response = '';
        while ($line = @fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
}
