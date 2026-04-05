@echo off
echo ========================================
echo   Activity Log System Setup
echo ========================================
echo.

echo Step 1: Checking MySQL connection...
echo.
echo Please run this SQL command in phpMyAdmin or MySQL:
echo.
echo mysql -u root -p meditrack ^< database/activity_log_table.sql
echo.
pause

echo.
echo Step 2: Verifying files...
echo.

if exist "database\activity_log_table.sql" (
    echo [OK] Database schema file found
) else (
    echo [ERROR] database\activity_log_table.sql not found!
)

if exist "api\activity\log-activity.php" (
    echo [OK] Log activity API found
) else (
    echo [ERROR] api\activity\log-activity.php not found!
)

if exist "api\activity\get-logs.php" (
    echo [OK] Get logs API found
) else (
    echo [ERROR] api\activity\get-logs.php not found!
)

if exist "config\activity-logger.php" (
    echo [OK] Activity logger helper found
) else (
    echo [ERROR] config\activity-logger.php not found!
)

if exist "pages\history-log.html" (
    echo [OK] History log page found
) else (
    echo [ERROR] pages\history-log.html not found!
)

echo.
echo Step 3: Testing setup...
echo.
echo Opening history log page in browser...
start http://localhost/meditrack/pages/history-log.html

echo.
echo ========================================
echo   Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Check if the page loaded successfully
echo 2. Verify the database table was created
echo 3. Test creating a doctor to see logs
echo 4. Read ACTIVITY_LOG_SYSTEM.md for integration guide
echo.
echo Press any key to exit...
pause >nul
