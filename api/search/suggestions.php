<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if (strlen($query) < 2) {
    sendJSON(['success' => false, 'message' => 'Query too short'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $role = getCurrentUserRole();
    $userId = getCurrentUserId();
    $profileId = $_SESSION['profile_id'];
    
    $suggestions = [];
    
    // Get appointment suggestions
    if ($type === 'all' || $type === 'appointments') {
        $appointmentSuggestions = getAppointmentSuggestions($db, $query, $role, $profileId, $limit);
        $suggestions = array_merge($suggestions, $appointmentSuggestions);
    }
    
    // Get medical record suggestions
    if ($type === 'all' || $type === 'records') {
        $recordSuggestions = getRecordSuggestions($db, $query, $role, $profileId, $limit);
        $suggestions = array_merge($suggestions, $recordSuggestions);
    }
    
    // Get doctor suggestions
    if ($type === 'all' || $type === 'doctors') {
        $doctorSuggestions = getDoctorSuggestions($db, $query, $limit);
        $suggestions = array_merge($suggestions, $doctorSuggestions);
    }
    
    // Get department suggestions
    if ($type === 'all' || $type === 'departments') {
        $departmentSuggestions = getDepartmentSuggestions($db, $query, $limit);
        $suggestions = array_merge($suggestions, $departmentSuggestions);
    }
    
    // Sort by relevance and limit
    usort($suggestions, function($a, $b) {
        return $b['relevance'] - $a['relevance'];
    });
    
    $suggestions = array_slice($suggestions, 0, $limit);
    
    sendJSON([
        'success' => true,
        'suggestions' => $suggestions,
        'query' => $query,
        'type' => $type
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

function getAppointmentSuggestions($db, $query, $role, $profileId, $limit) {
    $suggestions = [];
    $searchTerm = '%' . $query . '%';
    
    $whereCondition = '';
    if ($role === 'patient') {
        $whereCondition = 'AND a.patient_id = :profile_id';
    } elseif ($role === 'doctor') {
        $whereCondition = 'AND a.doctor_id = :profile_id';
    }
    
    // Search patient names
    $patientQuery = "SELECT DISTINCT p.full_name as suggestion, 'patient' as type, 
                    COUNT(*) as count, 
                    (CASE WHEN p.full_name LIKE :query_start THEN 3 ELSE 2 END) as relevance
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    WHERE p.full_name LIKE :search_term $whereCondition
                    GROUP BY p.full_name
                    ORDER BY relevance DESC, count DESC
                    LIMIT :limit";
    
    $stmt = $db->prepare($patientQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'patient',
            'category' => 'Appointments',
            'relevance' => $row['relevance'],
            'count' => $row['count'],
            'icon' => 'fa-user'
        ];
    }
    
    // Search doctor names
    $doctorQuery = "SELECT DISTINCT d.full_name as suggestion, 'doctor' as type,
                   COUNT(*) as count,
                   (CASE WHEN d.full_name LIKE :query_start THEN 3 ELSE 2 END) as relevance
                   FROM appointments a
                   JOIN doctors d ON a.doctor_id = d.id
                   WHERE d.full_name LIKE :search_term $whereCondition
                   GROUP BY d.full_name
                   ORDER BY relevance DESC, count DESC
                   LIMIT :limit";
    
    $stmt = $db->prepare($doctorQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'doctor',
            'category' => 'Appointments',
            'relevance' => $row['relevance'],
            'count' => $row['count'],
            'icon' => 'fa-user-md'
        ];
    }
    
    // Search reasons/diagnoses
    $reasonQuery = "SELECT DISTINCT a.reason as suggestion, 'reason' as type,
                   COUNT(*) as count,
                   (CASE WHEN a.reason LIKE :query_start THEN 3 ELSE 2 END) as relevance
                   FROM appointments a
                   WHERE a.reason LIKE :search_term $whereCondition
                   GROUP BY a.reason
                   ORDER BY relevance DESC, count DESC
                   LIMIT :limit";
    
    $stmt = $db->prepare($reasonQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'reason',
            'category' => 'Appointments',
            'relevance' => $row['relevance'],
            'count' => $row['count'],
            'icon' => 'fa-notes-medical'
        ];
    }
    
    return $suggestions;
}

function getRecordSuggestions($db, $query, $role, $profileId, $limit) {
    $suggestions = [];
    $searchTerm = '%' . $query . '%';
    
    $whereCondition = '';
    if ($role === 'patient') {
        $whereCondition = 'AND mr.patient_id = :profile_id';
    } elseif ($role === 'doctor') {
        $whereCondition = 'AND mr.doctor_id = :profile_id';
    }
    
    // Search diagnoses
    $diagnosisQuery = "SELECT DISTINCT mr.diagnosis as suggestion, 'diagnosis' as type,
                      COUNT(*) as count,
                      (CASE WHEN mr.diagnosis LIKE :query_start THEN 3 ELSE 2 END) as relevance
                      FROM medical_records mr
                      WHERE mr.diagnosis LIKE :search_term $whereCondition
                      GROUP BY mr.diagnosis
                      ORDER BY relevance DESC, count DESC
                      LIMIT :limit";
    
    $stmt = $db->prepare($diagnosisQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'diagnosis',
            'category' => 'Medical Records',
            'relevance' => $row['relevance'],
            'count' => $row['count'],
            'icon' => 'fa-stethoscope'
        ];
    }
    
    // Search treatments
    $treatmentQuery = "SELECT DISTINCT mr.treatment as suggestion, 'treatment' as type,
                       COUNT(*) as count,
                       (CASE WHEN mr.treatment LIKE :query_start THEN 3 ELSE 2 END) as relevance
                       FROM medical_records mr
                       WHERE mr.treatment LIKE :search_term $whereCondition
                       GROUP BY mr.treatment
                       ORDER BY relevance DESC, count DESC
                       LIMIT :limit";
    
    $stmt = $db->prepare($treatmentQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    if ($role === 'patient' || $role === 'doctor') {
        $stmt->bindValue(':profile_id', $profileId);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'treatment',
            'category' => 'Medical Records',
            'relevance' => $row['relevance'],
            'count' => $row['count'],
            'icon' => 'fa-prescription'
        ];
    }
    
    return $suggestions;
}

function getDoctorSuggestions($db, $query, $limit) {
    $suggestions = [];
    $searchTerm = '%' . $query . '%';
    
    $doctorQuery = "SELECT DISTINCT d.full_name as suggestion, d.specialization,
                   (CASE WHEN d.full_name LIKE :query_start THEN 3 ELSE 2 END) as relevance
                   FROM doctors d
                   WHERE d.full_name LIKE :search_term OR d.specialization LIKE :search_term
                   ORDER BY relevance DESC
                   LIMIT :limit";
    
    $stmt = $db->prepare($doctorQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'doctor',
            'category' => 'Doctors',
            'relevance' => $row['relevance'],
            'description' => $row['specialization'],
            'icon' => 'fa-user-md'
        ];
    }
    
    return $suggestions;
}

function getDepartmentSuggestions($db, $query, $limit) {
    $suggestions = [];
    $searchTerm = '%' . $query . '%';
    
    $deptQuery = "SELECT DISTINCT department as suggestion,
                 (CASE WHEN department LIKE :query_start THEN 3 ELSE 2 END) as relevance
                 FROM doctors
                 WHERE department LIKE :search_term
                 ORDER BY relevance DESC
                 LIMIT :limit";
    
    $stmt = $db->prepare($deptQuery);
    $stmt->bindValue(':search_term', $searchTerm);
    $stmt->bindValue(':query_start', $query . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $suggestions[] = [
            'text' => $row['suggestion'],
            'type' => 'department',
            'category' => 'Departments',
            'relevance' => $row['relevance'],
            'icon' => 'fa-hospital'
        ];
    }
    
    return $suggestions;
}
?>
