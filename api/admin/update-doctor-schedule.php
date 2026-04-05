<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input     = json_decode(file_get_contents('php://input'), true);
$schedules = $input['schedules'] ?? [];

if (!is_array($schedules)) {
    sendJSON(['success' => false, 'message' => 'schedules array is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->query("SELECT id FROM doctors WHERE status = 'active' LIMIT 1");
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No active doctor found'], 404);
    }
    $doctor_id = $doctor['id'];

    $db->beginTransaction();

    $db->prepare("DELETE FROM doctor_schedules WHERE doctor_id = :did")
       ->execute([':did' => $doctor_id]);

    $insert = $db->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients, is_active)
        VALUES (:did, :dow, :start, :end, :slot, :max, :active)");

    foreach ($schedules as $row) {
        $dow = isset($row['day_of_week']) ? (int) $row['day_of_week'] : null;
        if ($dow === null || $dow < 0 || $dow > 6) continue;

        $insert->execute([
            ':did'    => $doctor_id,
            ':dow'    => $dow,
            ':start'  => $row['start_time'] ?? '08:00:00',
            ':end'    => $row['end_time'] ?? '17:00:00',
            ':slot'   => isset($row['slot_duration']) ? (int) $row['slot_duration'] : 30,
            ':max'    => isset($row['max_patients']) ? (int) $row['max_patients'] : 20,
            ':active' => isset($row['is_active']) ? (int) (bool) $row['is_active'] : 1
        ]);
    }

    $db->commit();

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'DoctorSchedule', $doctor_id, "Doctor schedule updated");

    sendJSON(['success' => true, 'message' => 'Schedule updated successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("admin update-doctor-schedule error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update schedule'], 500);
}
