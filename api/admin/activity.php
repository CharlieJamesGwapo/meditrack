<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $filter_action = sanitizeInput($_GET['action_type'] ?? '');
    $filter_module = sanitizeInput($_GET['module'] ?? '');
    $page          = max(1, (int) ($_GET['page'] ?? 1));
    $offset        = ($page - 1) * ITEMS_PER_PAGE;

    $where  = "WHERE 1=1";
    $params = [];

    if (!empty($filter_action)) {
        $where .= " AND action_type = :action_type";
        $params[':action_type'] = $filter_action;
    }
    if (!empty($filter_module)) {
        $where .= " AND module = :module";
        $params[':module'] = $filter_module;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs $where");
    $countStmt->execute($params);
    $total      = (int) $countStmt->fetchColumn();
    $total_pages = (int) ceil($total / ITEMS_PER_PAGE);

    $params[':limit']  = ITEMS_PER_PAGE;
    $params[':offset'] = $offset;

    $stmt = $db->prepare("
        SELECT id, user_id, username, user_role, action_type, module, record_id, description, ip_address, created_at
        FROM activity_logs
        $where
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    sendJSON([
        'success'    => true,
        'logs'       => $logs,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $total_pages,
            'per_page'    => ITEMS_PER_PAGE
        ]
    ]);

} catch (Exception $e) {
    error_log("admin activity error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load activity logs'], 500);
}
