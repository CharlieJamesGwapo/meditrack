<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get search and filter parameters
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $department = isset($_GET['department']) ? $_GET['department'] : '';

    // Build query with parameters
    $params = [];
    $query = "SELECT 
                d.id,
                d.full_name,
                d.specialization,
                d.department,
                d.qualification,
                d.experience_years,
                d.consultation_fee,
                d.profile_image,
                d.bio,
                d.status,
                u.email,
                d.contact_number
              FROM doctors d
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.status = 'active'";
    
    if (!empty($search)) {
        $query .= " AND (d.full_name LIKE :search OR d.specialization LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($department)) {
        $query .= " AND d.department = :department";
        $params[':department'] = $department;
    }
    
    $query .= " ORDER BY d.full_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Doctors query executed. Found: " . count($doctors) . " doctors");

    // Calculate average rating for each doctor (placeholder - implement rating system later)
    foreach ($doctors as &$doctor) {
        $doctor['rating'] = 4.5 + (rand(0, 8) / 10); // Temporary random rating 4.5-5.3
        $doctor['rating'] = round($doctor['rating'], 1);
        
        // Format profile image path - use APP_URL for absolute path
        if (!empty($doctor['profile_image'])) {
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            $doctor['profile_image_url'] = $baseUrl . '/uploads/' . $doctor['profile_image'];
        } else {
            $doctor['profile_image_url'] = null;
        }
        
        // Ensure all fields are set
        $doctor['full_name'] = $doctor['full_name'] ?? 'Unknown Doctor';
        $doctor['specialization'] = $doctor['specialization'] ?? 'General';
        $doctor['department'] = $doctor['department'] ?? 'General';
        $doctor['experience_years'] = $doctor['experience_years'] ?? 0;
        $doctor['consultation_fee'] = $doctor['consultation_fee'] ?? 0;
    }

    sendJSON([
        'success' => true,
        'doctors' => $doctors,
        'count' => count($doctors),
        'message' => count($doctors) > 0 ? 'Doctors loaded successfully' : 'No doctors available'
    ]);

} catch (Exception $e) {
    error_log("Error in get-doctors.php: " . $e->getMessage());
    sendJSON([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'doctors' => [],
        'count' => 0
    ], 500);
}
