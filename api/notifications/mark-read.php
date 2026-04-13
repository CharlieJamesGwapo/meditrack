<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id  = (int) ($input['id'] ?? 0);
$all = !empty($input['all']);

try {
    $db  = (new Database())->getConnection();
    $uid = getCurrentUserId();

    if ($all) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $uid]);
    } elseif ($id > 0) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $uid]);
    } else {
        sendJSON(['success' => false, 'message' => 'id or all=true required'], 400);
    }

    sendJSON(['success' => true]);
} catch (Exception $e) {
    error_log("notifications/mark-read error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to mark read'], 500);
}
