<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, role, is_active, profile_id, created_at FROM users ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
