<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Notifier.php';
require_once __DIR__ . '/../../utils/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('doctor') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$date   = sanitizeInput($input['date'] ?? '');
$reason = trim((string) ($input['reason'] ?? ''));
$targetDoctorId = (int) ($input['doctor_id'] ?? 0);

if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    sendJSON(['success' => false, 'message' => 'Valid date is required (YYYY-MM-DD)'], 400);
}
if ($reason === '') {
    sendJSON(['success' => false, 'message' => 'Reason is required'], 400);
}
if ($date < date('Y-m-d')) {
    sendJSON(['success' => false, 'message' => 'Date cannot be in the past'], 400);
}

try {
    $db     = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = $_SESSION['role'] ?? '';
    $actor  = $role === 'admin' ? 'admin' : 'doctor';

    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doctor_id = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        $doctor_id = $targetDoctorId;
    }
    if (!$doctor_id) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }

    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_time, a.appointment_date,
               p.full_name AS patient_name, u.id AS patient_user_id, u.email AS patient_email
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          JOIN users    u ON u.id = p.user_id
         WHERE a.doctor_id = :did
           AND a.appointment_date = :date
           AND a.status = 'scheduled'
    ");
    $stmt->execute([':did' => $doctor_id, ':date' => $date]);
    $affected = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $db->beginTransaction();
    $upd = $db->prepare("
        UPDATE appointments
           SET status = 'cancelled', cancelled_at = NOW(),
               cancelled_by = :actor, cancel_reason = :reason
         WHERE doctor_id = :did
           AND appointment_date = :date
           AND status = 'scheduled'
    ");
    $upd->execute([
        ':actor'  => $actor,
        ':reason' => $reason,
        ':did'    => $doctor_id,
        ':date'   => $date,
    ]);
    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', $role, 'UPDATE', 'Appointments', 0,
        "Day cancelled for doctor $doctor_id on $date ($reason): " . count($affected) . " appointments");

    $mailer = new Mailer();
    foreach ($affected as $a) {
        Notifier::notify(
            $db, (int) $a['patient_user_id'], 'day_cancelled',
            'Appointment cancelled — doctor unavailable',
            "Your appointment #{$a['appointment_number']} on {$a['appointment_date']} has been cancelled. Reason: $reason. Please rebook.",
            'patient-dashboard.html'
        );
        try {
            if (!empty($a['patient_email'])) {
                $mailer->sendDayCancelled(
                    $a['patient_email'], $a['patient_name'],
                    $a['appointment_number'], $a['appointment_date'], $a['appointment_time'], $reason
                );
            }
        } catch (Exception $e) {
            error_log("cancel-day email error: " . $e->getMessage());
        }
    }

    sendJSON([
        'success' => true,
        'cancelled_count' => count($affected),
        'message' => count($affected) . " appointment(s) cancelled and patients notified.",
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("cancel-day error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to cancel day'], 500);
}
