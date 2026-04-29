<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);

$full_name      = sanitizeInput($input['full_name'] ?? '');
$email          = sanitizeInput($input['email'] ?? '');
$username       = sanitizeInput($input['username'] ?? '');
$password       = $input['password'] ?? '';
$specialization = sanitizeInput($input['specialization'] ?? 'Internal Medicine');
$license_number = sanitizeInput($input['license_number'] ?? '');
$consultation_fee = floatval($input['consultation_fee'] ?? 0);
$experience_years = intval($input['experience_years'] ?? 0);
$bio            = sanitizeInput($input['bio'] ?? '');
// profile_picture is the filename returned by upload-doctor-photo.php; null if no photo was uploaded.
$profile_picture = !empty($input['profile_picture']) ? basename(sanitizeInput($input['profile_picture'])) : null;

if (empty($full_name) || empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Full name, email, and password are required'], 400);
}
if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}
if (!filter_var(html_entity_decode($email), FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email address'], 400);
}

// Auto-generate username from email if not provided
if (empty($username)) {
    $username = strtolower(explode('@', html_entity_decode($email))[0]);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check email uniqueness
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => html_entity_decode($email)]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'A user with this email already exists'], 409);
    }

    // Check username uniqueness
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        $username = $username . '_' . rand(100, 999);
    }

    $db->beginTransaction();

    // Create user account
    $password_hash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $stmt = $db->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (:email, :username, :hash, 'doctor', 'active')");
    $stmt->execute([
        ':email'    => html_entity_decode($email),
        ':username' => $username,
        ':hash'     => $password_hash
    ]);
    $user_id = (int) $db->lastInsertId();

    // Create doctor profile
    $stmt = $db->prepare("INSERT INTO doctors (user_id, full_name, specialization, license_number, consultation_fee, experience_years, bio, profile_picture, status) VALUES (:uid, :name, :spec, :license, :fee, :exp, :bio, :pic, 'active')");
    $stmt->execute([
        ':uid'     => $user_id,
        ':name'    => $full_name,
        ':spec'    => $specialization,
        ':license' => $license_number,
        ':fee'     => $consultation_fee,
        ':exp'     => $experience_years,
        ':bio'     => $bio,
        ':pic'     => $profile_picture
    ]);
    $doctor_id = (int) $db->lastInsertId();

    // Create default schedule (Mon-Fri, 8am-5pm)
    $scheduleStmt = $db->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients, is_active) VALUES (:did, :dow, '08:00:00', '17:00:00', 30, 20, :active)");
    for ($day = 0; $day <= 6; $day++) {
        $scheduleStmt->execute([
            ':did'    => $doctor_id,
            ':dow'    => $day,
            ':active' => ($day >= 1 && $day <= 5) ? 1 : 0
        ]);
    }

    $db->commit();

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'CREATE', 'Doctors', $doctor_id, "Added doctor: $full_name ($email)");

    sendJSON([
        'success'   => true,
        'message'   => 'Doctor added successfully',
        'doctor_id' => $doctor_id,
        'user_id'   => $user_id
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("add-doctor error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to add doctor. Please try again.'], 500);
}
