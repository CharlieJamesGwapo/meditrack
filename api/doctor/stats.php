<?php
/**
 * Get Doctor Dashboard Statistics
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    // Get doctor ID
    $doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->execute([':user_id' => $userId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }
    
    $doctorId = $doctor['id'];
    
    // Count today's appointments
    $todayQuery = "SELECT COUNT(*) as count FROM appointments 
                   WHERE doctor_id = :doctor_id 
                   AND appointment_date = CURDATE()
                   AND status NOT IN ('cancelled')";
    $todayStmt = $db->prepare($todayQuery);
    $todayStmt->execute([':doctor_id' => $doctorId]);
    $today = $todayStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count checked-in appointments
    $checkedInQuery = "SELECT COUNT(*) as count FROM appointments 
                       WHERE doctor_id = :doctor_id 
                       AND status = 'checked-in'
                       AND appointment_date = CURDATE()";
    $checkedInStmt = $db->prepare($checkedInQuery);
    $checkedInStmt->execute([':doctor_id' => $doctorId]);
    $checkedIn = $checkedInStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count pending appointments
    $pendingQuery = "SELECT COUNT(*) as count FROM appointments 
                     WHERE doctor_id = :doctor_id 
                     AND status = 'pending'";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([':doctor_id' => $doctorId]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count completed appointments
    $completedQuery = "SELECT COUNT(*) as count FROM appointments 
                       WHERE doctor_id = :doctor_id 
                       AND status = 'completed'";
    $completedStmt = $db->prepare($completedQuery);
    $completedStmt->execute([':doctor_id' => $doctorId]);
    $completed = $completedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    sendJSON([
        'success' => true,
        'stats' => [
            'today' => (int)$today,
            'checked_in' => (int)$checkedIn,
            'pending' => (int)$pending,
            'completed' => (int)$completed
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
