<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Notifier.php';
require_once __DIR__ . '/../../utils/Mailer.php';

$CANCEL_CUTOFF_HOURS = 2;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, full_name FROM patients WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    $stmt = $db->prepare("
        SELECT a.id, a.status, a.appointment_number, a.appointment_date, a.appointment_time,
               u.email AS patient_email
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE a.id = :aid AND a.patient_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id, ':pid' => $patient['id']]);
    $appt = $stmt->fetch();

    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if ($appt['status'] !== 'scheduled') {
        sendJSON(['success' => false, 'message' => 'Only scheduled appointments can be cancelled'], 400);
    }

    // Cutoff enforcement: must be at least CANCEL_CUTOFF_HOURS before appointment
    $apptTs   = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
    $cutoffTs = $apptTs - ($CANCEL_CUTOFF_HOURS * 3600);
    if (time() > $cutoffTs) {
        sendJSON([
            'success' => false,
            'message' => 'Cancellation window closed. Please contact the clinic.',
        ], 400);
    }

    $db->prepare("
        UPDATE appointments
           SET status = 'cancelled', cancelled_at = NOW(),
               cancelled_by = 'patient', cancel_reason = NULL
         WHERE id = :aid
    ")->execute([':aid' => $appointment_id]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'patient', 'UPDATE', 'Appointments', $appointment_id, "Appointment cancelled by patient");

    Notifier::notify(
        $db, $userId, 'appointment_cancelled_by_patient',
        'Appointment cancelled',
        "Your appointment #{$appt['appointment_number']} on {$appt['appointment_date']} has been cancelled.",
        'patient-dashboard.html'
    );

    try {
        if (!empty($appt['patient_email'])) {
            (new Mailer())->sendCancellationConfirmation(
                $appt['patient_email'],
                $patient['full_name'],
                $appt['appointment_number'],
                $appt['appointment_date'],
                $appt['appointment_time']
            );
        }
    } catch (Exception $e) {
        error_log("cancel-appointment email error: " . $e->getMessage());
    }

    sendJSON(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    error_log("cancel-appointment (patient) error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to cancel appointment'], 500);
}
