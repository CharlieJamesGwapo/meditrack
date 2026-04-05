<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/config.php';

// Debug: Check if functions exist
if (!function_exists('isLoggedIn')) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'isLoggedIn function not found - config.php not loaded properly'
    ]);
    exit;
}

// Debug: Check authentication
$authDebug = [
    'isLoggedIn' => isLoggedIn(),
    'userRole' => getCurrentUserRole(),
    'sessionData' => $_SESSION ?? []
];

// Check if user is logged in and is admin
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access',
        'debug' => $authDebug
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Debug: Check database connection
    $dbDebug = [
        'databaseConnected' => $db ? true : false,
        'databaseError' => $db ? null : 'Database connection failed'
    ];
    
    // Get POST data
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // Debug: Check input data
    $inputDebug = [
        'rawInput' => $input,
        'decodedData' => $data,
        'jsonError' => json_last_error_msg()
    ];
    
    // Validate required fields
    if (empty($data['name']) || empty($data['code'])) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Department name and code are required',
            'debug' => array_merge($authDebug, $dbDebug, $inputDebug)
        ]);
        exit;
    }
    
    // Check if department code already exists
    $checkQuery = "SELECT id FROM departments WHERE code = :code";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':code', $data['code']);
    $checkStmt->execute();
    
    $existingCount = $checkStmt->rowCount();
    
    if ($existingCount > 0) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Department code already exists',
            'debug' => array_merge($authDebug, $dbDebug, $inputDebug, ['existingCount' => $existingCount])
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
    
    $executeResult = $stmt->execute();
    $insertId = $db->lastInsertId();
    
    ob_end_clean();
    
    if ($executeResult) {
        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully',
            'department_id' => $insertId,
            'debug' => array_merge($authDebug, $dbDebug, $inputDebug, [
                'executeResult' => $executeResult,
                'insertId' => $insertId
            ])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add department',
            'debug' => array_merge($authDebug, $dbDebug, $inputDebug, [
                'executeResult' => $executeResult,
                'stmtError' => $stmt->errorInfo()
            ])
        ]);
    }
    
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'exception' => 'PDOException',
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'exception' => 'Exception',
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
