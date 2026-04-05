<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Username and password are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT u.*, 
              CASE 
                WHEN u.role = 'patient' THEN p.full_name
                WHEN u.role = 'doctor' THEN d.full_name
                ELSE u.username
              END as full_name,
              CASE 
                WHEN u.role = 'patient' THEN p.id
                WHEN u.role = 'doctor' THEN d.id
                ELSE NULL
              END as profile_id
              FROM users u
              LEFT JOIN patients p ON p.user_id = u.id AND u.role = 'patient'
              LEFT JOIN doctors d ON d.user_id = u.id AND u.role = 'doctor'
              WHERE u.username = :username AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        sendJSON(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    $user = $stmt->fetch();

    if (!password_verify($password, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    // Update last login
    $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':id', $user['id']);
    $updateStmt->execute();

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['profile_id'] = $user['profile_id'];
    $_SESSION['email'] = $user['email'];

    // Log audit
    logAudit($db, $user['id'], 'login', 'users', $user['id'], 'User logged in');

    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'profile_id' => $user['profile_id']
        ]
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
