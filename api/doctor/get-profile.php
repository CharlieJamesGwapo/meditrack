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

    $stmt = $db->prepare("
        SELECT d.id as doctor_id, d.full_name, d.specialization, d.license_number,
               d.consultation_fee, d.experience_years, d.bio, d.status,
               u.id as user_id, u.email, u.username, u.last_login,
               s.day_of_week, s.start_time, s.end_time, s.slot_duration, s.max_patients, s.is_active as schedule_active
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN doctor_schedules s ON d.id = s.doctor_id AND s.is_active = 1
        WHERE d.user_id = :uid
        ORDER BY s.day_of_week ASC
    ");
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }

    // Build profile + schedules
    $profile = [
        'doctor_id'        => $rows[0]['doctor_id'],
        'full_name'        => $rows[0]['full_name'],
        'specialization'   => $rows[0]['specialization'],
        'license_number'   => $rows[0]['license_number'],
        'consultation_fee' => $rows[0]['consultation_fee'],
        'experience_years' => $rows[0]['experience_years'],
        'bio'              => $rows[0]['bio'],
        'status'           => $rows[0]['status'],
        'user_id'          => $rows[0]['user_id'],
        'email'            => $rows[0]['email'],
        'username'         => $rows[0]['username'],
        'last_login'       => $rows[0]['last_login'],
        'schedules'        => []
    ];

    foreach ($rows as $row) {
        if ($row['day_of_week'] !== null) {
            $profile['schedules'][] = [
                'day_of_week'   => $row['day_of_week'],
                'start_time'    => $row['start_time'],
                'end_time'      => $row['end_time'],
                'slot_duration' => $row['slot_duration'],
                'max_patients'  => $row['max_patients'],
                'is_active'     => $row['schedule_active']
            ];
        }
    }

    sendJSON(['success' => true, 'profile' => $profile, 'doctor' => $profile]);

} catch (Exception $e) {
    error_log("doctor get-profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load profile'], 500);
}
