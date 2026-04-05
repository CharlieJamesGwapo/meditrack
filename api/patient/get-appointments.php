<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];

    // Get patient ID
    $patientQuery = "SELECT id FROM patients WHERE user_id = :user_id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':user_id', $userId);
    $patientStmt->execute();
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    $patientId = $patient['id'];

    // Get appointments with QR tokens
    $query = "SELECT 
                a.id,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason_for_visit,
                a.notes,
                a.priority,
                d.full_name as doctor_name,
                d.specialization,
                d.department,
                d.profile_image as doctor_image,
                qt.token_hash,
                qt.qr_payload,
                qt.issued_at as qr_issued_at,
                qt.expires_at as qr_expires_at
              FROM appointments a
              LEFT JOIN doctors d ON a.doctor_id = d.id
              LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id
              WHERE a.patient_id = :patient_id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format appointments
    foreach ($appointments as &$appointment) {
        // Format date
        $appointment['formatted_date'] = date('F d, Y', strtotime($appointment['appointment_date']));
        
        // Format time
        $appointment['formatted_time'] = date('g:i A', strtotime($appointment['appointment_time']));
        
        // Doctor image URL
        if ($appointment['doctor_image']) {
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            $appointment['doctor_image_url'] = $baseUrl . '/uploads/' . $appointment['doctor_image'];
        } else {
            $appointment['doctor_image_url'] = null;
        }
        
        // QR Code data - generate if not exists
        if (!$appointment['token_hash'] && $appointment['status'] != 'cancelled' && $appointment['status'] != 'completed') {
            // QR code needs to be generated
            $appointment['qr_code_url'] = null;
            $appointment['needs_qr'] = true;
        } else if ($appointment['qr_payload']) {
            // QR code exists - create data URL
            $appointment['qr_code_url'] = $appointment['qr_payload'];
            $appointment['needs_qr'] = false;
        } else {
            $appointment['qr_code_url'] = null;
            $appointment['needs_qr'] = false;
        }
        
        // Status colors
        $statusColors = [
            'pending' => 'yellow',
            'scheduled' => 'blue',
            'confirmed' => 'blue',
            'checked_in' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red'
        ];
        $appointment['status_color'] = $statusColors[$appointment['status']] ?? 'gray';
        
        // Status display name
        $appointment['status_display'] = strtoupper(str_replace('-', ' ', $appointment['status']));
    }

    // Count upcoming appointments
    $upcomingQuery = "SELECT COUNT(*) as count FROM appointments 
                      WHERE patient_id = :patient_id 
                      AND status = 'scheduled' 
                      AND appointment_date >= CURDATE()";
    $upcomingStmt = $db->prepare($upcomingQuery);
    $upcomingStmt->bindParam(':patient_id', $patientId);
    $upcomingStmt->execute();
    $upcomingCount = $upcomingStmt->fetch(PDO::FETCH_ASSOC)['count'];

    sendJSON([
        'success' => true,
        'appointments' => $appointments,
        'upcoming_count' => $upcomingCount
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
