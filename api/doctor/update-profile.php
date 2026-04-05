<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';
require_once '../../config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    if ($_SESSION['role'] !== 'doctor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    $doctor_id = $_SESSION['user_id'];

    // Look up actual doctor record using user_id
    $lookupStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :user_id LIMIT 1");
    $lookupStmt->execute([':user_id' => $doctor_id]);
    $doctorRecord = $lookupStmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctorRecord) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
        exit;
    }
    $doctorProfileId = $doctorRecord['id'];

    // Get form data (support both POST and JSON)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $inputData = json_decode(file_get_contents("php://input"), true) ?? [];
    } else {
        $inputData = $_POST;
    }
    $first_name = $inputData['first_name'] ?? '';
    $middle_name = $inputData['middle_name'] ?? '';
    $last_name = $inputData['last_name'] ?? '';
    $email = $inputData['email'] ?? '';
    $phone = $inputData['phone'] ?? '';
    $specialization = $inputData['specialization'] ?? '';
    $department = $inputData['department'] ?? '';
    $bio = $inputData['bio'] ?? '';
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('First name, last name, and email are required');
    }
    
    // Handle file upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profile_picture = 'doctor_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $profile_picture;
        
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload profile picture');
        }
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Update users table
    $sql = "UPDATE users SET 
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                email = :email,
                phone = :phone" .
                ($profile_picture ? ", profile_picture = :profile_picture" : "") .
            " WHERE id = :doctor_id";
    
    $stmt = $db->prepare($sql);
    $params = [
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':phone' => $phone,
        ':doctor_id' => $doctor_id
    ];
    
    if ($profile_picture) {
        $params[':profile_picture'] = $profile_picture;
    }
    
    $stmt->execute($params);
    
    // Build full_name
    $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);

    // Update doctors table
    $sql = "UPDATE doctors SET
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                full_name = :full_name,
                email = :email,
                contact_number = :contact_number,
                specialization = :specialization,
                department = :department,
                bio = :bio" .
                ($profile_picture ? ", profile_image = :profile_image" : "") .
            " WHERE user_id = :doctor_id";

    $stmt = $db->prepare($sql);
    $params = [
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':full_name' => $full_name,
        ':email' => $email,
        ':contact_number' => $phone,
        ':specialization' => $specialization,
        ':department' => $department,
        ':bio' => $bio,
        ':doctor_id' => $doctor_id
    ];
    
    if ($profile_picture) {
        $params[':profile_image'] = $profile_picture;
    }
    
    $stmt->execute($params);
    
    // Commit transaction
    $db->commit();
    
    $baseUrl = defined('APP_URL') ? APP_URL : '';
    $responseData = [
        'success' => true,
        'message' => 'Profile updated successfully'
    ];
    if ($profile_picture) {
        $responseData['profile_picture'] = $profile_picture;
        $responseData['profile_image_url'] = $baseUrl . '/uploads/' . $profile_picture;
    }
    echo json_encode($responseData);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Delete uploaded file if exists
    if (isset($profile_picture) && file_exists(__DIR__ . '/../../uploads/' . $profile_picture)) {
        unlink(__DIR__ . '/../../uploads/' . $profile_picture);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
