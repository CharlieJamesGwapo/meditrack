<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$token_hash = sanitizeInput($_GET['token'] ?? '');

if (empty($token_hash)) {
    sendJSON(['success' => false, 'message' => 'Token is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Look up the token and appointment info
    $stmt = $db->prepare("
        SELECT qt.token_hash, qt.expires_at, qt.is_used,
               a.id as appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit,
               p.full_name as patient_name, p.date_of_birth as patient_dob, p.gender as patient_gender,
               p.contact_number as patient_contact,
               d.full_name as doctor_name, d.specialization
        FROM qr_tokens qt
        JOIN appointments a ON qt.appointment_id = a.id
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE qt.token_hash = :token
        LIMIT 1
    ");
    $stmt->execute([':token' => $token_hash]);
    $result = $stmt->fetch();

    if (!$result) {
        sendJSON(['success' => false, 'message' => 'Invalid QR code. This token does not exist.'], 404);
    }

    // Check if already used
    if ($result['is_used']) {
        sendJSON(['success' => false, 'message' => 'This QR code has already been used for check-in.', 'already_used' => true]);
    }

    // Check if expired
    if (strtotime($result['expires_at']) < time()) {
        sendJSON(['success' => false, 'message' => 'This QR code has expired. Please request a new one from your dashboard.']);
    }

    // Check appointment status
    if ($result['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'This appointment has been cancelled.']);
    }

    if ($result['status'] === 'completed') {
        sendJSON(['success' => false, 'message' => 'This appointment has already been completed.']);
    }

    if ($result['status'] === 'checked_in') {
        sendJSON(['success' => false, 'message' => 'You have already checked in for this appointment.', 'already_used' => true]);
    }

    sendJSON([
        'success' => true,
        'appointment' => [
            'appointment_id'     => $result['appointment_id'],
            'appointment_number' => $result['appointment_number'],
            'appointment_date'   => $result['appointment_date'],
            'appointment_time'   => $result['appointment_time'],
            'status'             => $result['status'],
            'reason_for_visit'   => $result['reason_for_visit'],
            'patient_name'       => $result['patient_name'],
            'patient_dob'        => $result['patient_dob'],
            'patient_gender'     => $result['patient_gender'],
            'doctor_name'        => $result['doctor_name'],
            'specialization'     => $result['specialization']
        ]
    ]);

} catch (Exception $e) {
    error_log("verify-qr error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to verify QR code'], 500);
}
