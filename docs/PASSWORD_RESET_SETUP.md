# Password Reset System - Setup Guide

## Overview
This document explains how to set up and use the OTP-based password reset system for MediTrack.

## Features
- ✅ Secure 6-digit OTP sent via email
- ✅ OTP expires after 10 minutes
- ✅ Email verification required before password reset
- ✅ Strong password validation
- ✅ Professional email templates
- ✅ Audit logging for security
- ✅ Automatic cleanup of expired requests

## Prerequisites

### 1. PHPMailer Installation
The system uses PHPMailer for sending emails. Install it via Composer:

```bash
cd c:\xampp\htdocs\meditrack
composer require phpmailer/phpmailer
```

If you don't have Composer installed:
1. Download from: https://getcomposer.org/download/
2. Install Composer
3. Run the command above

### 2. Gmail App Password Setup

**Important:** You cannot use your regular Gmail password. You must create an App Password.

#### Steps to create Gmail App Password:

1. **Enable 2-Factor Authentication:**
   - Go to: https://myaccount.google.com/security
   - Enable "2-Step Verification"

2. **Generate App Password:**
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and "Windows Computer" (or Other)
   - Click "Generate"
   - Copy the 16-character password (no spaces)

3. **Update config.php:**
   ```php
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-16-char-app-password');
   ```

### 3. Database Setup

Run the migration SQL file to create the `password_resets` table:

```sql
-- Execute this in phpMyAdmin or MySQL command line
SOURCE c:/xampp/htdocs/meditrack/database/migrations/create_password_resets_table.sql;
```

Or manually in phpMyAdmin:
1. Open phpMyAdmin
2. Select `meditrack` database
3. Go to SQL tab
4. Copy and paste the contents of `create_password_resets_table.sql`
5. Click "Go"

## File Structure

```
meditrack/
├── pages/
│   ├── forgot-password.html      # Step 1: Request OTP
│   ├── verify-otp.html           # Step 2: Verify OTP
│   └── reset-password.html       # Step 3: Reset Password
├── api/
│   └── auth/
│       ├── request-otp.php       # Send OTP to email
│       ├── verify-otp.php        # Verify OTP code
│       └── reset-password.php    # Update password
├── database/
│   └── migrations/
│       └── create_password_resets_table.sql
└── docs/
    └── PASSWORD_RESET_SETUP.md   # This file
```

## User Flow

### Step 1: Request OTP
1. User clicks "Forgot Password?" on login page
2. Enters registered email address
3. System generates 6-digit OTP
4. OTP sent to email (valid for 10 minutes)

### Step 2: Verify OTP
1. User enters 6-digit OTP from email
2. System validates OTP and expiry time
3. If valid, generates reset token
4. User redirected to reset password page

### Step 3: Reset Password
1. User enters new password (must meet requirements)
2. Confirms new password
3. System validates and updates password
4. Confirmation email sent
5. User redirected to login page

## Password Requirements

New passwords must meet these criteria:
- ✅ Minimum 8 characters
- ✅ At least one uppercase letter (A-Z)
- ✅ At least one lowercase letter (a-z)
- ✅ At least one number (0-9)
- ✅ At least one special character (!@#$%^&*)

## Security Features

### OTP Security
- 6-digit random code
- 10-minute expiration
- One-time use only
- Cannot be reused after verification

### Token Security
- 64-character random reset token
- Linked to verified OTP
- Expires with OTP
- Marked as used after password reset

### Email Security
- Only registered emails can request OTP
- No information disclosure (same message for valid/invalid emails)
- Confirmation email sent after successful reset

### Audit Logging
All password reset activities are logged:
- OTP request
- OTP verification
- Password reset completion

## Testing the System

### Test Email Sending

Create a test file: `test-email.php`

```php
<?php
require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('test@example.com', 'Test User');
    
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - MediTrack';
    $mail->Body    = '<h1>Test Email</h1><p>If you receive this, email is working!</p>';
    
    $mail->send();
    echo 'Email sent successfully!';
} catch (Exception $e) {
    echo "Email failed: {$mail->ErrorInfo}";
}
?>
```

### Test Password Reset Flow

1. **Test OTP Request:**
   ```bash
   curl -X POST http://localhost/meditrack/api/auth/request-otp.php \
   -H "Content-Type: application/json" \
   -d '{"email":"test@example.com"}'
   ```

2. **Check email for OTP**

3. **Test OTP Verification:**
   ```bash
   curl -X POST http://localhost/meditrack/api/auth/verify-otp.php \
   -H "Content-Type: application/json" \
   -d '{"email":"test@example.com","otp":"123456"}'
   ```

4. **Test Password Reset:**
   ```bash
   curl -X POST http://localhost/meditrack/api/auth/reset-password.php \
   -H "Content-Type: application/json" \
   -d '{"email":"test@example.com","reset_token":"your-token","new_password":"NewPass123!"}'
   ```

## Troubleshooting

### Email Not Sending

**Problem:** OTP email not received

**Solutions:**
1. Check Gmail App Password is correct (16 characters, no spaces)
2. Verify 2FA is enabled on Gmail account
3. Check spam/junk folder
4. Verify SMTP settings in `config.php`
5. Check PHP error logs: `c:\xampp\php\logs\php_error_log`

### OTP Expired

**Problem:** OTP expired before user could enter it

**Solution:**
- OTP is valid for 10 minutes
- User can request a new OTP
- Check system time is correct

### Database Errors

**Problem:** Table doesn't exist

**Solution:**
```sql
-- Run the migration again
SOURCE c:/xampp/htdocs/meditrack/database/migrations/create_password_resets_table.sql;
```

### PHPMailer Not Found

**Problem:** Class 'PHPMailer\PHPMailer\PHPMailer' not found

**Solution:**
```bash
cd c:\xampp\htdocs\meditrack
composer install
```

## Email Template Customization

To customize the email templates, edit:
- `api/auth/request-otp.php` (OTP email)
- `api/auth/reset-password.php` (Confirmation email)

Look for the `$mail->Body` section and modify the HTML.

## Configuration Options

In `config/config.php`, you can customize:

```php
// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'MediTrack Hospital System');
```

## Security Best Practices

1. **Never commit sensitive data:**
   - Don't commit `config.php` with real credentials
   - Use environment variables in production

2. **Monitor password reset attempts:**
   - Check audit logs regularly
   - Look for suspicious patterns

3. **Rate limiting:**
   - Consider adding rate limiting to prevent abuse
   - Limit OTP requests per email per hour

4. **HTTPS only:**
   - Always use HTTPS in production
   - Never send passwords over HTTP

## Support

For issues or questions:
1. Check error logs: `c:\xampp\apache\logs\error.log`
2. Check PHP logs: `c:\xampp\php\logs\php_error_log`
3. Review audit logs in database: `SELECT * FROM audit_logs WHERE action LIKE '%password%'`

## Maintenance

### Cleanup Old Records

The system automatically cleans up expired password reset requests every hour.

To manually cleanup:
```sql
DELETE FROM password_resets 
WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### Monitor Usage

```sql
-- Check recent password reset activity
SELECT * FROM password_resets 
ORDER BY created_at DESC 
LIMIT 50;

-- Check audit logs
SELECT * FROM audit_logs 
WHERE action IN ('password_reset_requested', 'otp_verified', 'password_reset_completed')
ORDER BY created_at DESC 
LIMIT 50;
```

## Production Deployment

Before deploying to production:

1. ✅ Use environment variables for sensitive data
2. ✅ Enable HTTPS
3. ✅ Set up proper error logging
4. ✅ Configure email rate limiting
5. ✅ Test all email templates
6. ✅ Set up monitoring and alerts
7. ✅ Review security settings
8. ✅ Backup database regularly

---

**Version:** 1.0.0  
**Last Updated:** November 2024  
**Author:** MediTrack Development Team
