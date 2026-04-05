<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$recordId = $_GET['id'] ?? null;
$source = $_GET['source'] ?? 'medical_records';

if (!$recordId) {
    sendJSON(['success' => false, 'message' => 'Record ID required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $role = getCurrentUserRole();

    if ($source === 'visits') {
        $query = "SELECT v.id, v.chief_complaint, v.symptoms, v.diagnosis, v.prescription,
                         v.lab_tests_ordered as lab_tests, v.vital_signs, v.notes, v.follow_up_date,
                         v.created_at, v.appointment_id,
                         p.full_name as patient_name, p.date_of_birth, p.gender, p.blood_group,
                         p.contact_number, p.allergies,
                         d.full_name as doctor_name, d.specialization, d.department,
                         a.appointment_date, a.appointment_time
                  FROM visits v
                  JOIN patients p ON v.patient_id = p.id
                  JOIN doctors d ON v.doctor_id = d.id
                  LEFT JOIN appointments a ON v.appointment_id = a.id
                  WHERE v.id = :id";
    } else {
        $query = "SELECT mr.id, mr.chief_complaint, mr.symptoms, mr.diagnosis, mr.prescription,
                         mr.lab_tests, mr.vital_signs, mr.notes, mr.follow_up_date,
                         mr.created_at, mr.appointment_id,
                         p.full_name as patient_name, p.date_of_birth, p.gender, p.blood_group,
                         p.contact_number, p.allergies,
                         d.full_name as doctor_name, d.specialization, d.department,
                         a.appointment_date, a.appointment_time
                  FROM medical_records mr
                  JOIN patients p ON mr.patient_id = p.id
                  JOIN doctors d ON mr.doctor_id = d.id
                  LEFT JOIN appointments a ON mr.appointment_id = a.id
                  WHERE mr.id = :id";
    }

    // Patients can only see their own records
    if ($role === 'patient') {
        $patientStmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid");
        $patientStmt->execute([':uid' => getCurrentUserId()]);
        $patientRow = $patientStmt->fetch(PDO::FETCH_ASSOC);

        if ($patientRow) {
            $tablePfx = $source === 'visits' ? 'v' : 'mr';
            $query .= " AND {$tablePfx}.patient_id = :patient_id";
        }
    }

    $stmt = $db->prepare($query);
    $params = [':id' => $recordId];
    if ($role === 'patient' && isset($patientRow)) {
        $params[':patient_id'] = $patientRow['id'];
    }
    $stmt->execute($params);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        sendJSON(['success' => false, 'message' => 'Record not found'], 404);
    }

    // Parse vital signs JSON
    $vitals = json_decode($record['vital_signs'] ?? '{}', true);
    $record['vitals'] = [
        'blood_pressure' => $vitals['blood_pressure'] ?? $vitals['bp'] ?? '',
        'temperature' => $vitals['temperature'] ?? $vitals['temp'] ?? '',
        'pulse' => $vitals['pulse'] ?? $vitals['heart_rate'] ?? '',
        'weight' => $vitals['weight'] ?? ''
    ];

    sendJSON(['success' => true, 'record' => $record]);

} catch (Exception $e) {
    error_log("get-record-detail error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
?>
