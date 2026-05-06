<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('staff')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT a.id AS appointment_id, a.appointment_number, a.appointment_time, a.checked_in_at,
               p.id AS patient_id, p.full_name AS patient_name, p.date_of_birth, p.gender,
               t.id AS triage_id
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
     LEFT JOIN triage_assessments t ON t.appointment_id = a.id
         WHERE a.appointment_date = CURDATE()
           AND a.status = 'checked_in'
         ORDER BY a.checked_in_at ASC
    ");
    $stmt->execute();
    sendJSON(['success' => true, 'queue' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("staff/queue error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load queue'], 500);
}
