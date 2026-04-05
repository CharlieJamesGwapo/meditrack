<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    sendJSON(['success' => false, 'message' => 'Unauthorized access'], 403);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all patients with their user information
    $query = "SELECT 
                p.*,
                u.username,
                u.email as user_email,
                u.status as user_status,
                u.created_at as registered_at,
                u.last_login
              FROM patients p
              LEFT JOIN users u ON p.user_id = u.id
              ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($patients as &$patient) {
        // Calculate age from date_of_birth
        if ($patient['date_of_birth']) {
            $dob = new DateTime($patient['date_of_birth']);
            $now = new DateTime();
            $patient['age'] = $now->diff($dob)->y;
        } else {
            $patient['age'] = null;
        }
        
        // Format dates
        $patient['formatted_dob'] = $patient['date_of_birth'] ? date('M j, Y', strtotime($patient['date_of_birth'])) : 'N/A';
        $patient['formatted_registered'] = $patient['registered_at'] ? date('M j, Y', strtotime($patient['registered_at'])) : 'N/A';
        $patient['formatted_last_login'] = $patient['last_login'] ? date('M j, Y g:i A', strtotime($patient['last_login'])) : 'Never';
        
        // Format profile image path
        if (!empty($patient['profile_image_path'])) {
            $patient['profile_image_url'] = '../../' . $patient['profile_image_path'];
        } else {
            $patient['profile_image_url'] = null;
        }
        
        // Full address
        $addressParts = array_filter([
            $patient['address'],
            $patient['barangay'],
            $patient['city'],
            $patient['province'],
            $patient['region']
        ]);
        $patient['full_address'] = implode(', ', $addressParts);
    }
    
    // Calculate statistics
    $stats = [
        'total' => count($patients),
        'active' => count(array_filter($patients, fn($p) => $p['user_status'] === 'active')),
        'male' => count(array_filter($patients, fn($p) => $p['gender'] === 'male')),
        'female' => count(array_filter($patients, fn($p) => $p['gender'] === 'female')),
        'new_this_month' => count(array_filter($patients, function($p) {
            return $p['registered_at'] && date('Y-m', strtotime($p['registered_at'])) === date('Y-m');
        }))
    ];
    
    sendJSON([
        'success' => true,
        'patients' => $patients,
        'stats' => $stats,
        'count' => count($patients)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-patients.php: " . $e->getMessage());
    sendJSON([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'patients' => [],
        'stats' => ['total' => 0, 'active' => 0, 'male' => 0, 'female' => 0, 'new_this_month' => 0]
    ], 500);
}
?>
