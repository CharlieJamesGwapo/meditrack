<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$patient_id = $_GET['patient_id'] ?? null;

// If no patient_id provided, get from current user
if (!$patient_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id FROM patients WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $patient_id = $result['id'];
        }
    } catch (Exception $e) {
        sendJSON(['success' => false, 'message' => 'Error getting patient ID'], 500);
    }
}

if (!$patient_id) {
    sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get patient details
    $patientQuery = "SELECT p.*, u.email FROM patients p 
                     JOIN users u ON p.user_id = u.id 
                     WHERE p.id = :id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->execute([':id' => $patient_id]);
    $patient = $patientStmt->fetch();

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    // Get visit history
    $visitsQuery = "SELECT v.*, 
                    a.appointment_date, 
                    a.appointment_time,
                    d.full_name as doctor_name,
                    d.specialization
                    FROM visits v
                    JOIN appointments a ON v.appointment_id = a.id
                    JOIN doctors d ON v.doctor_id = d.id
                    WHERE v.patient_id = :patient_id
                    ORDER BY v.visit_date DESC";
    $visitsStmt = $db->prepare($visitsQuery);
    $visitsStmt->execute([':patient_id' => $patient_id]);
    $visits = $visitsStmt->fetchAll();

    // Decode vital signs JSON and format dates
    foreach ($visits as &$visit) {
        $visit['vital_signs'] = json_decode($visit['vital_signs'], true);
        $visit['formatted_date'] = date('F d, Y', strtotime($visit['visit_date']));
    }

    sendJSON([
        'success' => true,
        'patient' => $patient,
        'visits' => $visits
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
