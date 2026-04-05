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
    
    // Get form data
    $id = $_POST['id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $specialization = $_POST['specialization'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $consultation_fee = $_POST['consultation_fee'] ?? 0;
    $experience_years = $_POST['experience_years'] ?? 0;
    $phone = $_POST['phone'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $bio = $_POST['bio'] ?? '';
    
    // Validate required fields
    if (empty($id) || empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($specialization)) {
        throw new Exception('Please fill all required fields');
    }
    
    // Build full name
    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    
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
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = :username, email = :email, password_hash = :password_hash, 
                first_name = :first_name, middle_name = :middle_name, last_name = :last_name, 
                status = :status, phone = :phone" . 
                ($profile_picture ? ", profile_picture = :profile_picture" : "") . 
                " WHERE id = :id";
    } else {
        $sql = "UPDATE users SET username = :username, email = :email, 
                first_name = :first_name, middle_name = :middle_name, last_name = :last_name, 
                status = :status, phone = :phone" . 
                ($profile_picture ? ", profile_picture = :profile_picture" : "") . 
                " WHERE id = :id";
    }
    
    $stmt = $db->prepare($sql);
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':status' => $status,
        ':phone' => $phone,
        ':id' => $id
    ];
    
    if (!empty($password)) {
        $params[':password_hash'] = $password_hash;
    }
    
    if ($profile_picture) {
        $params[':profile_picture'] = $profile_picture;
    }
    
    $stmt->execute($params);
    
    // Update doctors table
    $sql = "UPDATE doctors SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name, 
            full_name = :full_name, specialization = :specialization, qualification = :qualification, 
            license_number = :license_number, department_id = :department_id, contact_number = :phone, 
            email = :email, consultation_fee = :consultation_fee, experience_years = :experience_years, 
            bio = :bio, status = :status" . 
            ($profile_picture ? ", profile_image = :profile_image" : "") . 
            " WHERE user_id = :user_id";
    
    $stmt = $db->prepare($sql);
    $params = [
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':full_name' => $full_name,
        ':specialization' => $specialization,
        ':qualification' => $qualification,
        ':license_number' => $license_number,
        ':department_id' => $department_id,
        ':phone' => $phone,
        ':email' => $email,
        ':consultation_fee' => $consultation_fee,
        ':experience_years' => $experience_years,
        ':bio' => $bio,
        ':status' => $status,
        ':user_id' => $id
    ];
    
    if ($profile_picture) {
        $params[':profile_image'] = $profile_picture;
    }
    
    $stmt->execute($params);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Doctor updated successfully'
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
