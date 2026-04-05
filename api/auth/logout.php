<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    if (isLoggedIn()) {
        $database = new Database();
        $db = $database->getConnection();
        logActivity(
            $db,
            $_SESSION['user_id'],
            $_SESSION['username'] ?? '',
            $_SESSION['role'] ?? '',
            'LOGOUT',
            'Auth',
            $_SESSION['user_id'],
            "User logged out"
        );
    }
} catch (Exception $e) {
    error_log("Logout log error: " . $e->getMessage());
}

session_destroy();
sendJSON(['success' => true, 'message' => 'Logged out successfully']);
