<?php
/**
 * Email Configuration Test
 * Run this file to test if your email settings are working
 * Access: http://localhost/meditrack/test-email-config.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Configuration Test - MediTrack</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #10b981; }
        .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .config { background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
        button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #059669; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📧 Email Configuration Test</h1>";

// Check if PHPMailer exists
$phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
if (!file_exists($phpmailerPath)) {
    echo "<div class='error'>
        <strong>❌ PHPMailer Not Found!</strong><br>
        Please install PHPMailer first:<br>
        <code>composer require phpmailer/phpmailer</code><br>
        Or run: <code>install-phpmailer.bat</code>
    </div>";
    echo "</div></body></html>";
    exit;
}

// Load email configuration
require_once __DIR__ . '/config/email.php';

echo "<div class='success'>✅ PHPMailer found!</div>";

// Display current configuration
echo "<h2>Current Email Configuration:</h2>";
echo "<div class='config'>";
echo "<strong>SMTP Host:</strong> " . EmailConfig::SMTP_HOST . "<br>";
echo "<strong>SMTP Port:</strong> " . EmailConfig::SMTP_PORT . "<br>";
echo "<strong>SMTP Username:</strong> " . EmailConfig::SMTP_USERNAME . "<br>";
echo "<strong>SMTP Password:</strong> " . str_repeat('*', strlen(EmailConfig::SMTP_PASSWORD)) . " (hidden)<br>";
echo "<strong>From Email:</strong> " . EmailConfig::FROM_EMAIL . "<br>";
echo "<strong>From Name:</strong> " . EmailConfig::FROM_NAME . "<br>";
echo "<strong>Email Enabled:</strong> " . (EmailConfig::ENABLE_EMAIL ? 'Yes' : 'No') . "<br>";
echo "</div>";

// Test email sending
if (isset($_POST['send_test'])) {
    echo "<h2>Sending Test Email...</h2>";
    
    $testEmail = $_POST['test_email'] ?? '';
    
    if (empty($testEmail)) {
        echo "<div class='error'>❌ Please enter an email address!</div>";
    } else {
        try {
            $testData = [
                'full_name' => 'Dr. Test User',
                'email' => $testEmail,
                'username' => 'dr.test',
                'password' => 'test123456',
                'department' => 'Test Department',
                'specialization' => 'Test Specialist'
            ];
            
            $result = EmailConfig::sendDoctorAccountEmail($testData);
            
            if ($result['success']) {
                echo "<div class='success'>
                    <strong>✅ Email Sent Successfully!</strong><br>
                    Check your inbox at: <strong>{$testEmail}</strong><br>
                    Message: {$result['message']}
                </div>";
            } else {
                echo "<div class='error'>
                    <strong>❌ Email Sending Failed!</strong><br>
                    Error: {$result['message']}
                </div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>
                <strong>❌ Exception Occurred!</strong><br>
                Error: " . $e->getMessage() . "
            </div>";
        }
    }
}

// Test form
echo "<h2>Send Test Email:</h2>";
echo "<form method='POST'>
    <div class='info'>
        <strong>ℹ️ Enter your email address to receive a test email</strong><br>
        This will send a sample doctor account creation email to verify your configuration.
    </div>
    <input type='email' name='test_email' placeholder='your-email@example.com' 
           style='width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px;' required>
    <button type='submit' name='send_test'>Send Test Email</button>
</form>";

// Troubleshooting tips
echo "<h2>Troubleshooting:</h2>";
echo "<div class='info'>
    <strong>If email is not working:</strong><br>
    1. ✅ Verify Gmail 2-Factor Authentication is enabled<br>
    2. ✅ Generate new App Password at: <a href='https://myaccount.google.com/apppasswords' target='_blank'>Google App Passwords</a><br>
    3. ✅ Make sure App Password has no spaces: <code>rtegcvlllmtaxnin</code><br>
    4. ✅ Check your Gmail allows 'Less secure app access' (if needed)<br>
    5. ✅ Verify SMTP settings are correct<br>
    6. ✅ Enable DEBUG_MODE in config/email.php for detailed errors<br>
    7. ✅ Check PHP error logs: <code>c:\\xampp\\php\\logs\\php_error_log.txt</code>
</div>";

echo "<h2>Configuration Files:</h2>";
echo "<div class='config'>";
echo "<strong>Email Config:</strong> config/email.php<br>";
echo "<strong>App Config:</strong> config/config.php<br>";
echo "</div>";

echo "</div></body></html>";
?>
