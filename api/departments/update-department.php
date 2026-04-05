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
    if (empty($data['id']) || empty($data['name']) || empty($data['code'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Department ID, name and code are required'
        ]);
        exit;
    }
    
    // Check if department code already exists for other departments
    $checkQuery = "SELECT id FROM departments WHERE code = :code AND id != :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':code', $data['code']);
    $checkStmt->bindParam(':id', $data['id']);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Department code already exists'
        ]);
        exit;
    }
    
    // Update department
    $query = "UPDATE departments 
              SET name = :name, 
                  code = :code, 
                  description = :description, 
                  head = :head, 
                  contact = :contact, 
                  location = :location,
                  updated_at = NOW()
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $data['id']);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':code', $data['code']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':head', $data['head']);
    $stmt->bindParam(':contact', $data['contact']);
    $stmt->bindParam(':location', $data['location']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Department updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update department'
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
