<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get doctor ID from session
    $doctor_id = 3; // Replace with actual session doctor ID
    
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        throw new Exception('Current password and new password are required');
    }
    
    // Verify current password
    $sql = "SELECT password_hash FROM users WHERE id = :doctor_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':doctor_id' => $doctor_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($current_password, $user['password_hash'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Update password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :doctor_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':password_hash' => $new_password_hash,
        ':doctor_id' => $doctor_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
