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
    
    // Get doctor ID from session (you should implement proper session handling)
    // For now, we'll use a default doctor ID
    $doctor_id = 3; // Replace with actual session doctor ID
    
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
                'profile_picture' => $doctor['profile_picture'] ?: $doctor['profile_image'],
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
