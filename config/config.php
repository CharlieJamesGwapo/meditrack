<?php
/**
 * Application Configuration
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Application settings
define('APP_NAME', 'MediTrack');
define('APP_URL', 'http://localhost/meditrack');
define('APP_VERSION', '1.0.0');

// Security settings
define('SECRET_KEY', 'your-secret-key-change-this-in-production');
define('JWT_SECRET', 'your-jwt-secret-change-this-in-production');
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);

// QR Code settings
define('QR_EXPIRY_HOURS', 24);
define('QR_SIZE', 300);

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Email settings (Gmail SMTP configuration)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'pforcapstone@gmail.com'); // Full Gmail address
define('SMTP_PASSWORD', 'rtegcvlllmtaxnin'); // App password from Google (no spaces)
define('SMTP_FROM_EMAIL', 'pforcapstone@gmail.com');
define('SMTP_FROM_NAME', 'MediTrack Hospital System');

// Pagination
define('ITEMS_PER_PAGE', 20);

// CORS settings
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Helper function to get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Helper function to get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function to send JSON response
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Helper function to log audit
function logAudit($db, $userId, $action, $targetTable = null, $targetId = null, $description = null) {
    try {
        $query = "INSERT INTO audit_logs (user_id, action, target_table, target_id, description, ip_address, user_agent) 
                  VALUES (:user_id, :action, :target_table, :target_id, :description, :ip_address, :user_agent)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':target_table' => $targetTable,
            ':target_id' => $targetId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
