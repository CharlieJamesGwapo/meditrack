<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input      = json_decode(file_get_contents('php://input'), true);
$token_hash = sanitizeInput($input['token_hash'] ?? '');

if (empty($token_hash)) {
    sendJSON(['success' => false, 'message' => 'Token hash is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    $qrGenerator = new QRCodeGenerator($db);
    $result = $qrGenerator->validateQRCode($token_hash, $userId);

    if (!$result['valid']) {
        sendJSON(['success' => false, 'message' => $result['message']], 400);
    }

    $appointment_id = $result['appointment_id'];

    if (!in_array($result['status'], ['scheduled', 'in_progress'])) {
        sendJSON(['success' => false, 'message' => 'Appointment cannot be checked in (status: ' . $result['status'] . ')'], 400);
    }

    $db->prepare("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :aid")
       ->execute([':aid' => $appointment_id]);

    // Get appointment info
    $stmt = $db->prepare("
        SELECT a.id as appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.reason_for_visit, a.status,
               p.id as patient_id, p.full_name as patient_name, p.date_of_birth as patient_dob, p.gender as patient_gender,
               p.blood_group, p.allergies, p.contact_number as patient_contact,
               p.emergency_contact_name, p.emergency_contact_number,
               d.full_name as doctor_name, d.specialization
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = :aid LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $appointment = $stmt->fetch();

    logActivity($db, $userId, $_SESSION['username'] ?? '', getCurrentUserRole() ?? 'guest', 'CHECKIN', 'Appointments', $appointment_id, "Patient checked in via QR");

    sendJSON([
        'success'     => true,
        'message'     => 'Patient checked in successfully',
        'appointment' => $appointment
    ]);

} catch (Exception $e) {
    error_log("appointments/checkin error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to process check-in'], 500);
}
