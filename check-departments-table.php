<?php
header('Content-Type: application/json');
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get table structure
    $stmt = $db->prepare("DESCRIBE departments");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sample data
    $stmt2 = $db->prepare("SELECT * FROM departments LIMIT 3");
    $stmt2->execute();
    $sampleData = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'sample_data' => $sampleData,
        'column_names' => array_column($columns, 'Field')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
