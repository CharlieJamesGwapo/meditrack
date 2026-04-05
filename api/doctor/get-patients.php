<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn() || getCurrentUserRole() !== 'doctor') {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    // Get doctor ID from user ID
    $doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->execute([':user_id' => $userId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }

    // Get all patients who have had appointments with this doctor
    $query = "SELECT DISTINCT
                p.id,
                p.full_name,
                p.date_of_birth,
                p.gender,
                p.contact_number,
                p.email,
                p.address,
                p.profile_image,
                u.last_login,
                COUNT(a.id) as total_appointments,
                MAX(a.appointment_date) as last_visit
              FROM patients p
              JOIN appointments a ON a.patient_id = p.id AND a.doctor_id = :doctor_id
              LEFT JOIN users u ON p.user_id = u.id
              GROUP BY p.id
              ORDER BY last_visit DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':doctor_id' => $doctor['id']]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseUrl = defined('APP_URL') ? APP_URL : '';
    foreach ($patients as &$patient) {
        if ($patient['date_of_birth']) {
            $dob = new DateTime($patient['date_of_birth']);
            $now = new DateTime();
            $patient['age'] = $dob->diff($now)->y;
        } else {
            $patient['age'] = null;
        }
        if ($patient['last_visit']) {
            $patient['last_visit_formatted'] = date('M d, Y', strtotime($patient['last_visit']));
        }
        // Build absolute profile image URL
        if (!empty($patient['profile_image'])) {
            $patient['profile_image_url'] = $baseUrl . '/uploads/' . $patient['profile_image'];
        } else {
            $patient['profile_image_url'] = null;
        }
    }

    sendJSON([
        'success' => true,
        'patients' => $patients,
        'count' => count($patients)
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
