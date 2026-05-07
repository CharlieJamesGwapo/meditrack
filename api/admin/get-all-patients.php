<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('admin') || hasRole('staff'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT p.id as patient_id, p.full_name, p.date_of_birth, p.gender, p.contact_number,
               p.address, p.region, p.city, p.barangay, p.blood_group, p.allergies,
               p.emergency_contact_name, p.emergency_contact_number, p.created_at,
               u.id as user_id, u.email, u.username, u.status,
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as total_appointments
        FROM patients p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $patients = $stmt->fetchAll();

    sendJSON(['success' => true, 'patients' => $patients, 'total' => count($patients)]);

} catch (Exception $e) {
    error_log("admin get-all-patients error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load patients'], 500);
}
