<?php
/**
 * Restore Archived Doctor
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $doctorId = $data['doctor_id'] ?? null;
    
    if (!$doctorId) {
        sendJSON(['success' => false, 'message' => 'Doctor ID required'], 400);
    }
    
    // Get doctor's user_id
    $query = "SELECT user_id, full_name FROM doctors WHERE id = :doctor_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':doctor_id' => $doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }
    
    // Update user status to active
    $updateQuery = "UPDATE users SET status = 'active' WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':user_id' => $doctor['user_id']]);
    
    // Log the action
    logAudit($db, $_SESSION['user_id'], 'restore_doctor', 'doctors', $doctorId, 
             "Restored doctor: {$doctor['full_name']}");
    
    sendJSON([
        'success' => true,
        'message' => 'Doctor restored successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
