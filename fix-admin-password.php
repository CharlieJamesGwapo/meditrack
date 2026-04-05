<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Generate a proper password hash for admin123
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update the admin user password
    $updateQuery = "UPDATE users SET password_hash = :password_hash WHERE username = 'admin'";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([
        ':password_hash' => $passwordHash
    ]);
    
    if ($result) {
        // Verify the update
        $verifyQuery = "SELECT password_hash FROM users WHERE username = 'admin'";
        $verifyStmt = $db->prepare($verifyQuery);
        $verifyStmt->execute();
        $updatedUser = $verifyStmt->fetch();
        
        // Test the password verification
        $testResult = password_verify($password, $updatedUser['password_hash']);
        
        sendJSON([
            'success' => true,
            'message' => 'Admin password updated successfully',
            'password' => $password,
            'hash_length' => strlen($updatedUser['password_hash']),
            'verification_test' => $testResult ? 'passed' : 'failed'
        ]);
    } else {
        sendJSON([
            'success' => false,
            'message' => 'Failed to update admin password'
        ]);
    }
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
