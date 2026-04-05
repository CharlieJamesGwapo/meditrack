<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get all active departments
    $query = "SELECT DISTINCT department 
              FROM doctors 
              WHERE status = 'active' 
              AND department IS NOT NULL 
              AND department != ''
              ORDER BY department ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    sendJSON([
        'success' => true,
        'departments' => $departments
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
