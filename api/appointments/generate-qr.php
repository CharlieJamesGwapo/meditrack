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

$input          = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();
    $role   = getCurrentUserRole();

    // Verify appointment exists, not cancelled
    $stmt = $db->prepare("SELECT a.id, a.status, a.patient_id, a.doctor_id FROM appointments a WHERE a.id = :aid AND a.status != 'cancelled' LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or cancelled'], 404);
    }

    // If patient role, verify belongs to them
    if ($role === 'patient') {
        $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $patient = $stmt->fetch();
        if (!$patient || (int) $appointment['patient_id'] !== (int) $patient['id']) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
        }
    }

    $qrGenerator = new QRCodeGenerator($db);
    $qrData = $qrGenerator->generateQRCode($appointment_id);

    sendJSON([
        'success'    => true,
        'qr_image'   => $qrData['qr_image'],
        'token_hash' => $qrData['token_hash'],
        'expires_at' => $qrData['expires_at'],
        'qr_url'     => $qrData['qr_url']
    ]);

} catch (Exception $e) {
    error_log("generate-qr error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to generate QR code'], 500);
}
