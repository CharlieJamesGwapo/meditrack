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
$contact_number = sanitizeInput($input['contact_number'] ?? '');

if (empty($full_name) || empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Full name, email, and password are required'], 400);
}
if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}
if (!filter_var(html_entity_decode($email), FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email address'], 400);
}

if (empty($username)) {
    $username = strtolower(explode('@', html_entity_decode($email))[0]);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => html_entity_decode($email)]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'A user with this email already exists'], 409);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        $username = $username . '_' . rand(100, 999);
    }

    $db->beginTransaction();

    $password_hash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $stmt = $db->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (:email, :username, :hash, 'staff', 'active')");
    $stmt->execute([
        ':email'    => html_entity_decode($email),
        ':username' => $username,
        ':hash'     => $password_hash
    ]);
    $user_id = (int) $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, full_name, contact_number) VALUES (:uid, :name, :contact)");
    $stmt->execute([
        ':uid'     => $user_id,
        ':name'    => $full_name,
        ':contact' => $contact_number ?: null
    ]);
    $staff_id = (int) $db->lastInsertId();

    $db->commit();

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'CREATE', 'Staff', $staff_id, "Added staff: $full_name ($email)");

    sendJSON([
        'success'  => true,
        'message'  => 'Staff member added successfully',
        'staff_id' => $staff_id,
        'user_id'  => $user_id,
        'username' => $username,
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("add-staff error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to add staff member. Please try again.'], 500);
}
