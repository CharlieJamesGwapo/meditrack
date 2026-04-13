<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $db = (new Database())->getConnection();
    $uid = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 20");
    $stmt->execute([':uid' => $uid]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute([':uid' => $uid]);
    $unread = (int) $stmt->fetchColumn();

    sendJSON(['success' => true, 'notifications' => $items, 'unread_count' => $unread]);
} catch (Exception $e) {
    error_log("notifications/list error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load notifications'], 500);
}
