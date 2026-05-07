<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = getCurrentUserRole();

    $stmt = $db->prepare("
        SELECT r.*,
               a.appointment_number, a.appointment_date,
               p.user_id AS patient_user_id,
               p.full_name AS patient_name, p.date_of_birth, p.gender, p.address, p.contact_number AS patient_contact,
               d.user_id AS doctor_user_id,
               d.full_name AS doctor_name, d.license_number, d.specialization
          FROM referrals r
          JOIN appointments a ON a.id = r.appointment_id
          JOIN patients p ON p.id = r.patient_id
          JOIN doctors d ON d.id = r.referring_doctor_id
         WHERE r.appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        sendJSON(['success' => false, 'message' => 'Referral not found'], 404);
    }

    $authorized = in_array($role, ['staff','admin'], true)
               || ($role === 'doctor'  && (int) $row['doctor_user_id']  === (int) $userId)
               || ($role === 'patient' && (int) $row['patient_user_id'] === (int) $userId);
    if (!$authorized) {
        sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    unset($row['patient_user_id'], $row['doctor_user_id']);

    sendJSON(['success' => true, 'referral' => $row]);
} catch (Exception $e) {
    error_log("staff/referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load referral'], 500);
}
