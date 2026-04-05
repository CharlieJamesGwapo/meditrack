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

    $stmt = $db->query("SELECT d.id, d.full_name, d.specialization, d.license_number, d.status, u.email
                        FROM doctors d JOIN users u ON d.user_id = u.id
                        WHERE d.status = 'active' LIMIT 1");
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No active doctor found'], 404);
    }

    $stmt = $db->prepare("SELECT day_of_week, start_time, end_time, slot_duration, max_patients, is_active
                          FROM doctor_schedules WHERE doctor_id = :did ORDER BY day_of_week");
    $stmt->execute([':did' => $doctor['id']]);
    $rows = $stmt->fetchAll();

    $scheduleMap = [];
    foreach ($rows as $row) {
        $scheduleMap[(int) $row['day_of_week']] = [
            'day_of_week'   => (int) $row['day_of_week'],
            'start_time'    => $row['start_time'],
            'end_time'      => $row['end_time'],
            'slot_duration' => (int) $row['slot_duration'],
            'max_patients'  => (int) $row['max_patients'],
            'is_active'     => (bool) $row['is_active']
        ];
    }

    sendJSON(['success' => true, 'doctor' => $doctor, 'schedule' => $scheduleMap]);

} catch (Exception $e) {
    error_log("admin get-doctor-schedule error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load schedule'], 500);
}
