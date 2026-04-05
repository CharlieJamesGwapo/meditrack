<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get All Patients for Admin View
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Check authentication - admin and reception can view patients
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['admin', 'reception'])) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all patients with their details
    $query = "SELECT 
                p.id,
                p.full_name,
                p.email,
                p.contact_number,
                p.date_of_birth,
                p.gender,
                p.blood_group,
                p.address,
                p.city,
                p.province,
                p.created_at,
                u.status
              FROM patients p
              LEFT JOIN users u ON p.user_id = u.id AND u.role = 'patient'
              WHERE u.status != 'archived' OR u.status IS NULL
              ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($patients as &$patient) {
        $patient['age'] = null;
        if ($patient['date_of_birth']) {
            $dob = new DateTime($patient['date_of_birth']);
            $now = new DateTime();
            $patient['age'] = $now->diff($dob)->y;
        }
        
        $patient['created_date'] = date('M d, Y', strtotime($patient['created_at']));
    }
    
    sendJSON([
        'success' => true,
        'patients' => $patients,
        'total' => count($patients)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
