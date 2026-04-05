============================================
  MediTrack - cPanel Deployment Guide
============================================

STEP 1: IMPORT THE DATABASE
-----------------------------
1. Go to cPanel > phpMyAdmin
2. Select your database: stjohnba_meditrack
3. Click "Import" tab
4. Choose file: meditrack_complete_database.sql
5. Click "Go" to import

NOTE: If import fails due to CREATE DATABASE line,
edit the SQL file and REMOVE the first 2 lines:
  CREATE DATABASE IF NOT EXISTS ...
  USE ...
phpMyAdmin already has the database selected.


STEP 2: UPLOAD FILES
-----------------------------
1. Go to cPanel > File Manager
2. Navigate to your temporary domain's document root
   (check Domains section for the exact path)
3. Create a folder called "meditrack" inside the document root
4. Upload the meditrack.zip into that folder
5. Right-click the zip > Extract
6. Make sure all files are directly inside /meditrack/
   (not /meditrack/meditrack/)


STEP 3: CONFIGURE env.php
-----------------------------
1. In File Manager, go to the meditrack folder
2. Open env.php for editing
3. Change DB_PASSWORD to your actual database password:
   'DB_PASSWORD' => 'your_actual_password_here',
4. Verify APP_URL matches your domain:
   'APP_URL' => 'http://merry-scarlet-gazelle.31-22-4-108.cpanel.site/meditrack',
5. Save the file


STEP 4: SET PERMISSIONS
-----------------------------
In File Manager, right-click these folders and set permissions to 755:
- /meditrack/uploads/
- /meditrack/logs/ (create if not exists)

For the env.php file, set permissions to 600 (read-only by owner)


STEP 5: TEST
-----------------------------
1. Visit: http://merry-scarlet-gazelle.31-22-4-108.cpanel.site/meditrack/
2. Try logging in
3. If you see errors, check:
   - Database credentials in env.php
   - Database tables were imported correctly
   - File permissions are correct


TROUBLESHOOTING
-----------------------------
- "Database connection failed": Check env.php credentials
- Blank page: Check PHP error logs in cPanel > Errors
- 500 error: Check .htaccess compatibility, try removing mod_php7 section
- Email not working: Byethost may block outgoing SMTP on port 587
