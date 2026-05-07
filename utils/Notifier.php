<?php
/**
 * In-app notification helper.
 * Inserts a row into `notifications`. Never throws — logs on failure.
 */
class Notifier {
    public static function notify(PDO $db, int $user_id, string $type, string $title, string $message, ?string $link = null): bool {
        try {
            $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (:uid, :type, :title, :msg, :link)");
            return $stmt->execute([
                ':uid'   => $user_id,
                ':type'  => $type,
                ':title' => $title,
                ':msg'   => $message,
                ':link'  => $link,
            ]);
        } catch (Exception $e) {
            error_log("Notifier error: " . $e->getMessage());
            return false;
        }
    }
}
