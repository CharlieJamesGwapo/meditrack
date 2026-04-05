<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$role = getCurrentUserRole();
if (!in_array($role, ['doctor', 'admin', 'reception'])) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 403);
}

$patientId = $_GET['id'] ?? null;
if (!$patientId) {
    sendJSON(['success' => false, 'message' => 'Patient ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get patient details
    $query = "SELECT
                p.id,
                p.full_name,
                p.date_of_birth,
                p.gender,
                p.contact_number,
                p.email,
                p.address,
                p.blood_group,
                p.allergies,
                p.medical_history,
                p.emergency_contact_name,
                p.emergency_contact_number,
                p.profile_image,
                p.created_at,
                u.last_login,
                u.status
              FROM patients p
              LEFT JOIN users u ON p.user_id = u.id
              WHERE p.id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    // Calculate age
    if ($patient['date_of_birth']) {
        $dob = new DateTime($patient['date_of_birth']);
        $now = new DateTime();
        $patient['age'] = $dob->diff($now)->y;
    }

    // Get profile image URL
    $baseUrl = defined('APP_URL') ? APP_URL : '';
    $patient['profile_image_url'] = $patient['profile_image']
        ? $baseUrl . '/uploads/' . $patient['profile_image']
        : null;

    // Get recent appointments
    $appointmentsQuery = "SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                           a.reason_for_visit, d.full_name as doctor_name, d.specialization
                          FROM appointments a
                          LEFT JOIN doctors d ON a.doctor_id = d.id
                          WHERE a.patient_id = :patient_id
                          ORDER BY a.appointment_date DESC
                          LIMIT 10";
    $appointmentsStmt = $db->prepare($appointmentsQuery);
    $appointmentsStmt->execute([':patient_id' => $patientId]);
    $patient['recent_appointments'] = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get visit history
    $visitsQuery = "SELECT v.id, v.diagnosis, v.prescription, v.notes, v.created_at,
                     a.appointment_date
                    FROM visits v
                    JOIN appointments a ON v.appointment_id = a.id
                    WHERE a.patient_id = :patient_id
                    ORDER BY v.created_at DESC
                    LIMIT 10";
    $visitsStmt = $db->prepare($visitsQuery);
    $visitsStmt->execute([':patient_id' => $patientId]);
    $patient['visit_history'] = $visitsStmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSON([
        'success' => true,
        'patient' => $patient
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
