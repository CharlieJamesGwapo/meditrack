<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (empty($_GET['doctor_id']) || empty($_GET['date'])) {
    sendJSON(['success' => false, 'message' => 'Doctor ID and date are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $doctorId = $_GET['doctor_id'];
    $date = $_GET['date'];

    // Get booked slots for this doctor on this date
    $bookedQuery = "SELECT appointment_time FROM appointments 
                    WHERE doctor_id = :doctor_id 
                    AND appointment_date = :date 
                    AND status NOT IN ('cancelled', 'no_show', 'completed')";
    $bookedStmt = $db->prepare($bookedQuery);
    $bookedStmt->execute([
        ':doctor_id' => $doctorId,
        ':date' => $date
    ]);
    
    $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get day of week for the requested date
    $dayOfWeek = date('l', strtotime($date));

    // Fetch doctor schedule for this day
    $schedQuery = "SELECT start_time, end_time, slot_duration FROM doctor_schedules
                   WHERE doctor_id = :doctor_id AND day_of_week = :day_of_week AND is_active = 1
                   LIMIT 1";
    $schedStmt = $db->prepare($schedQuery);
    $schedStmt->execute([':doctor_id' => $doctorId, ':day_of_week' => $dayOfWeek]);
    $schedule = $schedStmt->fetch(PDO::FETCH_ASSOC);

    // Default to 8AM-5PM / 30min if no schedule set
    $startHour = $schedule ? $schedule['start_time'] : '08:00:00';
    $endHour = $schedule ? $schedule['end_time'] : '17:00:00';
    $slotDuration = $schedule ? (int)$schedule['slot_duration'] : 30;

    $allSlots = [];
    $startTime = strtotime($startHour);
    $endTime = strtotime($endHour);

    while ($startTime < $endTime) {
        $timeSlot = date('H:i:s', $startTime);
        $displayTime = date('g:i A', $startTime);

        $allSlots[] = [
            'time' => $timeSlot,
            'display' => $displayTime,
            'available' => !in_array($timeSlot, $bookedSlots)
        ];

        $startTime = strtotime("+{$slotDuration} minutes", $startTime);
    }

    sendJSON([
        'success' => true,
        'slots' => $allSlots
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
