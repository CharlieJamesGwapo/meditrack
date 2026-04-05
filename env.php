<?php
/**
 * Environment Configuration
 *
 * INSTRUCTIONS:
 * 1. Copy this file to the server
 * 2. Fill in your actual database credentials
 * 3. Update APP_URL with your actual domain
 * 4. This file should NEVER be committed to version control
 */

return [
    // Database Settings
    'DB_HOST'     => 'localhost',
    'DB_NAME'     => 'stjohnba_meditrack',
    'DB_USERNAME' => 'stjohnba_meditrack',
    'DB_PASSWORD' => 'Meditrack2026',

    // Application URL (no trailing slash)
    'APP_URL'     => 'http://merry-scarlet-gazelle.31-22-4-108.cpanel.site/meditrack',

    // Email SMTP Settings
    'SMTP_HOST'     => 'smtp.gmail.com',
    'SMTP_PORT'     => 587,
    'SMTP_USERNAME' => 'pforcapstone@gmail.com',
    'SMTP_PASSWORD' => 'rtegcvlllmtaxnin',
    'SMTP_FROM_EMAIL' => 'pforcapstone@gmail.com',
    'SMTP_FROM_NAME'  => 'MediTrack Hospital System',

    // Environment: 'production' or 'development'
    'ENVIRONMENT' => 'production',
];
