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

// Only reception and admin can check in patients
if (!in_array(getCurrentUserRole(), ['reception', 'admin'])) {
    sendJSON(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

$data = json_decode(file_get_contents("php://input"), true);
$tokenHash = $data['token_hash'] ?? '';

if (empty($tokenHash)) {
    sendJSON(['success' => false, 'message' => 'QR token required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate QR code
    $qrGenerator = new QRCodeGenerator($db);
    $validation = $qrGenerator->validateQRCode($tokenHash, getCurrentUserId());

    if (!$validation['valid']) {
        sendJSON(['success' => false, 'message' => $validation['message']], 400);
    }

    $appointment_id = $validation['appointment_id'];

    // Check if appointment status allows check-in
    if (!in_array($validation['status'], ['scheduled', 'confirmed'])) {
        sendJSON(['success' => false, 'message' => 'Appointment already processed'], 400);
    }

    // Update appointment status
    $updateQuery = "UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $appointment_id]);

    // Get patient details
    $detailQuery = "SELECT a.*, 
                    p.full_name as patient_name, 
                    p.contact_number as patient_contact,
                    p.date_of_birth as patient_dob,
                    p.blood_group,
                    p.allergies,
                    d.full_name as doctor_name, 
                    d.specialization,
                    d.department
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = :id";
    $detailStmt = $db->prepare($detailQuery);
    $detailStmt->execute([':id' => $appointment_id]);
    $appointment = $detailStmt->fetch(PDO::FETCH_ASSOC);

    // Create notification for doctor
    $patientName = $appointment['patient_name'] ?? 'Unknown';
    $notifQuery = "INSERT INTO notifications (user_id, type, title, message, related_id)
                   VALUES ((SELECT user_id FROM doctors WHERE id = :doctor_id), 'appointment', 'Patient Checked In', :message, :appointment_id)";
    $notifStmt = $db->prepare($notifQuery);
    $notifStmt->execute([
        ':doctor_id' => $validation['doctor_id'],
        ':message' => "Patient $patientName has checked in.",
        ':appointment_id' => $appointment_id
    ]);

    // Log audit
    logAudit($db, getCurrentUserId(), 'checkin_appointment', 'appointments', $appointment_id, 'Patient checked in');

    sendJSON([
        'success' => true,
        'message' => 'Patient checked in successfully',
        'appointment' => $appointment
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
