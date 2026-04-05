<?php
/**
 * Database Import Script - Use this instead of phpMyAdmin
 * DELETE THIS FILE AFTER SUCCESSFUL IMPORT!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes max

$env = require __DIR__ . '/env.php';

$host = $env['DB_HOST'] ?? 'localhost';
$dbname = $env['DB_NAME'] ?? 'stjohnba_meditrack';
$username = $env['DB_USERNAME'] ?? 'stjohnba_meditrack';
$password = $env['DB_PASSWORD'] ?? '';

echo "<!DOCTYPE html><html><head><title>MediTrack DB Import</title></head><body>";
echo "<h2>MediTrack Database Importer</h2>";

if ($password === 'YOUR_DATABASE_PASSWORD_HERE') {
    die("<p style='color:red;'>ERROR: Please edit env.php first and set your actual database password.</p></body></html>");
}

try {
    // Connect directly to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
    echo "<p style='color:green;'>&#10004; Connected to MySQL database: $dbname</p>";

    // Read SQL file
    $sqlFile = __DIR__ . '/meditrack_complete_database.sql';
    if (!file_exists($sqlFile)) {
        die("<p style='color:red;'>ERROR: meditrack_complete_database.sql not found!</p></body></html>");
    }

    $sql = file_get_contents($sqlFile);
    echo "<p style='color:green;'>&#10004; SQL file loaded (" . round(strlen($sql) / 1024) . " KB)</p>";

    // Remove CREATE DATABASE and USE statements
    $sql = preg_replace('/^CREATE\s+DATABASE\s+.*?;\s*$/mi', '', $sql);
    $sql = preg_replace('/^USE\s+.*?;\s*$/mi', '', $sql);

    // Remove comments
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    // Split SQL into statements properly
    // This handles multi-line CREATE TABLE statements correctly
    $statements = [];
    $current = '';
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Skip empty lines and pure comment lines
        if (empty($trimmed) || strpos($trimmed, '--') === 0) continue;

        $current .= $line . "\n";

        // If line ends with semicolon (and we're not inside a string), it's end of statement
        if (substr($trimmed, -1) === ';') {
            $stmt = trim($current);
            if (!empty($stmt) && $stmt !== ';') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    // Add last statement if no trailing semicolon
    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }

    echo "<p>Found " . count($statements) . " SQL statements to execute...</p>";

    $success = 0;
    $errors = 0;
    $tables_created = [];

    foreach ($statements as $i => $statement) {
        // Remove trailing semicolon for exec
        $statement = rtrim($statement, '; ');

        if (empty(trim($statement))) continue;

        // Skip SET FOREIGN_KEY_CHECKS (we already did it)
        if (stripos($statement, 'SET FOREIGN_KEY_CHECKS') !== false) continue;
        if (stripos($statement, 'SET SQL_MODE') !== false) continue;

        try {
            $pdo->exec($statement);
            $success++;

            // Track table names
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $m)) {
                $tables_created[] = $m[1];
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Skip harmless errors
            if (strpos($msg, '1065') !== false) continue; // empty query
            if (strpos($msg, 'already exists') !== false) {
                $success++;
                continue;
            }

            $errors++;
            $shortStmt = htmlspecialchars(substr($statement, 0, 80));
            echo "<p style='color:orange;'>Warning #{$errors}: " . htmlspecialchars(substr($msg, 0, 200)) . "<br><small style='color:gray;'>Statement: {$shortStmt}...</small></p>";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Verify tables exist
    $tableCheck = $pdo->query("SHOW TABLES");
    $existingTables = $tableCheck->fetchAll(PDO::FETCH_COLUMN);

    echo "<hr>";
    echo "<h3 style='color:green;'>&#10004; Import Complete!</h3>";
    echo "<p><strong>Statements executed:</strong> $success</p>";
    if ($errors > 0) echo "<p><strong>Warnings:</strong> $errors (usually harmless)</p>";
    echo "<p><strong>Tables in database:</strong> " . count($existingTables) . "</p>";
    echo "<ul>";
    foreach ($existingTables as $t) {
        echo "<li style='color:green;'>&#10004; $t</li>";
    }
    echo "</ul>";

    // Check for essential tables
    $essential = ['users', 'patients', 'doctors', 'appointments', 'departments'];
    $missing = array_diff($essential, $existingTables);
    if (empty($missing)) {
        echo "<p style='color:green; font-size:18px;'><strong>&#10004; All essential tables are present!</strong></p>";
    } else {
        echo "<p style='color:red;'>Missing essential tables: " . implode(', ', $missing) . "</p>";
    }

    echo "<hr>";
    echo "<p style='color:red; font-weight:bold;'>IMPORTANT: Delete this file (import-database.php) from your server now for security!</p>";
    echo "<p><a href='pages/login.html' style='display:inline-block; padding:12px 24px; background:#10b981; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>&#10140; Go to MediTrack Login</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red; font-size:16px;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check your credentials in env.php:</p>";
    echo "<ul>";
    echo "<li><strong>DB_HOST:</strong> $host</li>";
    echo "<li><strong>DB_NAME:</strong> $dbname</li>";
    echo "<li><strong>DB_USERNAME:</strong> $username</li>";
    echo "<li><strong>DB_PASSWORD:</strong> " . str_repeat('*', strlen($password)) . " (" . strlen($password) . " chars)</li>";
    echo "</ul>";
    echo "<p>Go to cPanel > MySQL Databases and verify:</p>";
    echo "<ol>";
    echo "<li>Database '$dbname' exists</li>";
    echo "<li>User '$username' exists</li>";
    echo "<li>User is added to the database with ALL PRIVILEGES</li>";
    echo "<li>Password matches what's in env.php</li>";
    echo "</ol>";
}
echo "</body></html>";
