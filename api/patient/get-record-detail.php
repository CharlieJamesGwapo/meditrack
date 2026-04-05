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
               a.appointment_number, a.appointment_date, a.appointment_time, a.status
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN patients p ON mr.patient_id = p.id
        JOIN doctors d ON mr.doctor_id = d.id
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

    // Parse vital signs
    if (!empty($record['vital_signs'])) {
        $record['vital_signs'] = json_decode($record['vital_signs'], true) ?? [];
    } else {
        $record['vital_signs'] = [];
    }

    sendJSON(['success' => true, 'record' => $record]);

} catch (Exception $e) {
    error_log("get-record-detail error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
