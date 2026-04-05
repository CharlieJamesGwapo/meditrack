<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
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
    $did = $doctor['id'];

    $q = function($db, $sql, $params) {
        $s = $db->prepare($sql);
        $s->execute($params);
        return (int) $s->fetchColumn();
    };

    $today_appointments = $q($db, "SELECT COUNT(*) FROM appointments WHERE doctor_id = :did AND appointment_date = CURDATE() AND status != 'cancelled'", [':did' => $did]);
    $today_checked_in   = $q($db, "SELECT COUNT(*) FROM appointments WHERE doctor_id = :did AND appointment_date = CURDATE() AND status = 'checked_in'", [':did' => $did]);
    $today_completed    = $q($db, "SELECT COUNT(*) FROM appointments WHERE doctor_id = :did AND appointment_date = CURDATE() AND status = 'completed'", [':did' => $did]);
    $total_patients     = $q($db, "SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = :did", [':did' => $did]);
    $total_completed    = $q($db, "SELECT COUNT(*) FROM appointments WHERE doctor_id = :did AND status = 'completed'", [':did' => $did]);

    sendJSON([
        'success' => true,
        'stats'   => [
            'today_appointments' => $today_appointments,
            'today_checked_in'   => $today_checked_in,
            'today_completed'    => $today_completed,
            'total_patients'     => $total_patients,
            'total_completed'    => $total_completed
        ]
    ]);

} catch (Exception $e) {
    error_log("doctor stats error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load stats'], 500);
}
