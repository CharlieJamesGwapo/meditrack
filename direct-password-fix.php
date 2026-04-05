<?php
require_once 'config/database.php';
require_once 'config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // First, let's test what password_verify does with the current hash
    $testPassword = 'admin123';
    
    // Get current admin user
    $currentQuery = "SELECT password_hash FROM users WHERE username = 'admin'";
    $currentStmt = $db->prepare($currentQuery);
    $currentStmt->execute();
    $currentUser = $currentStmt->fetch();
    
    if ($currentUser) {
        $currentHash = $currentUser['password_hash'];
        $currentTest = password_verify($testPassword, $currentHash);
        
        // Generate a new proper hash
        $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
        $newTest = password_verify($testPassword, $newHash);
        
        // Update with the new hash
        $updateQuery = "UPDATE users SET password_hash = :new_hash WHERE username = 'admin'";
        $updateStmt = $db->prepare($updateQuery);
        $updateResult = $updateStmt->execute([':new_hash' => $newHash]);
        
        // Verify the update
        $verifyQuery = "SELECT password_hash FROM users WHERE username = 'admin'";
        $verifyStmt = $db->prepare($verifyQuery);
        $verifyStmt->execute();
        $updatedUser = $verifyStmt->fetch();
        $finalTest = password_verify($testPassword, $updatedUser['password_hash']);
        
        sendJSON([
            'success' => true,
            'message' => 'Admin password fixed successfully',
            'details' => [
                'password' => $testPassword,
                'old_hash_test' => $currentTest ? 'passed' : 'failed',
                'new_hash_test' => $newTest ? 'passed' : 'failed',
                'final_test' => $finalTest ? 'passed' : 'failed',
                'update_success' => $updateResult,
                'old_hash_length' => strlen($currentHash),
                'new_hash_length' => strlen($newHash)
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
