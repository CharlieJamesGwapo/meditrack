<?php
/**
 * NoShowSweeper — converts stale 'scheduled' appointments to 'no_show'.
 *
 * "Stale" = status='scheduled' AND TIMESTAMP(appointment_date, appointment_time)
 *           is more than NO_SHOW_GRACE_MINUTES in the past.
 *
 * Idempotent. Cheap (single UPDATE, indexed on status). Safe to call on every
 * dashboard load — the WHERE clause is a no-op if no stale rows exist.
 */
class NoShowSweeper {
    public static function sweep(PDO $db): int {
        $grace = defined('NO_SHOW_GRACE_MINUTES') ? (int) NO_SHOW_GRACE_MINUTES : 15;
        try {
            $stmt = $db->prepare("
                UPDATE appointments
                   SET status = 'no_show', updated_at = NOW()
                 WHERE status = 'scheduled'
                   AND TIMESTAMP(appointment_date, appointment_time) < (NOW() - INTERVAL {$grace} MINUTE)
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("NoShowSweeper error: " . $e->getMessage());
            return 0;
        }
    }
}
