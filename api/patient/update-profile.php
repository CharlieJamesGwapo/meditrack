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

    // Get data from POST or JSON body
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
    } else {
        $data = $_POST;
    }

    // For partial updates, fetch existing data to fill in missing required fields
    $required = ['full_name', 'date_of_birth', 'gender', 'contact_number'];
    $missingRequired = false;
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missingRequired = true;
            break;
        }
    }
    if ($missingRequired) {
        $existingStmt = $db->prepare("SELECT p.full_name, p.date_of_birth, p.gender, p.contact_number, p.address, p.region, p.province, p.city, p.zip_code, p.blood_group, p.allergies, p.medical_history, p.emergency_contact_name, p.emergency_contact_number FROM patients p WHERE p.user_id = :uid");
        $existingStmt->execute([':uid' => $userId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            foreach ($existing as $key => $val) {
                if (!isset($data[$key]) || $data[$key] === '') {
                    $data[$key] = $val;
                }
            }
        }
        // Re-validate after merge
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendJSON(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
            }
        }
    }

    // Handle profile picture upload
    $profile_image = null;
    $profile_image_path = null;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/';
        
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
                    email = :email,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :user_id";

    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([
        ':phone' => sanitizeInput($data['contact_number']),
        ':email' => sanitizeInput($data['email'] ?? ''),
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
        'profile_image_url' => $profile_image_path ? (defined('APP_URL') ? APP_URL : '') . '/' . $profile_image_path : null
    ]);

} catch (Exception $e) {
    if (isset($db) && $db && $db->inTransaction()) {
        $db->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
