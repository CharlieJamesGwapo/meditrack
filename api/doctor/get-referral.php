<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("
        SELECT r.* FROM referrals r
          JOIN appointments a ON a.id = r.appointment_id
         WHERE r.appointment_id = :aid
           AND a.doctor_id = :did
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id, ':did' => $doctor_id]);
    sendJSON(['success' => true, 'referral' => $stmt->fetch() ?: null]);
} catch (Exception $e) {
    error_log("doctor/get-referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load referral'], 500);
}
