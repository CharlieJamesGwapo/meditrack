<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$ALLOWED_SPECIALTIES = ['ENT','Cardiology','OB-GYN','Pediatrics','General Surgery','Dermatology','Orthopedics','Ophthalmology','Neurology','Other'];
$ALLOWED_URGENCY     = ['routine','urgent','emergency'];

$input = json_decode(file_get_contents('php://input'), true);

$appointment_id        = (int) ($input['appointment_id'] ?? 0);
$specialty             = sanitizeInput($input['specialty'] ?? '');
$specialty_other       = sanitizeInput($input['specialty_other'] ?? '');
$suggested_specialist  = sanitizeInput($input['suggested_specialist'] ?? '');
$reason                = sanitizeInput($input['reason'] ?? '');
$urgency               = sanitizeInput($input['urgency'] ?? 'routine');

if (!$appointment_id || empty($specialty) || empty($reason)) {
    sendJSON(['success' => false, 'message' => 'appointment_id, specialty, and reason are required'], 400);
}
if (!in_array($specialty, $ALLOWED_SPECIALTIES, true)) {
    sendJSON(['success' => false, 'message' => 'Unsupported specialty'], 400);
}
if ($specialty === 'Other' && empty($specialty_other)) {
    sendJSON(['success' => false, 'message' => 'Please describe the specialty when "Other" is selected'], 400);
}
if (!in_array($urgency, $ALLOWED_URGENCY, true)) {
    sendJSON(['success' => false, 'message' => 'Invalid urgency'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt || (int) $appt['doctor_id'] !== $doctor_id) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or not assigned to you'], 404);
    }
    if (!in_array($appt['status'], ['in_progress','completed'], true)) {
        sendJSON(['success' => false, 'message' => 'Referral can only be issued during or after the consultation'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO referrals
          (appointment_id, patient_id, referring_doctor_id, specialty, specialty_other, suggested_specialist, reason, urgency)
        VALUES
          (:aid, :pid, :did, :spec, :other, :sugg, :reason, :urg)
        ON DUPLICATE KEY UPDATE
          specialty            = VALUES(specialty),
          specialty_other      = VALUES(specialty_other),
          suggested_specialist = VALUES(suggested_specialist),
          reason               = VALUES(reason),
          urgency              = VALUES(urgency)
    ");
    $stmt->execute([
        ':aid'    => $appointment_id,
        ':pid'    => $appt['patient_id'],
        ':did'    => $doctor_id,
        ':spec'   => $specialty,
        ':other'  => $specialty === 'Other' ? $specialty_other : null,
        ':sugg'   => $suggested_specialist ?: null,
        ':reason' => $reason,
        ':urg'    => $urgency,
    ]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', 'CREATE', 'Referrals', $appointment_id, "Referral issued ($specialty) for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Referral saved']);
} catch (Exception $e) {
    error_log("doctor/save-referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to save referral'], 500);
}
