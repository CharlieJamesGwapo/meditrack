<?php
/**
 * Get All Doctors for Admin View
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
    
    // Get all doctors with their details
    $query = "SELECT 
                d.id,
                d.full_name,
                d.email,
                d.contact_number,
                d.specialization,
                d.license_number,
                d.department_name,
                d.bio,
                u.status,
                u.created_at
              FROM doctors d
              LEFT JOIN users u ON d.user_id = u.id
              WHERE u.status != 'archived' OR u.status IS NULL
              ORDER BY d.full_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($doctors as &$doctor) {
        $doctor['created_date'] = date('M d, Y', strtotime($doctor['created_at']));
    }
    
    sendJSON([
        'success' => true,
        'doctors' => $doctors,
        'total' => count($doctors)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
