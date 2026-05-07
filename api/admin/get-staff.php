<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("
        SELECT u.id AS user_id, u.email, u.username, u.status, u.last_login, u.created_at,
               s.id AS staff_id, s.full_name, s.contact_number
          FROM users u
          JOIN staff_profiles s ON s.user_id = u.id
         WHERE u.role = 'staff'
         ORDER BY u.created_at DESC
    ");
    sendJSON(['success' => true, 'staff' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("get-staff error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load staff list'], 500);
}
