<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';
require_once '../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $doctor_id = $_SESSION['user_id'];
    
    $sql = "SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.phone,
                u.profile_picture,
                d.specialization,
                d.department,
                d.bio,
                d.profile_image
            FROM users u
            LEFT JOIN doctors d ON u.id = d.user_id
            WHERE u.id = :doctor_id AND u.role = 'doctor'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':doctor_id' => $doctor_id]);
    
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $profileImg = $doctor['profile_picture'] ?: $doctor['profile_image'];
        $profileImageUrl = $profileImg ? ($baseUrl . '/uploads/' . $profileImg) : null;

        echo json_encode([
            'success' => true,
            'doctor' => [
                'id' => (int)$doctor['id'],
                'username' => $doctor['username'],
                'email' => $doctor['email'],
                'first_name' => $doctor['first_name'],
                'middle_name' => $doctor['middle_name'],
                'last_name' => $doctor['last_name'],
                'phone' => $doctor['phone'],
                'profile_picture' => $profileImg,
                'profile_image_url' => $profileImageUrl,
                'specialization' => $doctor['specialization'],
                'department' => $doctor['department'],
                'bio' => $doctor['bio']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
