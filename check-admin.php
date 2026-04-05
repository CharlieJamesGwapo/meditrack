<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        sendJSON([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => $user['is_active'],
                'password_hash' => $user['password_hash']
            ]
        ]);
    } else {
        sendJSON([
            'success' => false,
            'message' => 'Admin user not found'
        ]);
    }
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
