<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT id, appointment_id, patient_id, chief_complaint, blood_pressure, temperature,
               heart_rate, weight, height_cm, oxygen_saturation, priority_level, notes,
               recorded_by, recorded_at, updated_at
          FROM triage_assessments
         WHERE appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    sendJSON(['success' => true, 'vitals' => $row ?: null]);
} catch (Exception $e) {
    error_log("staff/get-vitals error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load vitals'], 500);
}
