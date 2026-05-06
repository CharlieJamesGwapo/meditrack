<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$from = sanitizeInput($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$to   = sanitizeInput($_GET['to']   ?? date('Y-m-d'));

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("
        SELECT a.id AS appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               p.full_name AS patient_name,
               d.full_name AS doctor_name,
               mc.id AS cert_id, mc.diagnosis AS cert_diagnosis, mc.rest_days AS cert_rest_days,
               mc.issued_at AS cert_issued_at,
               mr.diagnosis AS record_diagnosis
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          JOIN doctors d ON d.id = a.doctor_id
     LEFT JOIN medical_certificates mc ON mc.appointment_id = a.id
     LEFT JOIN medical_records mr ON mr.appointment_id = a.id
         WHERE a.status = 'completed'
           AND a.appointment_date BETWEEN :from AND :to
         ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    sendJSON(['success' => true, 'rows' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("staff/certificates error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load certificates'], 500);
}
