<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('staff') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input          = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

$chief_complaint   = sanitizeInput($input['chief_complaint'] ?? '');
$blood_pressure    = sanitizeInput($input['blood_pressure'] ?? '');
$temperature       = (isset($input['temperature']) && $input['temperature'] !== '') ? floatval($input['temperature']) : null;
$heart_rate        = (isset($input['heart_rate']) && $input['heart_rate'] !== '') ? intval($input['heart_rate']) : null;
$weight            = (isset($input['weight']) && $input['weight'] !== '') ? floatval($input['weight']) : null;
$height_cm         = (isset($input['height_cm']) && $input['height_cm'] !== '') ? intval($input['height_cm']) : null;
$oxygen_saturation = (isset($input['oxygen_saturation']) && $input['oxygen_saturation'] !== '') ? intval($input['oxygen_saturation']) : null;
$notes             = sanitizeInput($input['notes'] ?? '');

if (empty($chief_complaint) || empty($blood_pressure) || $weight === null || $height_cm === null) {
    sendJSON(['success' => false, 'message' => 'Chief complaint, blood pressure, weight, and height are required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, patient_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if (!in_array($appt['status'], ['checked_in', 'in_progress'], true)) {
        sendJSON(['success' => false, 'message' => 'Patient must be checked in before recording vitals'], 400);
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO triage_assessments
          (appointment_id, patient_id, chief_complaint, blood_pressure, temperature, heart_rate,
           weight, height_cm, oxygen_saturation, notes, recorded_by)
        VALUES
          (:aid, :pid, :cc, :bp, :temp, :hr, :w, :h, :o2, :notes, :uid)
        ON DUPLICATE KEY UPDATE
          chief_complaint   = VALUES(chief_complaint),
          blood_pressure    = VALUES(blood_pressure),
          temperature       = VALUES(temperature),
          heart_rate        = VALUES(heart_rate),
          weight            = VALUES(weight),
          height_cm         = VALUES(height_cm),
          oxygen_saturation = VALUES(oxygen_saturation),
          notes             = VALUES(notes),
          recorded_by       = VALUES(recorded_by)
    ");
    $stmt->execute([
        ':aid'   => $appointment_id,
        ':pid'   => $appt['patient_id'],
        ':cc'    => $chief_complaint,
        ':bp'    => $blood_pressure,
        ':temp'  => $temperature,
        ':hr'    => $heart_rate,
        ':w'     => $weight,
        ':h'     => $height_cm,
        ':o2'    => $oxygen_saturation,
        ':notes' => $notes ?: null,
        ':uid'   => $userId,
    ]);

    if ($appt['status'] === 'checked_in') {
        $db->prepare("UPDATE appointments SET status = 'in_progress' WHERE id = :aid")
           ->execute([':aid' => $appointment_id]);
    }

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', getCurrentUserRole() ?? 'staff', 'CREATE', 'Triage', $appointment_id, "Vitals recorded for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Vitals saved']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("staff/save-vitals error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to save vitals'], 500);
}
