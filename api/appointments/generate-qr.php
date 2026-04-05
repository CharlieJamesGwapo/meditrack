<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../utils/QRCodeGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);
$appointmentId = $data['appointment_id'] ?? '';

if (empty($appointmentId)) {
    sendJSON(['success' => false, 'message' => 'Appointment ID required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify appointment exists and user has access
    $role = getCurrentUserRole();
    $userId = getCurrentUserId();
    $profileId = $_SESSION['profile_id'];
    
    $whereCondition = '';
    if ($role === 'patient') {
        $whereCondition = 'AND a.patient_id = :profile_id';
    } elseif ($role === 'doctor') {
        $whereCondition = 'AND a.doctor_id = :profile_id';
    }
    
    $query = "SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name 
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN doctors d ON a.doctor_id = d.id
              WHERE a.id = :appointment_id $whereCondition";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':appointment_id', $appointmentId);
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->execute();
    
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or access denied'], 404);
    }
    
    // Generate QR code
    $qrGenerator = new QRCodeGenerator($db);
    $qrResult = $qrGenerator->generateQRCode($appointmentId);
    
    if ($qrResult) {
        sendJSON([
            'success' => true,
            'qr_code' => $qrResult,
            'appointment' => $appointment
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Failed to generate QR code'], 500);
    }

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>
