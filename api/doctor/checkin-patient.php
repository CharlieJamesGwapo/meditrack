<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$token_hash = sanitizeInput($input['token_hash'] ?? '');

if (empty($token_hash)) {
    sendJSON(['success' => false, 'message' => 'Token hash is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = $doctor['id'];

    $qrGenerator = new QRCodeGenerator($db);
    $result = $qrGenerator->validateQRCode($token_hash, $userId);

    if (!$result['valid']) {
        sendJSON(['success' => false, 'message' => $result['message']], 400);
    }

    // Verify appointment belongs to this doctor
    if ((int)$result['doctor_id'] !== (int)$doctor_id) {
        sendJSON(['success' => false, 'message' => 'This appointment is not assigned to you'], 403);
    }

    $appointment_id = $result['appointment_id'];

    // Update appointment status
    $db->prepare("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :aid")
       ->execute([':aid' => $appointment_id]);

    // Get patient details
    $stmt = $db->prepare("
        SELECT a.id as appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.reason_for_visit, a.status,
               p.id as patient_id, p.full_name as patient_name, p.date_of_birth, p.gender,
               p.blood_group, p.allergies, p.contact_number, p.emergency_contact_name, p.emergency_contact_number
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = :aid
        LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $appointment = $stmt->fetch();

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', 'CHECKIN', 'Appointments', $appointment_id, "Patient checked in via QR: " . ($appointment['patient_name'] ?? ''));

    sendJSON([
        'success'     => true,
        'message'     => 'Patient checked in successfully',
        'appointment' => $appointment
    ]);

} catch (Exception $e) {
    error_log("doctor checkin-patient error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to check in patient'], 500);
}
