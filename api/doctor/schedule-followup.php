<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';
require_once __DIR__ . '/../../utils/Mailer.php';
require_once __DIR__ . '/../../utils/Notifier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$parent_appointment_id = (int) ($input['parent_appointment_id'] ?? 0);
$appointment_date      = sanitizeInput($input['appointment_date'] ?? '');
$appointment_time      = sanitizeInput($input['appointment_time'] ?? '');
$reason_for_visit      = sanitizeInput($input['reason_for_visit'] ?? '');

if (!$parent_appointment_id || empty($appointment_date) || empty($appointment_time)) {
    sendJSON(['success' => false, 'message' => 'parent_appointment_id, appointment_date, appointment_time are required'], 400);
}
if ($appointment_date <= date('Y-m-d')) {
    sendJSON(['success' => false, 'message' => 'Follow-up date must be in the future'], 400);
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

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, reason_for_visit FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $parent_appointment_id]);
    $parent = $stmt->fetch();
    if (!$parent || (int) $parent['doctor_id'] !== $doctor_id) {
        sendJSON(['success' => false, 'message' => 'Parent appointment not found or not assigned to you'], 404);
    }
    $patient_id = (int) $parent['patient_id'];
    if (empty($reason_for_visit)) {
        $reason_for_visit = 'Follow-up of ' . ($parent['reason_for_visit'] ?: 'previous consultation');
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT id, status FROM appointments
         WHERE parent_appointment_id = :pid
           AND is_followup = 1
           AND status IN ('scheduled','checked_in','in_progress')
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_appointment_id]);
    $existing = $stmt->fetch();
    $excludeId = $existing ? (int) $existing['id'] : 0;

    $stmt = $db->prepare("
        SELECT id FROM appointments
         WHERE doctor_id = :did
           AND appointment_date = :date
           AND appointment_time = :time
           AND status NOT IN ('cancelled','no_show')
           AND id != :excl
         LIMIT 1 FOR UPDATE
    ");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date, ':time' => $appointment_time, ':excl' => $excludeId]);
    if ($stmt->rowCount() > 0) {
        $db->rollBack();
        sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 409);
    }

    if ($existing) {
        $stmt = $db->prepare("
            UPDATE appointments
               SET appointment_date = :date,
                   appointment_time = :time,
                   reason_for_visit = :reason
             WHERE id = :aid
        ");
        $stmt->execute([
            ':date'   => $appointment_date,
            ':time'   => $appointment_time,
            ':reason' => $reason_for_visit,
            ':aid'    => $existing['id'],
        ]);
        $appointment_id = (int) $existing['id'];
        $stmt = $db->prepare("SELECT appointment_number FROM appointments WHERE id = :aid");
        $stmt->execute([':aid' => $appointment_id]);
        $appointment_number = $stmt->fetchColumn();
        $db->prepare("DELETE FROM qr_tokens WHERE appointment_id = :aid")->execute([':aid' => $appointment_id]);
        $mode = 'updated';
    } else {
        $date_part = date('Ymd', strtotime($appointment_date));
        $stmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(appointment_number, -4) AS UNSIGNED)), 0) + 1 AS next_num FROM appointments WHERE appointment_date = :date FOR UPDATE");
        $stmt->execute([':date' => $appointment_date]);
        $cnt = (int) $stmt->fetch()['next_num'];
        $appointment_number = 'APT-' . $date_part . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO appointments
              (appointment_number, patient_id, doctor_id, parent_appointment_id, is_followup,
               appointment_date, appointment_time, reason_for_visit, status)
            VALUES
              (:num, :pid, :did, :parent, 1, :date, :time, :reason, 'scheduled')
        ");
        $stmt->execute([
            ':num'    => $appointment_number,
            ':pid'    => $patient_id,
            ':did'    => $doctor_id,
            ':parent' => $parent_appointment_id,
            ':date'   => $appointment_date,
            ':time'   => $appointment_time,
            ':reason' => $reason_for_visit,
        ]);
        $appointment_id = (int) $db->lastInsertId();
        $mode = 'created';
    }

    $qr = (new QRCodeGenerator($db))->generateQRCode($appointment_id);

    $db->prepare("UPDATE medical_records SET follow_up_date = :d WHERE appointment_id = :pid")
       ->execute([':d' => $appointment_date, ':pid' => $parent_appointment_id]);

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', $mode === 'created' ? 'CREATE' : 'UPDATE', 'Followup', $appointment_id, "Follow-up $mode for parent #$parent_appointment_id on $appointment_date $appointment_time");

    try {
        $stmt = $db->prepare("SELECT u.id AS user_id, u.email, p.full_name, d.full_name AS doctor_name
                                FROM patients p
                                JOIN users u ON u.id = p.user_id
                                JOIN doctors d ON d.id = :did
                               WHERE p.id = :pid LIMIT 1");
        $stmt->execute([':pid' => $patient_id, ':did' => $doctor_id]);
        $info = $stmt->fetch();
        if ($info) {
            Notifier::notify(
                $db, (int) $info['user_id'], 'followup_scheduled',
                'Follow-up scheduled',
                "Your follow-up #{$appointment_number} is on {$appointment_date} at {$appointment_time}.",
                'patient-dashboard.html'
            );
            if (!empty($info['email'])) {
                (new Mailer())->sendAppointmentConfirmation(
                    $info['email'], $info['full_name'], $appointment_number,
                    $appointment_date, $appointment_time, $info['doctor_name']
                );
            }
        }
    } catch (Exception $e) {
        error_log("schedule-followup notify error: " . $e->getMessage());
    }

    sendJSON([
        'success'            => true,
        'mode'               => $mode,
        'appointment_id'     => $appointment_id,
        'appointment_number' => $appointment_number,
        'qr_payload'         => $qr,
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("schedule-followup error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to schedule follow-up'], 500);
}
