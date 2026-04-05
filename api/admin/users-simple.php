<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once '../../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get role filter if provided
    $role = isset($_GET['role']) && $_GET['role'] !== '' ? $_GET['role'] : null;
    
    $sql = "SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.role,
                u.status,
                u.phone,
                u.profile_picture,
                u.last_login,
                u.created_at,
                d.specialization,
                d.bio,
                d.profile_image
            FROM users u
            LEFT JOIN doctors d ON u.id = d.user_id AND u.role = 'doctor'
            WHERE 1=1";
    
    if ($role) {
        $sql .= " AND u.role = ?";
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($sql);
    
    if ($role) {
        $stmt->execute([$role]);
    } else {
        $stmt->execute();
    }
    
    $users = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'email' => $row['email'] ?: 'No email',
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'role' => $row['role'],
            'status' => $row['status'] ?: 'active',
            'phone' => $row['phone'],
            'profile_picture' => $row['profile_picture'] ?: $row['profile_image'],
            'specialization' => $row['specialization'],
            'bio' => $row['bio'],
            'lastLogin' => $row['last_login'] ? date('M d, Y g:i A', strtotime($row['last_login'])) : 'Never',
            'fullName' => trim(($row['first_name'] ?: '') . ' ' . ($row['middle_name'] ?: '') . ' ' . ($row['last_name'] ?: '')) ?: $row['username']
        ];
    }
    
    // Clear any output buffer
    ob_end_clean();
    
    echo json_encode($users, JSON_PRETTY_PRINT);
    
} catch(Exception $e) {
    // Clear any output buffer
    ob_end_clean();
    
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
