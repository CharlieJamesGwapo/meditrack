<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get POST data
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['code'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Department name and code are required'
        ]);
        exit;
    }
    
    // Check if department code already exists
    $checkQuery = "SELECT id FROM departments WHERE code = :code";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':code', $data['code']);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Department code already exists'
        ]);
        exit;
    }
    
    // Insert new department
    $query = "INSERT INTO departments (name, code, description, head, contact, location, created_at) 
              VALUES (:name, :code, :description, :head, :contact, :location, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':code', $data['code']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':head', $data['head']);
    $stmt->bindParam(':contact', $data['contact']);
    $stmt->bindParam(':location', $data['location']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully',
            'department_id' => $db->lastInsertId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add department'
        ]);
    }
    
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
