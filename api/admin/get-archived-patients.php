<?php
/**
 * Get Archived Patients
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all archived patients
    $query = "SELECT 
                p.id,
                p.full_name,
                p.email,
                p.contact_number,
                p.date_of_birth,
                p.gender,
                p.blood_group,
                u.updated_at as archived_date
              FROM patients p
              INNER JOIN users u ON p.user_id = u.id
              WHERE u.status = 'archived'
              ORDER BY u.updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($patients as &$patient) {
        $patient['archived_date'] = date('M d, Y', strtotime($patient['archived_date']));
    }
    
    sendJSON([
        'success' => true,
        'patients' => $patients,
        'total' => count($patients)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
