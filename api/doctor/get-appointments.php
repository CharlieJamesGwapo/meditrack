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
    $doctor_id = $doctor['id'];

    $filter_date   = sanitizeInput($_GET['date'] ?? '');
    $filter_status = sanitizeInput($_GET['status'] ?? '');

    $sql = "
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, a.checked_in_at, a.completed_at, a.cancelled_at, a.created_at,
               p.id as patient_id, p.full_name as patient_name, p.date_of_birth as patient_dob,
               p.gender as patient_gender, p.blood_group, p.allergies, p.contact_number as patient_contact,
               qt.token_hash, qt.expires_at as qr_expires_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id
        WHERE a.doctor_id = :did
    ";
    $params = [':did' => $doctor_id];

    if (!empty($filter_date)) {
        $sql .= " AND a.appointment_date = :date";
        $params[':date'] = $filter_date;
    }
    if (!empty($filter_status)) {
        $sql .= " AND a.status = :status";
        $params[':status'] = $filter_status;
    }

    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    sendJSON(['success' => true, 'appointments' => $appointments, 'count' => count($appointments)]);

} catch (Exception $e) {
    error_log("doctor get-appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load appointments'], 500);
}
