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
                    AND status NOT IN ('cancelled')";
    $bookedStmt = $db->prepare($bookedQuery);
    $bookedStmt->execute([
        ':doctor_id' => $doctorId,
        ':date' => $date
    ]);
    
    $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate all possible time slots (8 AM to 5 PM, every 30 minutes)
    $allSlots = [];
    $startTime = strtotime('08:00');
    $endTime = strtotime('17:00');
    
    while ($startTime <= $endTime) {
        $timeSlot = date('H:i:s', $startTime);
        $displayTime = date('g:i A', $startTime);
        
        $allSlots[] = [
            'time' => $timeSlot,
            'display' => $displayTime,
            'available' => !in_array($timeSlot, $bookedSlots)
        ];
        
        $startTime = strtotime('+30 minutes', $startTime);
    }

    sendJSON([
        'success' => true,
        'slots' => $allSlots
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
