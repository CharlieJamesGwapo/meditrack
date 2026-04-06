<?php
/**
 * Environment Configuration
 * Auto-detects local vs production environment
 */

// Auto-detect: if running on cPanel server, use production credentials
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'cpanel.site') !== false
              || strpos($_SERVER['HTTP_HOST'] ?? '', 'stjohnbaptist') !== false
              || strpos(gethostname(), 'byethost') !== false);

if ($isProduction) {
    return [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'stjohnba_meditrack',
        'DB_USERNAME' => 'stjohnba_meditrack',
        'DB_PASSWORD' => 'Meditrack2026',
        'APP_URL' => 'http://merry-scarlet-gazelle.31-22-4-108.cpanel.site/meditrack',
        'ENVIRONMENT' => 'production',
        'SMTP_HOST' => 'merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_PORT' => 465,
        'SMTP_USER' => 'meditrack@merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_PASS' => '',
        'SMTP_FROM' => 'meditrack@merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_NAME' => 'MediTrack Clinic',
    ];
} else {
    return [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'meditrack',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '',
        'APP_URL' => 'http://localhost/meditrack',
        'ENVIRONMENT' => 'development',
        'SMTP_HOST' => 'merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_PORT' => 465,
        'SMTP_USER' => 'meditrack@merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_PASS' => '',
        'SMTP_FROM' => 'meditrack@merry-scarlet-gazelle.stjohnbaptisthighschoolinc.com',
        'SMTP_NAME' => 'MediTrack Clinic',
    ];
}
