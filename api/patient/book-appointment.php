<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';
require_once __DIR__ . '/../../utils/Mailer.php';
require_once __DIR__ . '/../../utils/Notifier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_date = sanitizeInput($input['appointment_date'] ?? '');
$appointment_time = sanitizeInput($input['appointment_time'] ?? '');
$reason_for_visit = sanitizeInput($input['reason_for_visit'] ?? '');

if (empty($appointment_date) || empty($appointment_time) || empty($reason_for_visit)) {
    sendJSON(['success' => false, 'message' => 'Appointment date, time, and reason are required'], 400);
}

// Validate date not in the past
if ($appointment_date < date('Y-m-d')) {
    sendJSON(['success' => false, 'message' => 'Appointment date cannot be in the past'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    // Get patient profile
    $stmt = $db->prepare("SELECT id, full_name FROM patients WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }
    $patient_id = $patient['id'];

    // Auto-select single active doctor
    $stmt = $db->prepare("SELECT id, full_name, consultation_fee FROM doctors WHERE status = 'active' LIMIT 1");
    $stmt->execute();
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No active doctor available'], 503);
    }
    $doctor_id = $doctor['id'];

    // Get day_of_week (0=Sunday, 6=Saturday)
    $day_of_week = (int) date('w', strtotime($appointment_date));

    // Check doctor has schedule for that day
    $stmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :did AND day_of_week = :dow AND is_active = 1 LIMIT 1");
    $stmt->execute([':did' => $doctor_id, ':dow' => $day_of_week]);
    $schedule = $stmt->fetch();
    if (!$schedule) {
        sendJSON(['success' => false, 'message' => 'Doctor is not available on this day'], 400);
    }

    // Validate time within schedule
    if ($appointment_time < $schedule['start_time'] || $appointment_time >= $schedule['end_time']) {
        sendJSON(['success' => false, 'message' => 'Appointment time is outside doctor\'s schedule'], 400);
    }

    $db->beginTransaction();

    // Slot lock with grace period — a 'scheduled' appointment past
    // NO_SHOW_GRACE_MINUTES with no check-in is treated as a stale no-show.
    // We auto-convert it to status='no_show' so the new booking takes the slot
    // cleanly (no two active appointments at the same time).
    $grace = defined('NO_SHOW_GRACE_MINUTES') ? (int) NO_SHOW_GRACE_MINUTES : 15;

    $stmt = $db->prepare("
        SELECT id, status, appointment_date, appointment_time
          FROM appointments
         WHERE doctor_id = :did
           AND appointment_date = :date
           AND appointment_time = :time
           AND status NOT IN ('cancelled','no_show')
         LIMIT 1 FOR UPDATE
    ");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date, ':time' => $appointment_time]);
    $existing = $stmt->fetch();

    if ($existing) {
        $isStaleScheduled = $existing['status'] === 'scheduled'
            && strtotime($existing['appointment_date'] . ' ' . $existing['appointment_time']) < (time() - $grace * 60);

        if ($isStaleScheduled) {
            // Atomically convert the stale row to no_show; same transaction.
            $db->prepare("
                UPDATE appointments
                   SET status = 'no_show', updated_at = NOW()
                 WHERE id = :aid
            ")->execute([':aid' => $existing['id']]);
            logActivity($db, $userId, $_SESSION['username'] ?? '', 'system', 'UPDATE', 'Appointments', $existing['id'], "Auto-marked as no-show on rebooking by another patient");
        } else {
            $db->rollBack();
            sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 409);
        }
    }

    // Patient doesn't already have appointment that day
    $stmt = $db->prepare("SELECT id FROM appointments WHERE patient_id = :pid AND appointment_date = :date AND status NOT IN ('cancelled','no_show') LIMIT 1 FOR UPDATE");
    $stmt->execute([':pid' => $patient_id, ':date' => $appointment_date]);
    if ($stmt->rowCount() > 0) {
        $db->rollBack();
        sendJSON(['success' => false, 'message' => 'You already have an appointment on this date'], 409);
    }

    // Check max_patients not exceeded
    $stmt = $db->prepare("SELECT COUNT(*) as booked FROM appointments WHERE doctor_id = :did AND appointment_date = :date AND status NOT IN ('cancelled','no_show') FOR UPDATE");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date]);
    $booked = (int) $stmt->fetch()['booked'];
    if ($booked >= $schedule['max_patients']) {
        $db->rollBack();
        sendJSON(['success' => false, 'message' => 'Doctor has reached maximum patients for this day'], 400);
    }

    // Generate appointment number: APT-YYYYMMDD-XXXX
    $date_part = date('Ymd', strtotime($appointment_date));
    $stmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(appointment_number, -4) AS UNSIGNED)), 0) + 1 as next_num FROM appointments WHERE appointment_date = :date FOR UPDATE");
    $stmt->execute([':date' => $appointment_date]);
    $cnt = (int) $stmt->fetch()['next_num'];
    $appointment_number = 'APT-' . $date_part . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason_for_visit, status) VALUES (:num, :pid, :did, :date, :time, :reason, 'scheduled')");
    $stmt->execute([
        ':num'    => $appointment_number,
        ':pid'    => $patient_id,
        ':did'    => $doctor_id,
        ':date'   => $appointment_date,
        ':time'   => $appointment_time,
        ':reason' => $reason_for_visit
    ]);
    $appointment_id = (int) $db->lastInsertId();

    // Generate QR code
    $qrGenerator = new QRCodeGenerator($db);
    $qrData = $qrGenerator->generateQRCode($appointment_id);

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'patient', 'CREATE', 'Appointments', $appointment_id, "Appointment booked: $appointment_number");

    try {
        $stmt = $db->prepare("SELECT email FROM users WHERE id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $patientEmail = $stmt->fetchColumn() ?: null;

        Notifier::notify(
            $db, $userId, 'booking_confirmed',
            'Appointment booked',
            "Your appointment #{$appointment_number} on {$appointment_date} at {$appointment_time} is confirmed. You can cancel up to 2 hours before your scheduled time.",
            'patient-dashboard.html'
        );

        if ($patientEmail) {
            (new Mailer())->sendAppointmentConfirmation(
                $patientEmail,
                $patient['full_name'],
                $appointment_number,
                $appointment_date,
                $appointment_time,
                $doctor['full_name']
            );
        }
    } catch (Exception $e) {
        error_log("book-appointment notify/email error: " . $e->getMessage());
    }

    sendJSON([
        'success'            => true,
        'message'            => 'Appointment booked successfully',
        'appointment' => [
            'id'                 => $appointment_id,
            'appointment_number' => $appointment_number,
            'appointment_date'   => $appointment_date,
            'appointment_time'   => $appointment_time,
            'doctor_name'        => $doctor['full_name'],
            'reason_for_visit'   => $reason_for_visit,
            'status'             => 'scheduled',
            'qr_image'           => $qrData['qr_image'],
            'qr_url'             => $qrData['qr_url'],
            'token_hash'         => $qrData['token_hash'],
            'qr_expires_at'      => $qrData['expires_at']
        ]
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("book-appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to book appointment. Please try again.'], 500);
}
