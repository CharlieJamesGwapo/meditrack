<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('staff')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input              = json_decode(file_get_contents('php://input'), true);
$appointment_id     = (int) ($input['appointment_id'] ?? 0);
$diagnosis          = sanitizeInput($input['diagnosis'] ?? '');
$rest_period_start  = sanitizeInput($input['rest_period_start'] ?? '');
$rest_period_end    = sanitizeInput($input['rest_period_end'] ?? '');
$rest_days_input    = $input['rest_days'] ?? null;
$notes              = sanitizeInput($input['notes'] ?? '');

if (!$appointment_id || empty($diagnosis) || empty($rest_period_start) || empty($rest_period_end)) {
    sendJSON(['success' => false, 'message' => 'appointment_id, diagnosis, rest_period_start, rest_period_end are required'], 400);
}

$startTs = strtotime($rest_period_start);
$endTs   = strtotime($rest_period_end);
if ($startTs === false || $endTs === false || $endTs < $startTs) {
    sendJSON(['success' => false, 'message' => 'Invalid rest period (end must be on or after start)'], 400);
}

$computedDays = (int) floor(($endTs - $startTs) / 86400) + 1;
$rest_days    = is_numeric($rest_days_input) && (int) $rest_days_input > 0 ? (int) $rest_days_input : $computedDays;

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if ($appt['status'] !== 'completed') {
        sendJSON(['success' => false, 'message' => 'Doctor must complete the medical record before a certificate can be issued'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO medical_certificates
          (appointment_id, patient_id, doctor_id, issued_by_user_id, diagnosis, rest_period_start, rest_period_end, rest_days, notes)
        VALUES
          (:aid, :pid, :did, :uid, :diag, :rs, :re, :rd, :notes)
        ON DUPLICATE KEY UPDATE
          diagnosis         = VALUES(diagnosis),
          rest_period_start = VALUES(rest_period_start),
          rest_period_end   = VALUES(rest_period_end),
          rest_days         = VALUES(rest_days),
          notes             = VALUES(notes),
          issued_by_user_id = VALUES(issued_by_user_id),
          issued_at         = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':aid'   => $appointment_id,
        ':pid'   => $appt['patient_id'],
        ':did'   => $appt['doctor_id'],
        ':uid'   => $userId,
        ':diag'  => $diagnosis,
        ':rs'    => $rest_period_start,
        ':re'    => $rest_period_end,
        ':rd'    => $rest_days,
        ':notes' => $notes ?: null,
    ]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'staff', 'CREATE', 'MedicalCertificates', $appointment_id, "Cert issued for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Certificate issued', 'rest_days' => $rest_days]);
} catch (Exception $e) {
    error_log("staff/issue-certificate error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to issue certificate'], 500);
}
