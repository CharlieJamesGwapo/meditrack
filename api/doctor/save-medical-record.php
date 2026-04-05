<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = $doctor['id'];

    // Verify appointment belongs to doctor and is in correct status
    $stmt = $db->prepare("SELECT id, patient_id, status FROM appointments WHERE id = :aid AND doctor_id = :did LIMIT 1");
    $stmt->execute([':aid' => $appointment_id, ':did' => $doctor_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or not assigned to you'], 404);
    }
    if (!in_array($appointment['status'], ['checked_in', 'in_progress'])) {
        sendJSON(['success' => false, 'message' => 'Patient must be checked in before saving a medical record'], 400);
    }

    $patient_id       = $appointment['patient_id'];
    $chief_complaint  = sanitizeInput($input['chief_complaint'] ?? '');
    $symptoms         = sanitizeInput($input['symptoms'] ?? '');
    $diagnosis        = sanitizeInput($input['diagnosis'] ?? '');
    $prescription     = sanitizeInput($input['prescription'] ?? '');
    $lab_tests_ordered = sanitizeInput($input['lab_tests_ordered'] ?? '');
    $notes            = sanitizeInput($input['notes'] ?? '');
    $follow_up_date   = sanitizeInput($input['follow_up_date'] ?? '') ?: null;

    // Build vital_signs JSON
    $vital_signs = json_encode([
        'bp'          => sanitizeInput($input['bp'] ?? ''),
        'temperature' => sanitizeInput($input['temperature'] ?? ''),
        'heart_rate'  => sanitizeInput($input['heart_rate'] ?? ''),
        'weight'      => sanitizeInput($input['weight'] ?? ''),
        'height'      => sanitizeInput($input['height'] ?? '')
    ]);

    $db->beginTransaction();

    // INSERT or UPDATE (ON DUPLICATE KEY — appointment_id is UNIQUE)
    $stmt = $db->prepare("
        INSERT INTO medical_records
            (appointment_id, patient_id, doctor_id, chief_complaint, symptoms, vital_signs, diagnosis, prescription, lab_tests_ordered, notes, follow_up_date)
        VALUES
            (:aid, :pid, :did, :cc, :sym, :vs, :diag, :rx, :lab, :notes, :fud)
        ON DUPLICATE KEY UPDATE
            chief_complaint    = VALUES(chief_complaint),
            symptoms           = VALUES(symptoms),
            vital_signs        = VALUES(vital_signs),
            diagnosis          = VALUES(diagnosis),
            prescription       = VALUES(prescription),
            lab_tests_ordered  = VALUES(lab_tests_ordered),
            notes              = VALUES(notes),
            follow_up_date     = VALUES(follow_up_date)
    ");
    $stmt->execute([
        ':aid'   => $appointment_id,
        ':pid'   => $patient_id,
        ':did'   => $doctor_id,
        ':cc'    => $chief_complaint,
        ':sym'   => $symptoms,
        ':vs'    => $vital_signs,
        ':diag'  => $diagnosis,
        ':rx'    => $prescription,
        ':lab'   => $lab_tests_ordered,
        ':notes' => $notes,
        ':fud'   => $follow_up_date
    ]);
    $record_id = $db->lastInsertId() ?: $appointment_id;

    // Update appointment status to completed
    $db->prepare("UPDATE appointments SET status = 'completed', completed_at = NOW() WHERE id = :aid")
       ->execute([':aid' => $appointment_id]);

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', 'CREATE', 'MedicalRecords', $appointment_id, "Medical record saved for appointment #$appointment_id");

    sendJSON([
        'success'   => true,
        'message'   => 'Medical record saved successfully',
        'record_id' => $record_id
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("save-medical-record error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to save medical record'], 500);
}
