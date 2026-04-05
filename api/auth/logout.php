<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not logged in'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Log audit
    logAudit($db, getCurrentUserId(), 'logout', 'users', getCurrentUserId(), 'User logged out');
    
    // Clear and destroy session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    sendJSON(['success' => true, 'message' => 'Logged out successfully']);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
