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
        sendJSON(['success' => true, 'records' => []]);
    }
    $patient_id = $patient['id'];

    $stmt = $db->prepare("
        SELECT mr.id, mr.appointment_id, mr.chief_complaint, mr.symptoms,
               mr.vital_signs, mr.diagnosis, mr.prescription, mr.lab_tests_ordered,
               mr.notes, mr.follow_up_date, mr.created_at, mr.updated_at,
               d.full_name as doctor_name, d.specialization,
               a.appointment_date, a.appointment_time, a.appointment_number
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN doctors d ON mr.doctor_id = d.id
        WHERE mr.patient_id = :pid
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([':pid' => $patient_id]);
    $records = $stmt->fetchAll();

    foreach ($records as &$record) {
        // Parse vital_signs JSON
        $vitals = [];
        if (!empty($record['vital_signs'])) {
            $vitals = json_decode($record['vital_signs'], true) ?? [];
        }
        $record['vital_signs'] = $vitals;
    }

    sendJSON(['success' => true, 'records' => $records, 'count' => count($records)]);

} catch (Exception $e) {
    error_log("get-medical-records error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load medical records'], 500);
}
