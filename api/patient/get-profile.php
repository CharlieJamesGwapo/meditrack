<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];

    // Get user and patient data
    $query = "SELECT 
                u.id as user_id,
                u.username,
                u.email,
                u.phone,
                u.profile_picture,
                p.id as patient_id,
                p.full_name,
                p.date_of_birth,
                p.gender,
                p.contact_number,
                p.address,
                p.region,
                p.province,
                p.city,
                p.zip_code,
                p.blood_group,
                p.allergies,
                p.medical_history,
                p.emergency_contact_name,
                p.emergency_contact_number,
                p.profile_image,
                p.profile_image_path
              FROM users u
              LEFT JOIN patients p ON u.id = p.user_id
              WHERE u.id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        sendJSON(['success' => false, 'message' => 'Profile not found'], 404);
    }

    // Format profile image URL
    if ($profile['profile_image_path']) {
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $profile['profile_image_url'] = $baseUrl . '/' . $profile['profile_image_path'];
    } else {
        $profile['profile_image_url'] = null;
    }

    sendJSON([
        'success' => true,
        'profile' => $profile,
        'patient' => $profile
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
