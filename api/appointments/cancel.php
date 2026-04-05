<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['appointment_id'])) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $appointmentId = $data['appointment_id'];
    $userId = getCurrentUserId();
    $role = getCurrentUserRole();

    // Verify appointment exists
    $checkQuery = "SELECT a.id, a.status, a.patient_id, a.doctor_id
                   FROM appointments a WHERE a.id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':id' => $appointmentId]);
    $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if ($appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Appointment is already cancelled'], 400);
    }

    if ($appointment['status'] === 'completed') {
        sendJSON(['success' => false, 'message' => 'Cannot cancel a completed appointment'], 400);
    }

    // Update status to cancelled
    $updateQuery = "UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $appointmentId]);

    // Notify patient about cancellation
    try {
        $aptQuery = "SELECT a.appointment_date, a.appointment_time,
                     d.full_name as doctor_name, p.user_id as patient_user_id
                     FROM appointments a
                     JOIN doctors d ON a.doctor_id = d.id
                     JOIN patients p ON a.patient_id = p.id
                     WHERE a.id = :id";
        $aptStmt = $db->prepare($aptQuery);
        $aptStmt->execute([':id' => $appointmentId]);
        $aptData = $aptStmt->fetch(PDO::FETCH_ASSOC);

        if ($aptData) {
            $dateFormatted = date('M d, Y', strtotime($aptData['appointment_date']));
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id)
                           VALUES (:user_id, 'cancellation', 'Appointment Cancelled',
                           :message, :apt_id)";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->execute([
                ':user_id' => $aptData['patient_user_id'],
                ':message' => "Your appointment with Dr. {$aptData['doctor_name']} on {$dateFormatted} has been cancelled. Please book a new appointment.",
                ':apt_id' => $appointmentId
            ]);
        }
    } catch (Exception $e) {
        // Don't fail if notification fails
    }

    logAudit($db, $userId, 'cancel_appointment', 'appointments', $appointmentId, 'Appointment cancelled by ' . $role);

    sendJSON(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
