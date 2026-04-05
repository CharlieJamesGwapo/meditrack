<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get filter parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $actionType = $_GET['action_type'] ?? '';
    $module = $_GET['module'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($actionType)) {
        $whereConditions[] = "action_type = :action_type";
        $params[':action_type'] = $actionType;
    }
    
    if (!empty($module)) {
        $whereConditions[] = "module = :module";
        $params[':module'] = $module;
    }
    
    if (!empty($userId)) {
        $whereConditions[] = "user_id = :user_id";
        $params[':user_id'] = $userId;
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(description LIKE :search OR username LIKE :search OR module LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM activity_logs $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated logs
    $sql = "SELECT 
                id,
                user_id,
                username,
                user_role,
                action_type,
                module,
                record_id,
                description,
                old_data,
                new_data,
                ip_address,
                user_agent,
                status,
                created_at
            FROM activity_logs 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format logs
    $formattedLogs = array_map(function($log) {
        return [
            'id' => $log['id'],
            'user_id' => $log['user_id'],
            'username' => $log['username'],
            'user_role' => $log['user_role'],
            'action_type' => $log['action_type'],
            'module' => $log['module'],
            'record_id' => $log['record_id'],
            'description' => $log['description'],
            'old_data' => $log['old_data'] ? json_decode($log['old_data'], true) : null,
            'new_data' => $log['new_data'] ? json_decode($log['new_data'], true) : null,
            'ip_address' => $log['ip_address'],
            'user_agent' => $log['user_agent'],
            'status' => $log['status'],
            'created_at' => $log['created_at'],
            'time_ago' => getTimeAgo($log['created_at'])
        ];
    }, $logs);
    
    // Get statistics
    $statsSql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour,
                    COUNT(DISTINCT user_id) as active_users
                 FROM activity_logs";
    $statsStmt = $db->query($statsSql);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $limit);
    
    // Clear any output buffer content
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'data' => $formattedLogs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => (int)$totalRecords,
            'per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'stats' => [
            'total' => (int)$stats['total'],
            'today' => (int)$stats['today'],
            'last_hour' => (int)$stats['last_hour'],
            'active_users' => (int)$stats['active_users']
        ]
    ]);
    
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

/**
 * Convert timestamp to human-readable time ago
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y h:i A', $time);
    }
}
