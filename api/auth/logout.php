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
    
    // Destroy session
    session_destroy();
    
    sendJSON(['success' => true, 'message' => 'Logged out successfully']);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
