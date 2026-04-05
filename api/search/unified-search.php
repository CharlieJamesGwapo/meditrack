<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $role = getCurrentUserRole();
    $userId = getCurrentUserId();
    $profileId = $_SESSION['profile_id'];
    
    // Get search parameters
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all'; // all, appointments, records, qr
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    
    if (empty($query) && $type !== 'qr') {
        sendJSON(['success' => false, 'message' => 'Search query is required'], 400);
    }
    
    $results = [];
    
    // Search Appointments
    if ($type === 'all' || $type === 'appointments') {
        $appointmentResults = searchAppointments($db, $query, $role, $profileId, $limit, $offset);
        $results['appointments'] = $appointmentResults;
    }
    
    // Search Medical Records
    if ($type === 'all' || $type === 'records') {
        $recordResults = searchMedicalRecords($db, $query, $role, $profileId, $limit, $offset);
        $results['records'] = $recordResults;
    }
    
    // QR Code Lookup
    if ($type === 'qr') {
        $qrToken = $_GET['qr_token'] ?? '';
        if (!empty($qrToken)) {
            $qrResult = lookupQRCode($db, $qrToken, $userId);
            $results['qr'] = $qrResult;
        } else {
            $results['qr'] = ['success' => false, 'message' => 'QR token is required'];
        }
    }
    
    sendJSON([
        'success' => true,
        'results' => $results,
        'query' => $query,
        'type' => $type,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => array_sum(array_column($results, 'total') ?? [0])
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

function searchAppointments($db, $query, $role, $profileId, $limit, $offset) {
    $whereConditions = [];
    $params = [];
    
    // Role-based access
    if ($role === 'patient') {
        $whereConditions[] = "a.patient_id = :profile_id";
        $params[':profile_id'] = $profileId;
    } elseif ($role === 'doctor') {
        $whereConditions[] = "a.doctor_id = :profile_id";
        $params[':profile_id'] = $profileId;
    }
    
    // Search conditions
    if (!empty($query)) {
        $searchConditions = [
            "p.full_name LIKE :search_name",
            "d.full_name LIKE :search_doctor",
            "a.reason LIKE :search_reason",
            "a.appointment_date LIKE :search_date",
            "a.status LIKE :search_status"
        ];
        
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $searchParam = "%{$query}%";
        $params[':search_name'] = $searchParam;
        $params[':search_doctor'] = $searchParam;
        $params[':search_reason'] = $searchParam;
        $params[':search_date'] = $searchParam;
        $params[':search_status'] = $searchParam;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count query
    $countQuery = "SELECT COUNT(*) as total FROM appointments a
                   JOIN patients p ON a.patient_id = p.id
                   JOIN doctors d ON a.doctor_id = d.id
                   $whereClause";
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Data query
    $dataQuery = "SELECT a.*, 
                  p.full_name as patient_name, 
                  p.contact_number as patient_contact,
                  d.full_name as doctor_name, 
                  d.specialization,
                  d.department,
                  qt.token_hash,
                  qt.expires_at as qr_expires_at
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN doctors d ON a.doctor_id = d.id
                  LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id
                  $whereClause
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC
                  LIMIT :limit OFFSET :offset";
    
    $dataStmt = $db->prepare($dataQuery);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $appointments = $dataStmt->fetchAll();
    
    return [
        'data' => $appointments,
        'total' => (int)$total
    ];
}

function searchMedicalRecords($db, $query, $role, $profileId, $limit, $offset) {
    $whereConditions = [];
    $params = [];
    
    // Role-based access
    if ($role === 'patient') {
        $whereConditions[] = "mr.patient_id = :profile_id";
        $params[':profile_id'] = $profileId;
    } elseif ($role === 'doctor') {
        $whereConditions[] = "mr.doctor_id = :profile_id";
        $params[':profile_id'] = $profileId;
    }
    
    // Search conditions
    if (!empty($query)) {
        $searchConditions = [
            "p.full_name LIKE :search_name",
            "d.full_name LIKE :search_doctor",
            "mr.diagnosis LIKE :search_diagnosis",
            "mr.treatment LIKE :search_treatment",
            "mr.notes LIKE :search_notes",
            "mr.visit_date LIKE :search_date"
        ];
        
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $searchParam = "%{$query}%";
        $params[':search_name'] = $searchParam;
        $params[':search_doctor'] = $searchParam;
        $params[':search_diagnosis'] = $searchParam;
        $params[':search_treatment'] = $searchParam;
        $params[':search_notes'] = $searchParam;
        $params[':search_date'] = $searchParam;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count query
    $countQuery = "SELECT COUNT(*) as total FROM medical_records mr
                   JOIN patients p ON mr.patient_id = p.id
                   JOIN doctors d ON mr.doctor_id = d.id
                   $whereClause";
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Data query
    $dataQuery = "SELECT mr.*, 
                  p.full_name as patient_name, 
                  p.contact_number as patient_contact,
                  d.full_name as doctor_name, 
                  d.specialization,
                  d.department
                  FROM medical_records mr
                  JOIN patients p ON mr.patient_id = p.id
                  JOIN doctors d ON mr.doctor_id = d.id
                  $whereClause
                  ORDER BY mr.visit_date DESC, mr.created_at DESC
                  LIMIT :limit OFFSET :offset";
    
    $dataStmt = $db->prepare($dataQuery);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $records = $dataStmt->fetchAll();
    
    return [
        'data' => $records,
        'total' => (int)$total
    ];
}

function lookupQRCode($db, $qrToken, $userId) {
    try {
        require_once '../../utils/QRCodeGenerator.php';
        $qrGenerator = new QRCodeGenerator($db);
        
        $validation = $qrGenerator->validateQRCode($qrToken, $userId);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        // Get full appointment details
        $query = "SELECT a.*, 
                  p.full_name as patient_name, 
                  p.contact_number as patient_contact,
                  p.date_of_birth as patient_dob,
                  p.blood_group,
                  p.allergies,
                  d.full_name as doctor_name, 
                  d.specialization,
                  d.department
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.id
                  JOIN doctors d ON a.doctor_id = d.id
                  WHERE a.id = :appointment_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':appointment_id' => $validation['appointment_id']]);
        $appointment = $stmt->fetch();
        
        return [
            'success' => true,
            'appointment' => $appointment,
            'validation' => $validation
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error looking up QR code: ' . $e->getMessage()
        ];
    }
}
?>
