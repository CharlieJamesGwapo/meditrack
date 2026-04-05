<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    $whereClause = "user_id = :user_id";
    if ($unread_only) {
        $whereClause .= " AND is_read = 0";
    }

    $query = "SELECT * FROM notifications 
              WHERE $whereClause 
              ORDER BY created_at DESC 
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', getCurrentUserId(), PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $notifications = $stmt->fetchAll();

    // Get unread count
    $countQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute([':user_id' => getCurrentUserId()]);
    $unreadCount = $countStmt->fetch()['unread_count'];

    sendJSON([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unreadCount
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
