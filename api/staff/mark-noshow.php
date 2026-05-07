<?php
/**
 * POST /api/staff/mark-noshow.php
 * Body: { appointment_id }
 * Mark an appointment as no_show. Frees the slot immediately.
 *
 * Auth: staff, doctor (if owner), admin.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('staff') || hasRole('doctor') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = getCurrentUserRole();

    $stmt = $db->prepare("SELECT id, doctor_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    // Doctor can only mark their own; staff/admin can mark any.
    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doc = $stmt->fetch();
        if (!$doc || (int) $appt['doctor_id'] !== (int) $doc['id']) {
            sendJSON(['success' => false, 'message' => 'You can only mark your own patients as no-show'], 403);
        }
    }

    if (!in_array($appt['status'], ['scheduled', 'checked_in'], true)) {
        sendJSON(['success' => false, 'message' => 'Only scheduled or checked-in appointments can be marked as no-show'], 400);
    }

    $db->prepare("UPDATE appointments SET status = 'no_show', updated_at = NOW() WHERE id = :aid")
       ->execute([':aid' => $appointment_id]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', $role ?? 'staff', 'UPDATE', 'Appointments', $appointment_id, "Marked as no-show");

    sendJSON(['success' => true, 'message' => 'Patient marked as no-show. The slot is now free.']);
} catch (Exception $e) {
    error_log("mark-noshow error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to mark as no-show'], 500);
}
