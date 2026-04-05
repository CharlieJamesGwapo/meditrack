<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../utils/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Get data from FormData (not JSON)
$data = $_POST;

// Validate required fields
$required = ['username', 'email', 'password', 'full_name', 'date_of_birth', 'gender', 'contact_number', 'region', 'province', 'city'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendJSON(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
    }
}

$username = sanitizeInput($data['username']);
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$password = $data['password'];
$full_name = sanitizeInput($data['full_name']);
$date_of_birth = $data['date_of_birth'];
$gender = $data['gender'];
$contact_number = sanitizeInput($data['contact_number']);
$address = sanitizeInput($data['address'] ?? '');
$barangay = sanitizeInput($data['barangay'] ?? '');
$region = sanitizeInput($data['region'] ?? '');
$province = sanitizeInput($data['province'] ?? '');
$city = sanitizeInput($data['city'] ?? '');
$zip_code = sanitizeInput($data['zip_code'] ?? '');
$blood_group = sanitizeInput($data['blood_group'] ?? '');
$allergies = sanitizeInput($data['allergies'] ?? '');
$emergency_contact_name = sanitizeInput($data['emergency_contact_name'] ?? '');
$emergency_contact_number = sanitizeInput($data['emergency_contact_number'] ?? '');

// Handle profile picture upload
$profile_image = null;
$profile_image_path = null;

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = 'jpg'; // Always save as JPG since we're converting
    $filename = 'patient_' . time() . '_' . uniqid() . '_2x2.' . $file_extension;
    $upload_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
        $profile_image = $filename;
        $profile_image_path = 'uploads/' . $filename;
    }
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email format'], 400);
}

// Validate password strength
if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if username or email already exists
    $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':username' => $username, ':email' => $email]);
    
    if ($checkStmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'Username or email already exists'], 409);
    }

    // Start transaction
    $db->beginTransaction();

    // Insert user
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    // Split full_name into first/last for users table
    $nameParts = explode(' ', $full_name, 2);
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? '';

    $userQuery = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, phone, profile_picture)
                  VALUES (:username, :email, :password_hash, :first_name, :last_name, 'patient', :phone, :profile_picture)";
    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':phone' => $contact_number,
        ':profile_picture' => $profile_image
    ]);
    
    $user_id = $db->lastInsertId();

    // Insert patient details
    $patientQuery = "INSERT INTO patients (user_id, full_name, date_of_birth, gender, contact_number, email, address, barangay, region, province, city, zip_code, blood_group, allergies, emergency_contact_name, emergency_contact_number, profile_image, profile_image_path) 
                     VALUES (:user_id, :full_name, :date_of_birth, :gender, :contact_number, :email, :address, :barangay, :region, :province, :city, :zip_code, :blood_group, :allergies, :emergency_contact_name, :emergency_contact_number, :profile_image, :profile_image_path)";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->execute([
        ':user_id' => $user_id,
        ':full_name' => $full_name,
        ':date_of_birth' => $date_of_birth,
        ':gender' => $gender,
        ':contact_number' => $contact_number,
        ':email' => $email,
        ':address' => $address,
        ':barangay' => $barangay,
        ':region' => $region,
        ':province' => $province,
        ':city' => $city,
        ':zip_code' => $zip_code,
        ':blood_group' => $blood_group,
        ':allergies' => $allergies,
        ':emergency_contact_name' => $emergency_contact_name,
        ':emergency_contact_number' => $emergency_contact_number,
        ':profile_image' => $profile_image,
        ':profile_image_path' => $profile_image_path
    ]);

    $patient_id = $db->lastInsertId();

    // Commit transaction
    $db->commit();

    // Log audit
    logAudit($db, $user_id, 'register', 'users', $user_id, 'New patient registered');

    // Send welcome email notification
    try {
        $emailService = new EmailService();
        $emailSent = $emailService->sendRegistrationEmail($email, $full_name, $username);
        
        if ($emailSent) {
            error_log("Registration email sent successfully to: {$email}");
        } else {
            error_log("Failed to send registration email to: {$email}");
        }
    } catch (Exception $emailError) {
        // Log error but don't fail registration
        error_log("Email service error: " . $emailError->getMessage());
    }

    // NO AUTO-LOGIN - User must login manually from homepage
    sendJSON([
        'success' => true,
        'message' => 'Registration successful! A confirmation email has been sent to your email address.',
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
            'role' => 'patient',
            'full_name' => $full_name,
            'profile_id' => $patient_id,
            'profile_image' => $profile_image_path
        ]
    ]);

} catch (Exception $e) {
    if (isset($db) && $db && $db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
