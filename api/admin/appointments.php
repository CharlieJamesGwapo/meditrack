<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

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
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.reason_for_visit as reason,
                a.notes,
                CONCAT(COALESCE(pu.first_name, ''), ' ', COALESCE(pu.middle_name, ''), ' ', COALESCE(pu.last_name, '')) as patient_name,
                CONCAT(COALESCE(du.first_name, ''), ' ', COALESCE(du.middle_name, ''), ' ', COALESCE(du.last_name, '')) as doctor_name,
                p.full_name as patient_full_name,
                d.full_name as doctor_full_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN users pu ON p.user_id = pu.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN users du ON d.user_id = du.id
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
        $patientName = trim($row['patient_name']) ?: $row['patient_full_name'] ?: 'Unknown Patient';
        $doctorName = trim($row['doctor_name']) ?: $row['doctor_full_name'] ?: 'Unknown Doctor';
        
        $appointments[] = [
            'id' => (int)$row['id'],
            'patient_name' => $patientName,
            'doctor_name' => $doctorName,
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status' => $row['status'] ?: 'scheduled',
            'reason' => $row['reason'],
            'notes' => $row['notes']
        ];
    }
    
    echo json_encode($appointments);
    
} catch(Exception $e) {
    echo json_encode([]);
}
?>
