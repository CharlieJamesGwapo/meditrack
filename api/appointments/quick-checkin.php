<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only reception and admin can check in patients
if (!in_array(getCurrentUserRole(), ['reception', 'admin'])) {
    sendJSON(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

$data = json_decode(file_get_contents("php://input"), true);
$appointmentId = $data['appointment_id'] ?? '';

if (empty($appointmentId)) {
    sendJSON(['success' => false, 'message' => 'Appointment ID required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get appointment details and validate
    $appointmentQuery = "SELECT a.*, 
                        p.full_name as patient_name, 
                        p.contact_number as patient_contact,
                        d.full_name as doctor_name, 
                        d.specialization,
                        d.user_id as doctor_user_id
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.id
                        JOIN doctors d ON a.doctor_id = d.id
                        WHERE a.id = :id";
    $appointmentStmt = $db->prepare($appointmentQuery);
    $appointmentStmt->execute([':id' => $appointmentId]);
    $appointment = $appointmentStmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    // Check if appointment is today
    $today = date('Y-m-d');
    if ($appointment['appointment_date'] !== $today) {
        sendJSON(['success' => false, 'message' => 'Can only check in appointments for today'], 400);
    }

    // Check if appointment status allows check-in
    if (!in_array($appointment['status'], ['scheduled', 'confirmed'])) {
        sendJSON(['success' => false, 'message' => 'Appointment already processed (status: ' . $appointment['status'] . ')'], 400);
    }

    // Update appointment status
    $updateQuery = "UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([':id' => $appointmentId]);

    if ($result) {
        // Create notification for doctor
        $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id) 
                       VALUES (:doctor_user_id, 'appointment', 'Patient Checked In', :message, :appointment_id)";
        $notifStmt = $db->prepare($notifQuery);
        $notifStmt->execute([
            ':doctor_user_id' => $appointment['doctor_user_id'],
            ':message' => "Patient {$appointment['patient_name']} has checked in for their appointment.",
            ':appointment_id' => $appointmentId
        ]);

        // Log audit
        logAudit($db, getCurrentUserId(), 'quick_checkin_appointment', 'appointments', $appointmentId, 'Quick check-in by reception');

        sendJSON([
            'success' => true,
            'message' => 'Patient checked in successfully',
            'appointment' => [
                'id' => $appointment['id'],
                'patient_id' => $appointment['patient_id'],
                'patient_name' => $appointment['patient_name'],
                'doctor_name' => $appointment['doctor_name'],
                'appointment_time' => $appointment['appointment_time']
            ]
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Failed to update appointment status'], 500);
    }

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>
