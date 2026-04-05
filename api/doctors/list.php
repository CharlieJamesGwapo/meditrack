<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    $department = $_GET['department'] ?? null;
    $specialization = $_GET['specialization'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');

    $whereConditions = ["d.status = 'active'"];
    $params = [];

    if ($department) {
        $whereConditions[] = "d.department = :department";
        $params[':department'] = $department;
    }

    if ($specialization) {
        $whereConditions[] = "d.specialization LIKE :specialization";
        $params[':specialization'] = "%$specialization%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT d.*, 
              COUNT(DISTINCT ds.id) as schedule_count,
              (SELECT COUNT(*) FROM appointments a 
               WHERE a.doctor_id = d.id 
               AND a.appointment_date = :date 
               AND a.status NOT IN ('cancelled', 'no_show')) as appointments_today
              FROM doctors d
              LEFT JOIN doctor_schedules ds ON d.id = ds.doctor_id AND ds.is_active = 1
              WHERE $whereClause
              GROUP BY d.id
              ORDER BY d.full_name";
    
    $stmt = $db->prepare($query);
    $params[':date'] = $date;
    $stmt->execute($params);

    $doctors = $stmt->fetchAll();

    sendJSON([
        'success' => true,
        'doctors' => $doctors
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
