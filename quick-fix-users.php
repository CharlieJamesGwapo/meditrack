<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Delete existing admin user (if any)
    $deleteQuery = "DELETE FROM users WHERE username = 'admin'";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->execute();
    
    // Create new admin user with fresh password hash
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $insertQuery = "INSERT INTO users (username, email, password_hash, role, is_active, profile_id, created_at) 
                   VALUES (:username, :email, :password_hash, :role, :is_active, :profile_id, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@meditrack.com',
        ':password_hash' => $passwordHash,
        ':role' => 'admin',
        ':is_active' => 1,
        ':profile_id' => null
    ]);
    
    if ($result) {
        // Verify the user was created
        $verifyQuery = "SELECT * FROM users WHERE username = 'admin'";
        $verifyStmt = $db->prepare($verifyQuery);
        $verifyStmt->execute();
        $user = $verifyStmt->fetch();
        
        if ($user) {
            sendJSON([
                'success' => true,
                'message' => 'Admin user created successfully',
                'user_id' => $user['id'],
                'username' => $user['username'],
                'password_hash_length' => strlen($user['password_hash'])
            ]);
        } else {
            sendJSON([
                'success' => false,
                'message' => 'Failed to verify user creation'
            ]);
        }
    } else {
        sendJSON([
            'success' => false,
            'message' => 'Failed to create admin user'
        ]);
    }
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
