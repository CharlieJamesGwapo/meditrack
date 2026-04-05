<?php
/**
 * Get Doctor's Appointments
 * Returns appointments for logged-in doctor
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
    
    // For testing, use Dr. Lanie's user_id
    $userId = $_SESSION['user_id'] ?? 19;
    $filterDate = $_GET['date'] ?? null;
    
    // Get doctor ID
    $doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id AND is_archived = 0";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->execute([':user_id' => $userId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor not found'
        ]);
        exit();
    }
    
    $doctorId = $doctor['id'];
    
    // Build query
    $query = "SELECT 
                a.id,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason_for_visit,
                a.notes,
                a.priority,
                a.created_at,
                p.id as patient_id,
                p.full_name as patient_name,
                p.contact_number as patient_contact,
                p.date_of_birth as patient_dob,
                p.gender as patient_gender,
                p.blood_group,
                p.allergies,
                p.medical_history,
                p.email as patient_email
              FROM appointments a
              LEFT JOIN patients p ON a.patient_id = p.id
              WHERE a.doctor_id = :doctor_id";
    
    $params = [':doctor_id' => $doctorId];
    
    // Add date filter if provided
    if ($filterDate) {
        $query .= " AND a.appointment_date = :filter_date";
        $params[':filter_date'] = $filterDate;
    } else {
        // Default to today and future appointments
        $query .= " AND a.appointment_date >= CURDATE()";
    }
    
    $query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointments
    foreach ($appointments as &$appointment) {
        // Format date
        $appointment['formatted_date'] = date('F d, Y', strtotime($appointment['appointment_date']));
        
        // Format time
        $appointment['formatted_time'] = date('g:i A', strtotime($appointment['appointment_time']));
        
        // Calculate patient age
        if ($appointment['patient_dob']) {
            $dob = new DateTime($appointment['patient_dob']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
            $appointment['patient_age'] = $age;
        } else {
            $appointment['patient_age'] = null;
        }
        
        // Status display
        $appointment['status_display'] = strtoupper(str_replace('-', ' ', $appointment['status']));
        
        // Check if can add medical record
        $appointment['can_add_record'] = in_array($appointment['status'], ['checked-in', 'scheduled', 'confirmed']);
    }
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'count' => count($appointments)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
