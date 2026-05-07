<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/NoShowSweeper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    NoShowSweeper::sweep($db);  // self-healing: stale scheduled → no_show
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = $doctor['id'];

    $filter_date   = sanitizeInput($_GET['date'] ?? '');
    $filter_status = sanitizeInput($_GET['status'] ?? '');

    $sql = "
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, a.checked_in_at, a.completed_at, a.cancelled_at, a.created_at,
               p.id as patient_id, p.full_name as patient_name, p.date_of_birth as patient_dob,
               p.gender as patient_gender, p.blood_group, p.allergies, p.contact_number as patient_contact,
               qt.token_hash, qt.expires_at as qr_expires_at,
               t.chief_complaint  AS triage_chief_complaint,
               t.blood_pressure   AS vital_bp,
               t.temperature      AS vital_temp,
               t.heart_rate       AS vital_hr,
               t.weight           AS vital_weight,
               t.height_cm        AS vital_height,
               t.oxygen_saturation AS vital_o2,
               t.notes            AS triage_notes,
               t.recorded_at      AS vitals_recorded_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id
        LEFT JOIN triage_assessments t ON t.appointment_id = a.id
        WHERE a.doctor_id = :did
    ";
    $params = [':did' => $doctor_id];

    if (!empty($filter_date)) {
        $sql .= " AND a.appointment_date = :date";
        $params[':date'] = $filter_date;
    }
    if (!empty($filter_status)) {
        $sql .= " AND a.status = :status";
        $params[':status'] = $filter_status;
    }

    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // Build a nested vitals object on each appointment row.
    foreach ($appointments as &$row) {
        $row['triage_chief_complaint'] = $row['triage_chief_complaint'] ?? null;
        $row['vitals'] = ($row['vital_bp'] === null) ? null : [
            'chief_complaint'   => $row['triage_chief_complaint'],
            'blood_pressure'    => $row['vital_bp'],
            'temperature'       => $row['vital_temp'],
            'heart_rate'        => $row['vital_hr'],
            'weight'            => $row['vital_weight'],
            'height_cm'         => $row['vital_height'],
            'oxygen_saturation' => $row['vital_o2'],
            'notes'             => $row['triage_notes'],
            'recorded_at'       => $row['vitals_recorded_at'],
        ];
        foreach (['vital_bp','vital_temp','vital_hr','vital_weight','vital_height','vital_o2','triage_notes','vitals_recorded_at'] as $k) {
            unset($row[$k]);
        }
    }
    unset($row);

    sendJSON(['success' => true, 'appointments' => $appointments, 'count' => count($appointments)]);

} catch (Exception $e) {
    error_log("doctor get-appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load appointments'], 500);
}
