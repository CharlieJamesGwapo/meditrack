@echo off
echo ========================================
echo MediTrack Password Reset System Setup
echo ========================================
echo.

echo Step 1: Installing PHPMailer via Composer...
echo.

cd /d "%~dp0"

if not exist "composer.json" (
    echo Creating composer.json...
    (
        echo {
        echo     "require": {
        echo         "phpmailer/phpmailer": "^6.8"
        echo     }
        echo }
    ) > composer.json
)

echo Running composer install...
composer install

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Composer installation failed!
    echo Please install Composer from: https://getcomposer.org/download/
    echo Then run this script again.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Step 2: Database Setup
echo ========================================
echo.
echo Please run the following SQL in phpMyAdmin:
echo.
echo 1. Open phpMyAdmin (http://localhost/phpmyadmin)
echo 2. Select 'meditrack' database
echo 3. Go to SQL tab
echo 4. Copy and paste the contents of:
echo    database/migrations/create_password_resets_table.sql
echo 5. Click 'Go'
echo.

pause

echo.
echo ========================================
echo Step 3: Gmail App Password Setup
echo ========================================
echo.
echo IMPORTANT: You need a Gmail App Password!
echo.
echo Follow these steps:
echo 1. Go to: https://myaccount.google.com/security
echo 2. Enable "2-Step Verification"
echo 3. Go to: https://myaccount.google.com/apppasswords
echo 4. Generate an App Password for "Mail"
echo 5. Copy the 16-character password
echo.
echo Then update config/config.php with:
echo   - SMTP_USERNAME: your-email@gmail.com
echo   - SMTP_PASSWORD: your-16-char-app-password
echo.

pause

echo.
echo ========================================
echo Installation Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Update config/config.php with your Gmail credentials
echo 2. Test the system by visiting:
echo    http://localhost/meditrack/pages/forgot-password.html
echo.
echo For detailed instructions, see:
echo docs/PASSWORD_RESET_SETUP.md
echo.

pause
