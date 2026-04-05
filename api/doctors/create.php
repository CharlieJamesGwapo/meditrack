<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get form data
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
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($specialization)) {
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
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $db->beginTransaction();
    
    // Insert into users table
    $sql = "INSERT INTO users (username, email, password_hash, first_name, middle_name, last_name, role, status, phone, profile_picture) 
            VALUES (:username, :email, :password_hash, :first_name, :middle_name, :last_name, 'doctor', :status, :phone, :profile_picture)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':first_name' => $first_name,
        ':middle_name' => $middle_name,
        ':last_name' => $last_name,
        ':status' => $status,
        ':phone' => $phone,
        ':profile_picture' => $profile_picture
    ]);
    
    $user_id = $db->lastInsertId();
    
    // Insert into doctors table
    $sql = "INSERT INTO doctors (user_id, first_name, middle_name, last_name, full_name, specialization, qualification, license_number, department_id, contact_number, email, consultation_fee, experience_years, bio, status, profile_image) 
            VALUES (:user_id, :first_name, :middle_name, :last_name, :full_name, :specialization, :qualification, :license_number, :department_id, :phone, :email, :consultation_fee, :experience_years, :bio, :status, :profile_image)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
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
        ':profile_image' => $profile_picture
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Send email notification
    $emailSent = false;
    $emailMessage = '';
    
    try {
        require_once '../../config/email.php';
        
        // Get department name
        $deptQuery = "SELECT name FROM departments WHERE id = :dept_id";
        $deptStmt = $db->prepare($deptQuery);
        $deptStmt->execute([':dept_id' => $department_id]);
        $deptResult = $deptStmt->fetch(PDO::FETCH_ASSOC);
        $departmentName = $deptResult ? $deptResult['name'] : 'Not assigned';
        
        // Prepare email data
        $emailData = [
            'full_name' => $full_name,
            'email' => $email,
            'username' => $username,
            'password' => $password,  // Plain password for email only
            'department' => $departmentName,
            'specialization' => $specialization
        ];
        
        // Send email
        $emailResult = EmailConfig::sendDoctorAccountEmail($emailData);
        $emailSent = $emailResult['success'];
        $emailMessage = $emailResult['message'];
        
    } catch (Exception $e) {
        error_log("Email notification error: " . $e->getMessage());
        $emailMessage = 'Email notification failed';
    }
    
    // Clear any output buffer content
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Doctor created successfully',
        'doctor_id' => $user_id,
        'email_sent' => $emailSent,
        'email_message' => $emailMessage
    ]);
    
    ob_end_flush();
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Delete uploaded file if exists
    if (isset($profile_picture) && file_exists('../../uploads/' . $profile_picture)) {
        unlink('../../uploads/' . $profile_picture);
    }
    
    // Clear any output buffer content
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    ob_end_flush();
    exit();
}
?>
