<?php
/**
 * Database Setup Script
 *
 * DESTRUCTIVE: Drops every table in the configured database and re-imports
 * database/schema.sql. Never expose this without auth.
 *
 * SECURITY:
 *  - Requires SETUP_TOKEN in env.php AND a matching ?token= URL param.
 *  - If env.php has no SETUP_TOKEN, this script refuses to run.
 *  - DELETE THIS FILE from the server as soon as you are done.
 */

$env = require __DIR__ . '/env.php';

$expectedToken = $env['SETUP_TOKEN'] ?? null;
$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$expectedToken || !is_string($expectedToken) || $expectedToken === '') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. setup-db.php is disabled because SETUP_TOKEN is not configured in env.php.\n";
    exit;
}
if (!hash_equals($expectedToken, (string) $providedToken)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Append ?token=YOUR-TOKEN to the URL.\n";
    exit;
}

$host = $env['DB_HOST'] ?? 'localhost';
$dbname = $env['DB_NAME'] ?? 'stjohnba_meditrack';
$username = $env['DB_USERNAME'] ?? 'stjohnba_meditrack';
$password = $env['DB_PASSWORD'] ?? 'Meditrack2026';

echo "<h2>Internal Medicine OPD — Database Setup</h2><pre>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected to database: $dbname\n\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "Foreign key checks disabled.\n";

    // Drop ALL existing tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped: $table\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nAll old tables dropped.\n\n";

    // Read and execute schema.sql
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($sql);
    echo "Schema imported successfully!\n\n";

    // Verify
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables created: " . count($tables) . "\n";
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  - $t ($count rows)\n";
    }

    echo "\n✅ DATABASE SETUP COMPLETE!\n";
    echo "\nAdmin login: admin@meditrack.com / admin123\n";
    echo "Doctor login: doctor@meditrack.com / doctor123\n";
    echo "\n⚠️  DELETE THIS FILE (setup-db.php) NOW!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
