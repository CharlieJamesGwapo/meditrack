<?php
/**
 * CancelBroadcaster — fans out a `slot_available` in-app notification to
 * patients who could plausibly take a freed appointment slot.
 *
 * Recipient set rules (Batch C2 spec):
 *   1. role = 'patient', status = 'active'
 *   2. excluding the cancelling patient
 *   3. excluding any patient with an active appointment on the cancellation's date
 *   4. excluding patients with >= 3 unread `slot_available` notifications in the last 24h
 *   5. capped at CANCEL_BROADCAST_LIMIT, ordered by most recent last_login (NULLs last)
 *
 * Idempotent: rerunning for the same cancelled_appointment_id does not double-notify
 * (uniqueness enforced by cancel_broadcasts.uniq_broadcast_recipient).
 */
require_once __DIR__ . '/Notifier.php';

class CancelBroadcaster {
    public static function broadcast(PDO $db, int $cancelledAppointmentId, array $appt): int {
        $limit = defined('CANCEL_BROADCAST_LIMIT') ? (int) CANCEL_BROADCAST_LIMIT : 20;

        try {
            $stmt = $db->prepare("
                SELECT u.id AS user_id
                  FROM users u
                  JOIN patients p ON p.user_id = u.id
                 WHERE u.role = 'patient'
                   AND u.status = 'active'
                   AND u.id  != :cancelling_user_id
                   AND p.id  != :cancelling_patient_id
                   AND NOT EXISTS (
                         SELECT 1 FROM appointments a
                          WHERE a.patient_id = p.id
                            AND a.appointment_date = :cancel_date
                            AND a.status IN ('scheduled','checked_in','in_progress')
                   )
                   AND (
                         SELECT COUNT(*) FROM notifications n
                          WHERE n.user_id = u.id
                            AND n.type = 'slot_available'
                            AND n.is_read = 0
                            AND n.created_at > (NOW() - INTERVAL 24 HOUR)
                   ) < 3
                 ORDER BY u.last_login IS NULL ASC, u.last_login DESC
                 LIMIT {$limit}
            ");
            $stmt->execute([
                ':cancelling_user_id'    => (int) ($appt['cancelling_user_id'] ?? 0),
                ':cancelling_patient_id' => (int) ($appt['cancelling_patient_id'] ?? 0),
                ':cancel_date'           => $appt['appointment_date'] ?? '',
            ]);
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!$recipients) return 0;

            $title = sprintf('Open slot — %s %s', $appt['appointment_date'], $appt['appointment_time']);
            $msg   = sprintf(
                'A %s slot just opened up on %s. Tap to book it before someone else does.',
                $appt['appointment_time'],
                $appt['appointment_date']
            );
            // Deep-link straight to the patient dashboard's booking section with date+time pre-selected.
            $link = sprintf(
                'patient-dashboard.html?book_date=%s&book_time=%s',
                rawurlencode($appt['appointment_date']),
                rawurlencode(substr((string) $appt['appointment_time'], 0, 5))
            );

            $count = 0;
            $insertBroadcast = $db->prepare("
                INSERT IGNORE INTO cancel_broadcasts (cancelled_appointment_id, recipient_user_id)
                VALUES (:cid, :uid)
            ");
            $linkNotification = $db->prepare("
                UPDATE cancel_broadcasts
                   SET notification_id = :nid
                 WHERE cancelled_appointment_id = :cid
                   AND recipient_user_id = :uid
            ");

            foreach ($recipients as $userId) {
                $userId = (int) $userId;
                $insertBroadcast->execute([':cid' => $cancelledAppointmentId, ':uid' => $userId]);
                if ($insertBroadcast->rowCount() === 0) {
                    continue;
                }

                Notifier::notify($db, $userId, 'slot_available', $title, $msg, $link);
                $nid = (int) $db->lastInsertId();
                if ($nid > 0) {
                    $linkNotification->execute([
                        ':nid' => $nid,
                        ':cid' => $cancelledAppointmentId,
                        ':uid' => $userId,
                    ]);
                }
                $count++;
            }

            return $count;

        } catch (Exception $e) {
            error_log("CancelBroadcaster error: " . $e->getMessage());
            return 0;
        }
    }
}
