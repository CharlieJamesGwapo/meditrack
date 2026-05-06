<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('admin') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT mc.*,
               p.full_name AS patient_name, p.date_of_birth, p.gender, p.address, p.contact_number AS patient_contact,
               d.full_name AS doctor_name, d.license_number, d.specialization,
               u.username AS issued_by_username,
               sp.full_name AS issued_by_full_name,
               a.appointment_number, a.appointment_date
          FROM medical_certificates mc
          JOIN patients p ON p.id = mc.patient_id
          JOIN doctors d ON d.id = mc.doctor_id
          JOIN users u ON u.id = mc.issued_by_user_id
     LEFT JOIN staff_profiles sp ON sp.user_id = u.id
          JOIN appointments a ON a.id = mc.appointment_id
         WHERE mc.appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        sendJSON(['success' => false, 'message' => 'Certificate not found'], 404);
    }
    sendJSON(['success' => true, 'certificate' => $row]);
} catch (Exception $e) {
    error_log("staff/certificate error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load certificate'], 500);
}
