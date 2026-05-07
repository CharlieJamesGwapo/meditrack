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
               a.appointment_date, a.appointment_time, a.appointment_number,
               t.chief_complaint  AS triage_chief_complaint,
               t.blood_pressure   AS triage_bp,
               t.temperature      AS triage_temp,
               t.heart_rate       AS triage_hr,
               t.weight           AS triage_weight,
               t.height_cm        AS triage_height,
               t.oxygen_saturation AS triage_o2
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN doctors d ON mr.doctor_id = d.id
   LEFT JOIN triage_assessments t ON t.appointment_id = mr.appointment_id
        WHERE mr.patient_id = :pid
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([':pid' => $patient_id]);
    $records = $stmt->fetchAll();

    foreach ($records as &$record) {
        // Vitals: prefer triage_assessments (current source of truth in C1+);
        // fall back to legacy medical_records.vital_signs JSON for older rows.
        $vitals = [];
        if ($record['triage_bp'] !== null) {
            $vitals = [
                'bp'                => $record['triage_bp'],
                'temperature'       => $record['triage_temp'],
                'heart_rate'        => $record['triage_hr'],
                'weight'            => $record['triage_weight'],
                'height'            => $record['triage_height'],
                'oxygen_saturation' => $record['triage_o2'],
            ];
        } elseif (!empty($record['vital_signs'])) {
            $vitals = json_decode($record['vital_signs'], true) ?? [];
        }
        $record['vital_signs'] = $vitals;

        // Chief complaint: prefer triage; fall back to legacy medical_records column.
        if (empty($record['chief_complaint']) && !empty($record['triage_chief_complaint'])) {
            $record['chief_complaint'] = $record['triage_chief_complaint'];
        }

        // Strip helper columns from response
        foreach (['triage_chief_complaint','triage_bp','triage_temp','triage_hr','triage_weight','triage_height','triage_o2'] as $k) {
            unset($record[$k]);
        }
    }
    unset($record);

    sendJSON(['success' => true, 'records' => $records, 'count' => count($records)]);

} catch (Exception $e) {
    error_log("get-medical-records error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load medical records'], 500);
}
