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

    $page        = max(1, (int) ($_GET['page']        ?? 1));
    $per_page    = 20;
    $offset      = ($page - 1) * $per_page;
    $action_type = trim($_GET['action_type'] ?? '');
    $module      = trim($_GET['module']      ?? '');

    $where  = [];
    $params = [];

    if ($action_type && $action_type !== 'all') {
        $where[] = "action_type = :action_type";
        $params[':action_type'] = $action_type;
    }
    if ($module && $module !== 'all') {
        $where[] = "module = :module";
        $params[':module'] = $module;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs $where_sql");
    $count_stmt->execute($params);
    $total       = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));

    $stmt = $db->prepare(
        "SELECT id, user_id, username, user_role, action_type, module, record_id, description, ip_address, created_at
         FROM activity_logs
         $where_sql
         ORDER BY created_at DESC
         LIMIT :lim OFFSET :off"
    );

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSON([
        'success'     => true,
        'logs'        => $logs,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => $total_pages,
        'per_page'    => $per_page,
    ]);

} catch (Exception $e) {
    error_log("admin activity-logs error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load activity logs'], 500);
}
