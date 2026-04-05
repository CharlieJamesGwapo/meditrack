<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Get data from POST
    $data = $_POST;

    // Validate required fields
    $required = ['full_name', 'date_of_birth', 'gender', 'contact_number'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendJSON(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
        }
    }

    // Handle profile picture upload
    $profile_image = null;
    $profile_image_path = null;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = 'jpg';
        $filename = 'patient_' . $userId . '_' . time() . '_2x2.' . $file_extension;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
            $profile_image = $filename;
            $profile_image_path = 'uploads/' . $filename;
        }
    }

    // Start transaction
    $db->beginTransaction();

    // Update users table
    $userQuery = "UPDATE users SET 
                    phone = :phone,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :user_id";
    
    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([
        ':phone' => sanitizeInput($data['contact_number']),
        ':user_id' => $userId
    ]);

    // Update patients table
    $patientQuery = "UPDATE patients SET 
                        full_name = :full_name,
                        date_of_birth = :date_of_birth,
                        gender = :gender,
                        contact_number = :contact_number,
                        address = :address,
                        region = :region,
                        province = :province,
                        city = :city,
                        zip_code = :zip_code,
                        blood_group = :blood_group,
                        allergies = :allergies,
                        medical_history = :medical_history,
                        emergency_contact_name = :emergency_contact_name,
                        emergency_contact_number = :emergency_contact_number";
    
    if ($profile_image) {
        $patientQuery .= ", profile_image = :profile_image, profile_image_path = :profile_image_path";
    }
    
    $patientQuery .= ", updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id";
    
    $patientStmt = $db->prepare($patientQuery);
    
    $params = [
        ':full_name' => sanitizeInput($data['full_name']),
        ':date_of_birth' => $data['date_of_birth'],
        ':gender' => $data['gender'],
        ':contact_number' => sanitizeInput($data['contact_number']),
        ':address' => sanitizeInput($data['address'] ?? ''),
        ':region' => sanitizeInput($data['region'] ?? ''),
        ':province' => sanitizeInput($data['province'] ?? ''),
        ':city' => sanitizeInput($data['city'] ?? ''),
        ':zip_code' => sanitizeInput($data['zip_code'] ?? ''),
        ':blood_group' => sanitizeInput($data['blood_group'] ?? ''),
        ':allergies' => sanitizeInput($data['allergies'] ?? ''),
        ':medical_history' => sanitizeInput($data['medical_history'] ?? ''),
        ':emergency_contact_name' => sanitizeInput($data['emergency_contact_name'] ?? ''),
        ':emergency_contact_number' => sanitizeInput($data['emergency_contact_number'] ?? ''),
        ':user_id' => $userId
    ];
    
    if ($profile_image) {
        $params[':profile_image'] = $profile_image;
        $params[':profile_image_path'] = $profile_image_path;
    }
    
    $patientStmt->execute($params);

    // Commit transaction
    $db->commit();

    // Log audit
    logAudit($db, $userId, 'update_profile', 'patients', $userId, 'Patient profile updated');

    sendJSON([
        'success' => true,
        'message' => 'Profile updated successfully',
        'profile_image_url' => $profile_image_path ? '../../' . $profile_image_path : null
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
