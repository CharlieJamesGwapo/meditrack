<?php
/**
 * Restore Archived Patient
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
    $patientId = $data['patient_id'] ?? null;
    
    if (!$patientId) {
        sendJSON(['success' => false, 'message' => 'Patient ID required'], 400);
    }
    
    // Get patient's user_id
    $query = "SELECT user_id, full_name FROM patients WHERE id = :patient_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':patient_id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }
    
    // Update user status to active
    $updateQuery = "UPDATE users SET status = 'active' WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':user_id' => $patient['user_id']]);
    
    // Log the action
    logAudit($db, $_SESSION['user_id'], 'restore_patient', 'patients', $patientId, 
             "Restored patient: {$patient['full_name']}");
    
    sendJSON([
        'success' => true,
        'message' => 'Patient restored successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
