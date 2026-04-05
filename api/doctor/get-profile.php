<?php
/**
 * Get Doctor Profile - Returns logged-in doctor's information
 */

session_start();

require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // For testing, get the first doctor if no session
    // In production, check session: if (!isset($_SESSION['user_id']))
    $userId = $_SESSION['user_id'] ?? 19; // Default to Dr. Lanie's user_id for testing
    
    // Get doctor profile with department info
    $query = "SELECT 
                d.id,
                d.user_id,
                d.first_name,
                d.middle_name,
                d.last_name,
                d.full_name,
                d.specialization,
                d.qualification,
                d.license_number,
                d.contact_number,
                d.email,
                d.department_id,
                d.consultation_fee,
                d.experience_years,
                d.profile_image,
                d.bio,
                d.status,
                dept.name as department_name,
                u.username,
                u.email as user_email
              FROM doctors d
              LEFT JOIN users u ON d.user_id = u.id
              LEFT JOIN departments dept ON d.department_id = dept.id
              WHERE d.user_id = :user_id AND d.is_archived = 0";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        // Format profile image URL
        if ($profile['profile_image']) {
            $profile['profile_image_url'] = '../uploads/' . $profile['profile_image'];
        } else {
            $profile['profile_image_url'] = null;
        }
        
        // Use email from doctors table or users table
        if (!$profile['email'] && isset($profile['user_email'])) {
            $profile['email'] = $profile['user_email'];
        }
        
        // Format phone number
        $profile['phone'] = $profile['contact_number'];
        
        // Format department
        $profile['department'] = $profile['department_name'] ?: 'Not Assigned';
        
        echo json_encode([
            'success' => true,
            'profile' => $profile,
            'doctor' => $profile // Alias for compatibility
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor profile not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
