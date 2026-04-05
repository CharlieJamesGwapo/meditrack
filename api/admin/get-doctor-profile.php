<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->query("
        SELECT d.id as doctor_id, d.full_name, d.specialization, d.license_number,
               d.consultation_fee, d.experience_years, d.bio, d.status,
               u.id as user_id, u.email, u.username, u.last_login, u.created_at
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'active'
        LIMIT 1
    ");
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No active doctor found'], 404);
    }

    sendJSON(['success' => true, 'doctor' => $doctor]);

} catch (Exception $e) {
    error_log("admin get-doctor-profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load doctor profile'], 500);
}
