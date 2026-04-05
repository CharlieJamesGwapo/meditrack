<?php
/**
 * Patient Self Check-In via QR Token
 * This endpoint does NOT require login - it validates the QR token itself
 * Used by qr-checkin.html when patients scan their QR code
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../utils/QRCodeGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);
$tokenHash = $data['token_hash'] ?? '';

if (empty($tokenHash)) {
    sendJSON(['success' => false, 'message' => 'QR token required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate QR code token (pass null for userId since patient may not be logged in)
    $qrGenerator = new QRCodeGenerator($db);
    $validation = $qrGenerator->validateQRCode($tokenHash, null);

    if (!$validation['valid']) {
        sendJSON(['success' => false, 'message' => $validation['message']], 400);
    }

    $appointment_id = $validation['appointment_id'];

    // Check if appointment status allows check-in
    $allowedStatuses = ['scheduled', 'confirmed'];
    if (!in_array($validation['status'], $allowedStatuses)) {
        $statusMsg = $validation['status'] === 'checked_in'
            ? 'You have already checked in for this appointment'
            : 'This appointment cannot be checked in (status: ' . $validation['status'] . ')';
        sendJSON(['success' => false, 'message' => $statusMsg], 400);
    }

    // Check if appointment is today
    $today = date('Y-m-d');
    if ($validation['appointment_date'] !== $today) {
        $aptDate = date('M d, Y', strtotime($validation['appointment_date']));
        sendJSON(['success' => false, 'message' => "This appointment is scheduled for {$aptDate}. You can only check in on the day of your appointment."], 400);
    }

    // Update appointment status
    $updateQuery = "UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $appointment_id]);

    // Get full appointment details for display
    $detailQuery = "SELECT a.*,
                    p.full_name as patient_name,
                    p.contact_number as patient_contact,
                    d.full_name as doctor_name,
                    d.specialization,
                    d.department,
                    d.user_id as doctor_user_id
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = :id";
    $detailStmt = $db->prepare($detailQuery);
    $detailStmt->execute([':id' => $appointment_id]);
    $appointment = $detailStmt->fetch();

    // Create notification for doctor
    if ($appointment) {
        $patientName = $appointment['patient_name'] ?? 'A patient';
        $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id)
                       VALUES (:doctor_user_id, 'appointment', 'Patient Checked In', :message, :appointment_id)";
        $notifStmt = $db->prepare($notifQuery);
        $notifStmt->execute([
            ':doctor_user_id' => $appointment['doctor_user_id'],
            ':message' => "Patient {$patientName} has checked in via QR code.",
            ':appointment_id' => $appointment_id
        ]);
    }

    // Log audit (no user ID since this is self-service)
    try {
        logAudit($db, null, 'self_checkin_appointment', 'appointments', $appointment_id, 'Patient self check-in via QR code');
    } catch (Exception $e) {
        // Don't fail the check-in if audit logging fails
    }

    sendJSON([
        'success' => true,
        'message' => 'You have been successfully checked in!',
        'appointment' => [
            'id' => $appointment['id'] ?? $appointment_id,
            'patient_name' => $appointment['patient_name'] ?? '',
            'doctor_name' => $appointment['doctor_name'] ?? '',
            'appointment_date' => $appointment['appointment_date'] ?? '',
            'appointment_time' => $appointment['appointment_time'] ?? '',
            'specialization' => $appointment['specialization'] ?? '',
            'department' => $appointment['department'] ?? '',
            'reason' => $appointment['reason_for_visit'] ?? $appointment['reason'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    error_log("Self check-in error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error. Please visit the reception desk for assistance.'], 500);
}
?>
