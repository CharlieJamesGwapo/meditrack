<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

session_start();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Query to get all departments with doctor and patient counts
    $query = "SELECT 
                d.id,
                d.name,
                d.code,
                d.description,
                d.head,
                d.contact,
                d.location,
                d.created_at,
                COUNT(DISTINCT doc.id) as doctor_count,
                COUNT(DISTINCT p.id) as patient_count
              FROM departments d
              LEFT JOIN doctors doc ON d.id = doc.department_id AND doc.is_archived = 0
              LEFT JOIN patients p ON doc.id = p.doctor_id AND p.is_archived = 0
              GROUP BY d.id
              ORDER BY d.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'count' => count($departments)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
