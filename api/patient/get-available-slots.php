<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$date = sanitizeInput($_GET['date'] ?? '');
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    sendJSON(['success' => false, 'message' => 'Valid date (YYYY-MM-DD) is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get single active doctor
    $stmt = $db->prepare("SELECT id, full_name, specialization, consultation_fee FROM doctors WHERE status = 'active' LIMIT 1");
    $stmt->execute();
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No active doctor available'], 503);
    }
    $doctor_id = $doctor['id'];

    // day_of_week: 0=Sunday, 6=Saturday
    $day_of_week = (int) date('w', strtotime($date));

    $stmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :did AND day_of_week = :dow AND is_active = 1 LIMIT 1");
    $stmt->execute([':did' => $doctor_id, ':dow' => $day_of_week]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        sendJSON([
            'success' => true,
            'slots'   => [],
            'doctor'  => $doctor,
            'message' => 'Doctor is not available on this day'
        ]);
    }

    // Get booked slots
    $stmt = $db->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = :did AND appointment_date = :date AND status NOT IN ('cancelled','no_show')");
    $stmt->execute([':did' => $doctor_id, ':date' => $date]);
    $booked = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    // Generate all slots
    $slots = [];
    $now = time();
    $isToday = ($date === date('Y-m-d'));
    $start = strtotime($date . ' ' . $schedule['start_time']);
    $end   = strtotime($date . ' ' . $schedule['end_time']);
    $duration = (int) $schedule['slot_duration'];

    for ($t = $start; $t < $end; $t += $duration * 60) {
        $timeStr = date('H:i:s', $t);
        $isPast  = $isToday && $t <= $now;
        $isBooked = in_array($timeStr, $booked);

        $slots[] = [
            'time'      => $timeStr,
            'display'   => date('g:i A', $t),
            'available' => !$isBooked && !$isPast,
            'booked'    => $isBooked,
            'past'      => $isPast
        ];
    }

    sendJSON([
        'success'  => true,
        'slots'    => $slots,
        'doctor'   => $doctor,
        'schedule' => [
            'start_time'    => $schedule['start_time'],
            'end_time'      => $schedule['end_time'],
            'slot_duration' => $schedule['slot_duration'],
            'max_patients'  => $schedule['max_patients']
        ]
    ]);

} catch (Exception $e) {
    error_log("get-available-slots error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load available slots'], 500);
}
