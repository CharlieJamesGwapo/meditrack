<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/config.php';

// Check authentication - admin and reception can view appointments
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['admin', 'reception'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get filters
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    $sql = "SELECT
                a.id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason_for_visit as reason,
                a.notes,
                COALESCE(p.full_name, 'Unknown Patient') as patient_name,
                COALESCE(d.full_name, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as doctor_name,
                d.department as department
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    if ($date) {
        $sql .= " AND DATE(a.appointment_date) = :date";
        $params[':date'] = $date;
    }
    
    if ($status) {
        $sql .= " AND a.status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $appointments = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $patientName = trim($row['patient_name']) ?: 'Unknown Patient';
        $doctorName = trim($row['doctor_name']) ?: 'Not Assigned';
        
        $appointments[] = [
            'id' => (int)$row['id'],
            'patient_id' => (int)$row['patient_id'],
            'patient_name' => $patientName,
            'doctor_name' => $doctorName,
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status' => $row['status'] ?: 'scheduled',
            'reason' => $row['reason'],
            'notes' => $row['notes'],
            'department' => $row['department'] ?: 'General'
        ];
    }
    
    echo json_encode(['success' => true, 'appointments' => $appointments]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
