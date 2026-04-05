<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $doctorId = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    if (empty($doctorId) || empty($date)) {
        sendJSON(['success' => true, 'booked_slots' => []]);
    }
    
    // Get booked time slots for the doctor on the specified date
    $query = "SELECT appointment_time 
              FROM appointments 
              WHERE doctor_id = :doctor_id 
              AND appointment_date = :date 
              AND status NOT IN ('cancelled', 'no_show', 'completed')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':doctor_id' => $doctorId,
        ':date' => $date
    ]);
    
    $bookedSlots = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bookedSlots[] = $row['appointment_time'];
    }
    
    sendJSON([
        'success' => true,
        'booked_slots' => $bookedSlots,
        'count' => count($bookedSlots)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-booked-slots.php: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error', 'booked_slots' => []], 500);
}
