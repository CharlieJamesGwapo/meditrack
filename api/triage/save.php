<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only reception and admin can save triage data
if (!in_array(getCurrentUserRole(), ['reception', 'admin'])) {
    sendJSON(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required_fields = ['patient_id', 'chief_complaint'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        sendJSON(['success' => false, 'message' => "Field '$field' is required"], 400);
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if patient exists
    $patientQuery = "SELECT id FROM patients WHERE id = :patient_id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->execute([':patient_id' => $data['patient_id']]);
    
    if (!$patientStmt->fetch()) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    // Insert triage data
    $triageQuery = "INSERT INTO triage_assessments (
        patient_id, 
        chief_complaint, 
        blood_pressure, 
        temperature, 
        heart_rate, 
        weight, 
        priority_level, 
        notes, 
        recorded_by, 
        recorded_at
    ) VALUES (
        :patient_id, 
        :chief_complaint, 
        :blood_pressure, 
        :temperature, 
        :heart_rate, 
        :weight, 
        :priority_level, 
        :notes, 
        :recorded_by, 
        NOW()
    )";

    $triageStmt = $db->prepare($triageQuery);
    $result = $triageStmt->execute([
        ':patient_id' => $data['patient_id'],
        ':chief_complaint' => $data['chief_complaint'],
        ':blood_pressure' => $data['blood_pressure'] ?? null,
        ':temperature' => $data['temperature'] ?? null,
        ':heart_rate' => $data['heart_rate'] ?? null,
        ':weight' => $data['weight'] ?? null,
        ':priority_level' => $data['priority_level'] ?? 'low',
        ':notes' => $data['notes'] ?? null,
        ':recorded_by' => getCurrentUserId()
    ]);

    if ($result) {
        $triage_id = $db->lastInsertId();

        // Update patient's current appointment with triage priority if exists
        $updateAppointmentQuery = "UPDATE appointments 
                                  SET priority_level = :priority 
                                  WHERE patient_id = :patient_id 
                                  AND appointment_date = CURDATE() 
                                  AND status IN ('scheduled', 'checked_in')
                                  ORDER BY appointment_time ASC 
                                  LIMIT 1";
        $updateStmt = $db->prepare($updateAppointmentQuery);
        $updateStmt->execute([
            ':priority' => $data['priority_level'] ?? 'low',
            ':patient_id' => $data['patient_id']
        ]);

        // Create notification for assigned doctor if appointment exists
        $doctorNotifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id)
                            SELECT d.user_id, 'triage', 'Triage Assessment Completed', 
                                   CONCAT('Triage completed for patient. Priority: ', :priority), :triage_id
                            FROM appointments a
                            JOIN doctors d ON a.doctor_id = d.id
                            WHERE a.patient_id = :patient_id 
                            AND a.appointment_date = CURDATE() 
                            AND a.status IN ('scheduled', 'checked_in')
                            LIMIT 1";
        $notifStmt = $db->prepare($doctorNotifQuery);
        $notifStmt->execute([
            ':priority' => $data['priority_level'] ?? 'low',
            ':triage_id' => $triage_id,
            ':patient_id' => $data['patient_id']
        ]);

        // Log audit
        logAudit($db, getCurrentUserId(), 'create_triage', 'triage_assessments', $triage_id, 'Triage assessment created');

        sendJSON([
            'success' => true,
            'message' => 'Triage assessment saved successfully',
            'triage_id' => $triage_id
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Failed to save triage assessment'], 500);
    }

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>
