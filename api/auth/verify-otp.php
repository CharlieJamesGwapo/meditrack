<?php
/**
 * Verify OTP for Password Reset
 * Validates the OTP code sent to user's email
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || empty($input['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    if (!isset($input['otp']) || empty($input['otp'])) {
        echo json_encode(['success' => false, 'message' => 'OTP is required']);
        exit;
    }
    
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $otp = trim($input['otp']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format']);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if OTP exists and is valid
    $query = "SELECT id, email, otp, reset_token, expires_at, verified 
              FROM password_resets 
              WHERE email = :email 
              AND otp = :otp 
              AND verified = 0
              ORDER BY created_at DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':otp', $otp);
    $stmt->execute();
    
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resetRequest) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        exit;
    }
    
    // Check if OTP has expired
    $currentTime = date('Y-m-d H:i:s');
    if ($currentTime > $resetRequest['expires_at']) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }
    
    // Mark OTP as verified
    $updateQuery = "UPDATE password_resets 
                    SET verified = 1, verified_at = NOW() 
                    WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':id', $resetRequest['id']);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to verify OTP');
    }
    
    // Get user ID for audit log
    $userQuery = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $userStmt = $db->prepare($userQuery);
    $userStmt->bindParam(':email', $email);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Log the activity
        logAudit($db, $user['id'], 'otp_verified', 'password_resets', $resetRequest['id'], 'OTP verified successfully');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully',
        'reset_token' => $resetRequest['reset_token']
    ]);
    
} catch (Exception $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>
