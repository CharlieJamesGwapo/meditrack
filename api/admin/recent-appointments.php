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
    
    // Get recent appointments with patient and doctor names
    $sql = "SELECT 
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                CONCAT(p.first_name, ' ', p.last_name) as patientName,
                CONCAT(d.first_name, ' ', d.last_name) as doctorName
            FROM appointments a
            LEFT JOIN users p ON a.patient_id = p.id
            LEFT JOIN users d ON a.doctor_id = d.id
            ORDER BY a.created_at DESC
            LIMIT 10";
    
    $stmt = $db->query($sql);
    $appointments = [];
    
    while ($row = $stmt->fetch()) {
        $appointments[] = [
            'id' => $row['id'],
            'patientName' => $row['patientName'] ?: 'Unknown Patient',
            'doctorName' => $row['doctorName'] ?: 'Unknown Doctor',
            'time' => date('g:i A', strtotime($row['appointment_time'])),
            'date' => date('M d, Y', strtotime($row['appointment_date'])),
            'status' => $row['status'] ?: 'pending'
        ];
    }
    
    echo json_encode($appointments);
    
} catch(Exception $e) {
    echo json_encode([]);
}
?>
