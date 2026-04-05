<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    // Get patient ID
    $patientStmt = $db->prepare("SELECT id FROM patients WHERE user_id = :user_id");
    $patientStmt->execute([':user_id' => $userId]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => true, 'records' => []]);
    }

    $patientId = $patient['id'];

    // Get medical records from medical_records table
    $query = "SELECT
                mr.id,
                mr.chief_complaint,
                mr.symptoms,
                mr.diagnosis,
                mr.prescription,
                mr.lab_tests,
                mr.vital_signs,
                mr.notes,
                mr.follow_up_date,
                mr.created_at,
                d.full_name as doctor_name,
                d.specialization,
                d.department,
                a.appointment_date,
                a.appointment_time
              FROM medical_records mr
              LEFT JOIN doctors d ON mr.doctor_id = d.id
              LEFT JOIN appointments a ON mr.appointment_id = a.id
              WHERE mr.patient_id = :patient_id
              ORDER BY mr.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':patient_id' => $patientId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no records in medical_records, also check visits table
    if (empty($records)) {
        $visitQuery = "SELECT
                        v.id,
                        v.chief_complaint,
                        v.symptoms,
                        v.diagnosis,
                        v.prescription,
                        v.lab_tests_ordered as lab_tests,
                        v.vital_signs,
                        v.notes,
                        v.follow_up_date,
                        v.created_at,
                        d.full_name as doctor_name,
                        d.specialization,
                        d.department,
                        a.appointment_date,
                        a.appointment_time
                      FROM visits v
                      LEFT JOIN doctors d ON v.doctor_id = d.id
                      LEFT JOIN appointments a ON v.appointment_id = a.id
                      WHERE v.patient_id = :patient_id
                      ORDER BY v.created_at DESC";

        $visitStmt = $db->prepare($visitQuery);
        $visitStmt->execute([':patient_id' => $patientId]);
        $records = $visitStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Format records and parse vital signs JSON
    foreach ($records as &$record) {
        $record['formatted_date'] = $record['created_at'] ? date('M d, Y', strtotime($record['created_at'])) : 'N/A';
        $record['formatted_time'] = !empty($record['appointment_time']) ? date('g:i A', strtotime($record['appointment_time'])) : '';

        // Parse vital_signs JSON into individual fields
        $vitals = json_decode($record['vital_signs'] ?? '{}', true);
        $record['vital_bp'] = $vitals['blood_pressure'] ?? $vitals['bp'] ?? '';
        $record['vital_temp'] = $vitals['temperature'] ?? $vitals['temp'] ?? '';
        $record['vital_pulse'] = $vitals['pulse'] ?? $vitals['heart_rate'] ?? '';
        $record['vital_weight'] = $vitals['weight'] ?? '';
    }

    sendJSON([
        'success' => true,
        'records' => $records,
        'count' => count($records)
    ]);

} catch (Exception $e) {
    error_log("Error in get-medical-records.php: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error', 'records' => []], 500);
}
