<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('doctor') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$weekStart = sanitizeInput($_GET['week_start'] ?? '');
$targetDoctorId = (int) ($_GET['doctor_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
}
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

try {
    $db     = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = $_SESSION['role'] ?? '';

    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doctor_id = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        $doctor_id = $targetDoctorId;
        if (!$doctor_id) {
            $doctor_id = (int) $db->query("SELECT id FROM doctors WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
        }
    }

    if (!$doctor_id) {
        sendJSON(['success' => true, 'week_start' => $weekStart, 'week_end' => $weekEnd, 'appointments' => []]);
    }

    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, p.full_name AS patient_name
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
         WHERE a.doctor_id = :did
           AND a.appointment_date BETWEEN :start AND :end
         ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->execute([':did' => $doctor_id, ':start' => $weekStart, ':end' => $weekEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSON([
        'success' => true,
        'doctor_id' => $doctor_id,
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'appointments' => $rows,
    ]);
} catch (Exception $e) {
    error_log("get-weekly-appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load weekly schedule'], 500);
}
