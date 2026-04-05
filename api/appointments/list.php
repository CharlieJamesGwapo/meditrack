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
    
    $status = $_GET['status'] ?? null;
    $date = $_GET['date'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    // Build query based on role
    $whereConditions = [];
    $params = [];

    if ($role === 'patient') {
        $whereConditions[] = "a.patient_id = :profile_id";
        $params[':profile_id'] = $profileId;
    } elseif ($role === 'doctor') {
        $whereConditions[] = "a.doctor_id = :profile_id";
        $params[':profile_id'] = $profileId;
    }

    if ($status) {
        $whereConditions[] = "a.status = :status";
        $params[':status'] = $status;
    }

    if ($date) {
        $whereConditions[] = "a.appointment_date = :date";
        $params[':date'] = $date;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM appointments a $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Get appointments
    $query = "SELECT a.*, 
              p.full_name as patient_name, 
              p.contact_number as patient_contact,
              p.date_of_birth as patient_dob,
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
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $appointments = $stmt->fetchAll();

    sendJSON([
        'success' => true,
        'appointments' => $appointments,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
