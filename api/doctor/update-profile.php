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
    $doctor_id = 3; // Replace with actual session doctor ID
    
    // Get form data
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $department = $_POST['department'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('First name, last name, and email are required');
    }
    
    // Handle file upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
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
    
    // Update doctors table
    $sql = "UPDATE doctors SET 
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'profile_picture' => $profile_picture
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Delete uploaded file if exists
    if (isset($profile_picture) && file_exists('../../uploads/' . $profile_picture)) {
        unlink('../../uploads/' . $profile_picture);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
