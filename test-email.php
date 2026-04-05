<?php
/**
 * Email Test Script
 * Test if email sending is working properly
 */

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'utils/EmailService.php';

header('Content-Type: application/json');

try {
    echo "Testing Email Configuration...\n\n";
    
    // Display current configuration
    echo "SMTP Host: " . SMTP_HOST . "\n";
    echo "SMTP Port: " . SMTP_PORT . "\n";
    echo "SMTP Username: " . SMTP_USERNAME . "\n";
    echo "SMTP From Email: " . SMTP_FROM_EMAIL . "\n";
    echo "SMTP From Name: " . SMTP_FROM_NAME . "\n\n";
    
    // Create email service
    echo "Creating EmailService instance...\n";
    $emailService = new EmailService();
    echo "✅ EmailService created successfully\n\n";
    
    // Test email details
    $testEmail = "capstonee2@gmail.com"; // Send to yourself for testing
    $testName = "Test User";
    $testUsername = "testuser123";
    
    echo "Attempting to send test email to: $testEmail\n";
    echo "This may take a few seconds...\n\n";
    
    // Send test email
    $result = $emailService->sendRegistrationEmail($testEmail, $testName, $testUsername);
    
    if ($result) {
        echo "✅ SUCCESS! Email sent successfully!\n";
        echo "Check your inbox at: $testEmail\n";
        echo "Also check your spam/junk folder if you don't see it.\n";
    } else {
        echo "❌ FAILED! Email was not sent.\n";
        echo "Check the error logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\n";
echo "Check PHP error log at: C:\\xampp\\php\\logs\\php_error_log\n";
echo "Check Apache error log at: C:\\xampp\\apache\\logs\\error.log\n";
