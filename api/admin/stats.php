<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get total patients - Count from patients table (REAL DATA)
    $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    $totalPatients = $stmt->fetch()['count'];
    
    // Get total doctors - Count from doctors table (REAL DATA - YOU HAVE 0!)
    $stmt = $db->query("SELECT COUNT(*) as count FROM doctors");
    $totalDoctors = $stmt->fetch()['count'];
    
    // Get today's appointments
    $stmt = $db->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
    $todayAppointments = $stmt->fetch()['count'];
    
    // Get total visits this month
    $stmt = $db->query("SELECT COUNT(*) as count FROM appointments WHERE MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())");
    $totalVisits = $stmt->fetch()['count'];
    
    $stats = [
        'totalPatients' => (int)$totalPatients,
        'totalDoctors' => (int)$totalDoctors,
        'todayAppointments' => (int)$todayAppointments,
        'totalVisits' => (int)$totalVisits,
        'success' => true
    ];
    
    echo json_encode($stats);
    
} catch(Exception $e) {
    echo json_encode([
        'totalPatients' => 0,
        'totalDoctors' => 0,
        'todayAppointments' => 0,
        'totalVisits' => 0,
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
