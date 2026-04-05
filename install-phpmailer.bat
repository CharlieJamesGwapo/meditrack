@echo off
echo ========================================
echo  PHPMailer Installation Script
echo  MediTrack Hospital Management System
echo ========================================
echo.

cd /d "%~dp0"

echo Checking if Composer is installed...
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Composer is not installed!
    echo.
    echo Please install Composer first:
    echo 1. Download from: https://getcomposer.org/download/
    echo 2. Run the installer
    echo 3. Restart this script
    echo.
    pause
    exit /b 1
)

echo.
echo Composer found! Installing PHPMailer...
echo.

composer require phpmailer/phpmailer

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo  PHPMailer installed successfully!
    echo ========================================
    echo.
    echo Next steps:
    echo 1. Configure email settings in: config\email.php
    echo 2. Update SMTP_USERNAME with your Gmail
    echo 3. Update SMTP_PASSWORD with App Password
    echo 4. Test the system
    echo.
    echo See DOCTOR_EMAIL_NOTIFICATION_SETUP.md for details
    echo.
) else (
    echo.
    echo [ERROR] Installation failed!
    echo Please check your internet connection and try again.
    echo.
)

pause
