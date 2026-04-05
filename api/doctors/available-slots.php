<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

$doctor_id = $_GET['doctor_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$doctor_id || !$date) {
    sendJSON(['success' => false, 'message' => 'Doctor ID and date required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $dayOfWeek = date('l', strtotime($date));

    // Get doctor schedule
    $scheduleQuery = "SELECT * FROM doctor_schedules 
                      WHERE doctor_id = :doctor_id 
                      AND day_of_week = :day_of_week 
                      AND is_active = 1";
    $scheduleStmt = $db->prepare($scheduleQuery);
    $scheduleStmt->execute([
        ':doctor_id' => $doctor_id,
        ':day_of_week' => $dayOfWeek
    ]);

    if ($scheduleStmt->rowCount() === 0) {
        sendJSON(['success' => true, 'slots' => [], 'message' => 'Doctor not available on this day']);
    }

    $schedule = $scheduleStmt->fetch();

    // Get booked appointments
    $bookedQuery = "SELECT appointment_time FROM appointments 
                    WHERE doctor_id = :doctor_id 
                    AND appointment_date = :date 
                    AND status NOT IN ('cancelled', 'no_show')";
    $bookedStmt = $db->prepare($bookedQuery);
    $bookedStmt->execute([':doctor_id' => $doctor_id, ':date' => $date]);
    $bookedTimes = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate time slots
    $slots = [];
    $startTime = strtotime($schedule['start_time']);
    $endTime = strtotime($schedule['end_time']);
    $slotDuration = $schedule['slot_duration'] * 60; // Convert to seconds

    while ($startTime < $endTime) {
        $timeSlot = date('H:i:s', $startTime);
        $slots[] = [
            'time' => $timeSlot,
            'display' => date('h:i A', $startTime),
            'available' => !in_array($timeSlot, $bookedTimes)
        ];
        $startTime += $slotDuration;
    }

    sendJSON([
        'success' => true,
        'slots' => $slots,
        'schedule' => $schedule
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
