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

    $stmt = $db->prepare("
        SELECT d.id, d.user_id, d.full_name, d.specialization, d.license_number,
               d.consultation_fee, d.experience_years, d.bio, d.profile_picture, d.status,
               d.created_at, d.updated_at,
               u.email, u.username, u.last_login,
               (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.status NOT IN ('cancelled','no_show')) as total_appointments,
               (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.status = 'completed') as completed_appointments,
               (SELECT COUNT(*) FROM doctor_schedules ds WHERE ds.doctor_id = d.id AND ds.is_active = 1) as active_schedule_days
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.status ASC, d.full_name ASC
    ");
    $stmt->execute();
    $doctors = $stmt->fetchAll();

    sendJSON(['success' => true, 'doctors' => $doctors, 'count' => count($doctors)]);

} catch (Exception $e) {
    error_log("get-doctors error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load doctors'], 500);
}
