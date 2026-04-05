<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
    sendJSON(['success' => false, 'message' => 'All fields are required'], 400);
}

// Validate new password
if ($data['new_password'] !== $data['confirm_password']) {
    sendJSON(['success' => false, 'message' => 'New passwords do not match'], 400);
}

if (strlen($data['new_password']) < 6) {
    sendJSON(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];

    // Get current password hash
    $query = "SELECT password_hash FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendJSON(['success' => false, 'message' => 'User not found'], 404);
    }

    // Verify current password
    if (!password_verify($data['current_password'], $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }

    // Update password
    $new_password_hash = password_hash($data['new_password'], PASSWORD_BCRYPT, ['cost' => 10]);
    
    $updateQuery = "UPDATE users SET 
                      password_hash = :password_hash,
                      updated_at = CURRENT_TIMESTAMP
                    WHERE id = :user_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        ':password_hash' => $new_password_hash,
        ':user_id' => $userId
    ]);

    // Log audit
    logAudit($db, $userId, 'change_password', 'users', $userId, 'Password changed');

    sendJSON([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
