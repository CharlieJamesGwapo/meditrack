<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if users table exists and count users
    $countQuery = "SELECT COUNT(*) as count FROM users";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute();
    $userCount = $countStmt->fetch()['count'];
    
    // Check if admin exists
    $adminQuery = "SELECT COUNT(*) as count FROM users WHERE username = 'admin'";
    $adminStmt = $db->prepare($adminQuery);
    $adminStmt->execute();
    $adminExists = $adminStmt->fetch()['count'] > 0;
    
    sendJSON([
        'success' => true,
        'user_count' => $userCount,
        'admin_exists' => $adminExists,
        'database' => 'connected'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
