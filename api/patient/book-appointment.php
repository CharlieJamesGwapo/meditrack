<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

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
    $stmt = $db->prepare("SELECT id, full_name FROM doctors WHERE status = 'active' LIMIT 1");
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

    // Check slot not taken
    $stmt = $db->prepare("SELECT id FROM appointments WHERE doctor_id = :did AND appointment_date = :date AND appointment_time = :time AND status NOT IN ('cancelled','no_show') LIMIT 1");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date, ':time' => $appointment_time]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 409);
    }

    // Patient doesn't already have appointment that day
    $stmt = $db->prepare("SELECT id FROM appointments WHERE patient_id = :pid AND appointment_date = :date AND status NOT IN ('cancelled','no_show') LIMIT 1");
    $stmt->execute([':pid' => $patient_id, ':date' => $appointment_date]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'You already have an appointment on this date'], 409);
    }

    // Check max_patients not exceeded
    $stmt = $db->prepare("SELECT COUNT(*) as booked FROM appointments WHERE doctor_id = :did AND appointment_date = :date AND status NOT IN ('cancelled','no_show')");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date]);
    $booked = (int) $stmt->fetch()['booked'];
    if ($booked >= $schedule['max_patients']) {
        sendJSON(['success' => false, 'message' => 'Doctor has reached maximum patients for this day'], 400);
    }

    $db->beginTransaction();

    // Generate appointment number: APT-YYYYMMDD-XXXX
    $date_part = date('Ymd', strtotime($appointment_date));
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE appointment_date = :date");
    $stmt->execute([':date' => $appointment_date]);
    $cnt = (int) $stmt->fetch()['cnt'] + 1;
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
            'token_hash'         => $qrData['token_hash'],
            'qr_expires_at'      => $qrData['expires_at']
        ]
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("book-appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to book appointment. Please try again.'], 500);
}
