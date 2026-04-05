<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$full_name                = sanitizeInput($input['full_name'] ?? '');
$email                    = sanitizeInput($input['email'] ?? '');
$username                 = sanitizeInput($input['username'] ?? '');
$password                 = $input['password'] ?? '';
$date_of_birth            = sanitizeInput($input['date_of_birth'] ?? '');
$gender                   = sanitizeInput($input['gender'] ?? '');
$contact_number           = sanitizeInput($input['contact_number'] ?? '');
$address                  = sanitizeInput($input['address'] ?? '');
$region                   = sanitizeInput($input['region'] ?? '');
$city                     = sanitizeInput($input['city'] ?? '');
$barangay                 = sanitizeInput($input['barangay'] ?? '');
$blood_group              = sanitizeInput($input['blood_group'] ?? '');
$allergies                = sanitizeInput($input['allergies'] ?? '');
$emergency_contact_name   = sanitizeInput($input['emergency_contact_name'] ?? '');
$emergency_contact_number = sanitizeInput($input['emergency_contact_number'] ?? '');

if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Full name, email, username, and password are required'], 400);
}
if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email address'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'Email already registered'], 409);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'Username already taken'], 409);
    }

    $db->beginTransaction();

    $password_hash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);

    $stmt = $db->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (:email, :username, :password_hash, 'patient', 'active')");
    $stmt->execute([
        ':email'         => $email,
        ':username'      => $username,
        ':password_hash' => $password_hash
    ]);
    $user_id = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO patients (user_id, full_name, date_of_birth, gender, contact_number, address, region, city, barangay, blood_group, allergies, emergency_contact_name, emergency_contact_number)
        VALUES (:user_id, :full_name, :dob, :gender, :contact, :address, :region, :city, :barangay, :blood_group, :allergies, :ec_name, :ec_number)");
    $stmt->execute([
        ':user_id'    => $user_id,
        ':full_name'  => $full_name,
        ':dob'        => $date_of_birth ?: null,
        ':gender'     => $gender ?: null,
        ':contact'    => $contact_number ?: null,
        ':address'    => $address ?: null,
        ':region'     => $region ?: null,
        ':city'       => $city ?: null,
        ':barangay'   => $barangay ?: null,
        ':blood_group'=> $blood_group ?: null,
        ':allergies'  => $allergies ?: null,
        ':ec_name'    => $emergency_contact_name ?: null,
        ':ec_number'  => $emergency_contact_number ?: null
    ]);
    $patient_id = $db->lastInsertId();

    $db->commit();

    logActivity($db, $user_id, $username, 'patient', 'CREATE', 'Auth', $user_id, "New patient registered: $full_name");

    sendJSON([
        'success'    => true,
        'message'    => 'Registration successful',
        'user_id'    => $user_id,
        'patient_id' => $patient_id
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("Register error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
}
