<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);
$notification_id = $data['notification_id'] ?? null;
$mark_all = $data['mark_all'] ?? false;

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($mark_all) {
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => getCurrentUserId()]);
    } else {
        if (!$notification_id) {
            sendJSON(['success' => false, 'message' => 'Notification ID required'], 400);
        }
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $notification_id, ':user_id' => getCurrentUserId()]);
    }

    sendJSON(['success' => true, 'message' => 'Notification(s) marked as read']);

} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
