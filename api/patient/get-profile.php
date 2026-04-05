<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT u.id as user_id, u.email, u.username, u.status, u.last_login,
               p.id as patient_id, p.full_name, p.date_of_birth, p.gender,
               p.contact_number, p.address, p.region, p.city, p.barangay,
               p.blood_group, p.allergies, p.emergency_contact_name,
               p.emergency_contact_number, p.profile_picture, p.created_at
        FROM users u
        JOIN patients p ON p.user_id = u.id
        WHERE u.id = :uid
        LIMIT 1
    ");
    $stmt->execute([':uid' => getCurrentUserId()]);
    $profile = $stmt->fetch();

    if (!$profile) {
        sendJSON(['success' => false, 'message' => 'Profile not found'], 404);
    }

    sendJSON(['success' => true, 'profile' => $profile]);

} catch (Exception $e) {
    error_log("get-profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load profile'], 500);
}
