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
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => true, 'appointments' => []]);
    }
    $patient_id = $patient['id'];

    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, a.checked_in_at, a.completed_at, a.cancelled_at, a.created_at,
               a.is_followup, a.parent_appointment_id,
               d.full_name as doctor_name, d.specialization,
               qt.token_hash, qt.expires_at as qr_expires_at, qt.is_used as qr_used
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id
        WHERE a.patient_id = :pid
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([':pid' => $patient_id]);
    $appointments = $stmt->fetchAll();

    sendJSON(['success' => true, 'appointments' => $appointments]);

} catch (Exception $e) {
    error_log("get-appointments (patient) error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load appointments'], 500);
}
