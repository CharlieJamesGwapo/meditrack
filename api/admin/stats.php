<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $q = function($db, $sql) {
        return (int) $db->query($sql)->fetchColumn();
    };

    $total_patients  = $q($db, "SELECT COUNT(*) FROM patients");
    $today           = $q($db, "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
    $week            = $q($db, "SELECT COUNT(*) FROM appointments WHERE YEARWEEK(appointment_date,1) = YEARWEEK(CURDATE(),1)");
    $month           = $q($db, "SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())");
    $total_completed = $q($db, "SELECT COUNT(*) FROM appointments WHERE status='completed'");
    $total_cancelled = $q($db, "SELECT COUNT(*) FROM appointments WHERE status='cancelled'");

    sendJSON([
        'success' => true,
        'stats'   => [
            'total_patients'     => $total_patients,
            'today_appointments' => $today,
            'week_appointments'  => $week,
            'month_appointments' => $month,
            'total_completed'    => $total_completed,
            'total_cancelled'    => $total_cancelled
        ]
    ]);

} catch (Exception $e) {
    error_log("admin stats error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load stats'], 500);
}
