<?php
/**
 * Get Archived Doctors
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
    
    // Get all archived doctors
    $query = "SELECT 
                d.id,
                d.full_name,
                d.email,
                d.contact_number,
                d.specialization,
                d.license_number,
                d.department_name,
                u.updated_at as archived_date
              FROM doctors d
              INNER JOIN users u ON d.user_id = u.id
              WHERE u.status = 'archived'
              ORDER BY u.updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($doctors as &$doctor) {
        $doctor['archived_date'] = date('M d, Y', strtotime($doctor['archived_date']));
    }
    
    sendJSON([
        'success' => true,
        'doctors' => $doctors,
        'total' => count($doctors)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
