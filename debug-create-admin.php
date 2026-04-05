<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if admin already exists
    $checkQuery = "SELECT id FROM users WHERE username = 'admin'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        sendJSON([
            'success' => false,
            'message' => 'Admin user already exists'
        ]);
        exit;
    }
    
    // Create admin user
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $insertQuery = "INSERT INTO users (username, email, password_hash, role, is_active, profile_id) 
                   VALUES (:username, :email, :password_hash, :role, :is_active, :profile_id)";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@meditrack.com',
        ':password_hash' => $passwordHash,
        ':role' => 'admin',
        ':is_active' => 1,
        ':profile_id' => null
    ]);
    
    sendJSON([
        'success' => true,
        'message' => 'Admin user created successfully',
        'username' => 'admin',
        'password' => 'admin123'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
