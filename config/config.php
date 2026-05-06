<?php
/**
 * Internal Medicine OPD Management System — Application Configuration
 * Single Doctor System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$env = require __DIR__ . '/../env.php';

if (($env['ENVIRONMENT'] ?? 'production') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'Internal Medicine OPD');
define('APP_URL', $env['APP_URL'] ?? 'http://localhost/meditrack');
define('APP_VERSION', '2.0.0');

if (!defined('SECRET_KEY')) {
    $envKey = getenv('MEDITRACK_SECRET_KEY');
    define('SECRET_KEY', $envKey ?: 'mt-' . md5(__DIR__ . php_uname('n')));
}
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);

define('QR_EXPIRY_HOURS', 24);
define('QR_SIZE', 300);

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

define('ITEMS_PER_PAGE', 20);
define('CANCEL_BROADCAST_LIMIT', 20);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function logActivity($db, $userId, $username, $role, $actionType, $module, $recordId, $description) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, user_role, action_type, module, record_id, description, ip_address) VALUES (:user_id, :username, :user_role, :action_type, :module, :record_id, :description, :ip_address)");
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':user_role' => $role,
            ':action_type' => $actionType,
            ':module' => $module,
            ':record_id' => $recordId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
