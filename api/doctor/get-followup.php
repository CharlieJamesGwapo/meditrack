<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$parent_appointment_id = (int) ($_GET['parent_appointment_id'] ?? 0);
if (!$parent_appointment_id) {
    sendJSON(['success' => false, 'message' => 'parent_appointment_id is required'], 400);
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
        SELECT id, appointment_number, appointment_date, appointment_time, status, reason_for_visit
          FROM appointments
         WHERE parent_appointment_id = :pid
           AND is_followup = 1
           AND doctor_id = :did
           AND status IN ('scheduled','checked_in','in_progress')
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_appointment_id, ':did' => $doctor_id]);
    sendJSON(['success' => true, 'followup' => $stmt->fetch() ?: null]);
} catch (Exception $e) {
    error_log("doctor/get-followup error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load follow-up'], 500);
}
