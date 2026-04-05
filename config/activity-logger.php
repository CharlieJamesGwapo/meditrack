<?php
/**
 * Activity Logger Helper Class
 * Use this to log all CRUD operations throughout the system
 */

class ActivityLogger {
    private $db;
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            require_once __DIR__ . '/database.php';
            $dbInstance = new Database();
            $this->db = $dbInstance->getConnection();
        }
    }
    
    /**
     * Log an activity
     * 
     * @param int $userId User ID performing the action
     * @param string $username Username of the user
     * @param string $userRole Role of the user (admin, doctor, patient, staff)
     * @param string $actionType Type of action (CREATE, READ, UPDATE, DELETE, LOGIN, LOGOUT, VIEW, EXPORT, IMPORT)
     * @param string $module Module name (doctors, patients, appointments, departments, etc.)
     * @param int|null $recordId ID of the affected record
     * @param string $description Human-readable description of the action
     * @param array|null $oldData Previous data before update/delete
     * @param array|null $newData New data after create/update
     * @param string $status Status of the action (success, failed, warning)
     * @return bool Success status
     */
    public function log($userId, $username, $userRole, $actionType, $module, $recordId = null, $description = '', $oldData = null, $newData = null, $status = 'success') {
        try {
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
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
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
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Quick log methods for common actions
     */
    
    public function logCreate($userId, $username, $userRole, $module, $recordId, $description, $newData = null) {
        return $this->log($userId, $username, $userRole, 'CREATE', $module, $recordId, $description, null, $newData, 'success');
    }
    
    public function logUpdate($userId, $username, $userRole, $module, $recordId, $description, $oldData = null, $newData = null) {
        return $this->log($userId, $username, $userRole, 'UPDATE', $module, $recordId, $description, $oldData, $newData, 'success');
    }
    
    public function logDelete($userId, $username, $userRole, $module, $recordId, $description, $oldData = null) {
        return $this->log($userId, $username, $userRole, 'DELETE', $module, $recordId, $description, $oldData, null, 'success');
    }
    
    public function logView($userId, $username, $userRole, $module, $description) {
        return $this->log($userId, $username, $userRole, 'VIEW', $module, null, $description, null, null, 'success');
    }
    
    public function logLogin($userId, $username, $userRole, $description = 'User logged in successfully') {
        return $this->log($userId, $username, $userRole, 'LOGIN', 'auth', null, $description, null, null, 'success');
    }
    
    public function logLogout($userId, $username, $userRole, $description = 'User logged out') {
        return $this->log($userId, $username, $userRole, 'LOGOUT', 'auth', null, $description, null, null, 'success');
    }
    
    public function logFailed($userId, $username, $userRole, $actionType, $module, $description) {
        return $this->log($userId, $username, $userRole, $actionType, $module, null, $description, null, null, 'failed');
    }
}

/**
 * Example Usage:
 * 
 * // Initialize logger
 * $logger = new ActivityLogger();
 * 
 * // Log doctor creation
 * $logger->logCreate(
 *     1,                                    // user_id
 *     'admin',                              // username
 *     'admin',                              // user_role
 *     'doctors',                            // module
 *     $doctorId,                            // record_id
 *     'Created new doctor: Dr. KC D Alberca', // description
 *     ['name' => 'KC D Alberca', 'specialization' => 'Cardiology'] // new_data
 * );
 * 
 * // Log doctor update
 * $logger->logUpdate(
 *     1,
 *     'admin',
 *     'admin',
 *     'doctors',
 *     $doctorId,
 *     'Updated doctor profile: Dr. KC D Alberca',
 *     ['consultation_fee' => 1000],  // old_data
 *     ['consultation_fee' => 1500]   // new_data
 * );
 * 
 * // Log doctor deletion
 * $logger->logDelete(
 *     1,
 *     'admin',
 *     'admin',
 *     'doctors',
 *     $doctorId,
 *     'Deleted doctor: Dr. KC D Alberca',
 *     ['name' => 'KC D Alberca', 'status' => 'active'] // old_data
 * );
 * 
 * // Log viewing patients list
 * $logger->logView(
 *     1,
 *     'admin',
 *     'admin',
 *     'patients',
 *     'Viewed patients list'
 * );
 * 
 * // Log login
 * $logger->logLogin(1, 'admin', 'admin', 'Admin user logged in successfully');
 * 
 * // Log failed action
 * $logger->logFailed(
 *     1,
 *     'admin',
 *     'admin',
 *     'CREATE',
 *     'doctors',
 *     'Failed to create doctor: Duplicate license number'
 * );
 */
