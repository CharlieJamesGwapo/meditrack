<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

/**
 * Log Activity Function
 * Records all CRUD operations and user actions
 */
function logActivity($userId, $username, $userRole, $actionType, $module, $recordId = null, $description = '', $oldData = null, $newData = null, $status = 'success') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // Get client information
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Prepare data
        $oldDataJson = $oldData ? json_encode($oldData) : null;
        $newDataJson = $newData ? json_encode($newData) : null;
        
        // Insert activity log
        $sql = "INSERT INTO activity_logs 
                (user_id, username, user_role, action_type, module, record_id, description, old_data, new_data, ip_address, user_agent, status) 
                VALUES 
                (:user_id, :username, :user_role, :action_type, :module, :record_id, :description, :old_data, :new_data, :ip_address, :user_agent, :status)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':user_role' => $userRole,
            ':action_type' => $actionType,
            ':module' => $module,
            ':record_id' => $recordId,
            ':description' => $description,
            ':old_data' => $oldDataJson,
            ':new_data' => $newDataJson,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':status' => $status
        ]);
        
        return [
            'success' => true,
            'message' => 'Activity logged successfully',
            'log_id' => $db->lastInsertId()
        ];
        
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to log activity: ' . $e->getMessage()
        ];
    }
}

try {
    // Get POST data
    $userId = $_POST['user_id'] ?? 1; // Default to admin for now
    $username = $_POST['username'] ?? 'admin';
    $userRole = $_POST['user_role'] ?? 'admin';
    $actionType = $_POST['action_type'] ?? '';
    $module = $_POST['module'] ?? '';
    $recordId = $_POST['record_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $oldData = isset($_POST['old_data']) ? json_decode($_POST['old_data'], true) : null;
    $newData = isset($_POST['new_data']) ? json_decode($_POST['new_data'], true) : null;
    $status = $_POST['status'] ?? 'success';
    
    // Validate required fields
    if (empty($actionType) || empty($module)) {
        throw new Exception('Action type and module are required');
    }
    
    // Log the activity
    $result = logActivity($userId, $username, $userRole, $actionType, $module, $recordId, $description, $oldData, $newData, $status);
    
    // Clear any output buffer content
    ob_clean();
    
    echo json_encode($result);
    
    ob_end_flush();
    exit();
    
} catch (Exception $e) {
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    ob_end_flush();
    exit();
}
