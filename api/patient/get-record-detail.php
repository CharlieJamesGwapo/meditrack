<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$recordId = (int)($_GET['id'] ?? 0);
if (!$recordId) {
    sendJSON(['success' => false, 'message' => 'Record ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $role = getCurrentUserRole();
    $userId = getCurrentUserId();

    $query = "
        SELECT mr.id, mr.chief_complaint, mr.symptoms, mr.vital_signs,
               mr.diagnosis, mr.prescription, mr.lab_tests_ordered,
               mr.notes, mr.follow_up_date, mr.created_at,
               p.full_name as patient_name, p.date_of_birth, p.gender,
               p.contact_number, p.blood_group, p.allergies,
               d.full_name as doctor_name, d.specialization, d.license_number,
               a.appointment_number, a.appointment_date, a.appointment_time, a.status,
               t.chief_complaint  AS triage_chief_complaint,
               t.blood_pressure   AS triage_bp,
               t.temperature      AS triage_temp,
               t.heart_rate       AS triage_hr,
               t.weight           AS triage_weight,
               t.height_cm        AS triage_height,
               t.oxygen_saturation AS triage_o2
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN patients p ON mr.patient_id = p.id
        JOIN doctors d ON mr.doctor_id = d.id
   LEFT JOIN triage_assessments t ON t.appointment_id = mr.appointment_id
        WHERE mr.id = :id
    ";

    // Access control
    if ($role === 'patient') {
        $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $patient = $stmt->fetch();
        if (!$patient) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
        }
        $query .= " AND mr.patient_id = :pid";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $recordId, ':pid' => $patient['id']]);
    } elseif ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $doctor = $stmt->fetch();
        if (!$doctor) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
        }
        $query .= " AND mr.doctor_id = :did";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $recordId, ':did' => $doctor['id']]);
    } else {
        // Admin can view all
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $recordId]);
    }

    $record = $stmt->fetch();
    if (!$record) {
        sendJSON(['success' => false, 'message' => 'Record not found'], 404);
    }

    // Vitals: prefer triage_assessments (C1 source of truth); fall back to
    // legacy medical_records.vital_signs JSON for pre-C1 rows.
    if ($record['triage_bp'] !== null) {
        $record['vital_signs'] = [
            'bp'                => $record['triage_bp'],
            'temperature'       => $record['triage_temp'],
            'heart_rate'        => $record['triage_hr'],
            'weight'            => $record['triage_weight'],
            'height'            => $record['triage_height'],
            'oxygen_saturation' => $record['triage_o2'],
        ];
    } elseif (!empty($record['vital_signs'])) {
        $record['vital_signs'] = json_decode($record['vital_signs'], true) ?? [];
    } else {
        $record['vital_signs'] = [];
    }
    if (empty($record['chief_complaint']) && !empty($record['triage_chief_complaint'])) {
        $record['chief_complaint'] = $record['triage_chief_complaint'];
    }
    foreach (['triage_chief_complaint','triage_bp','triage_temp','triage_hr','triage_weight','triage_height','triage_o2'] as $k) {
        unset($record[$k]);
    }

    sendJSON(['success' => true, 'record' => $record]);

} catch (Exception $e) {
    error_log("get-record-detail error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
