<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (getCurrentUserRole() !== 'admin') {
    sendJSON(['success' => false, 'message' => 'Access denied'], 403);
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $whereClause = '';
    $params = [];
    
    if ($role) {
        $whereClause = 'WHERE u.role = :role';
        $params[':role'] = $role;
    }

    $query = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, u.last_login,
              CASE 
                WHEN u.role = 'patient' THEN p.full_name
                WHEN u.role = 'doctor' THEN d.full_name
                ELSE u.username
              END as full_name
              FROM users u
              LEFT JOIN patients p ON u.id = p.user_id AND u.role = 'patient'
              LEFT JOIN doctors d ON u.id = d.user_id AND u.role = 'doctor'
              $whereClause
              ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    sendJSON([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
