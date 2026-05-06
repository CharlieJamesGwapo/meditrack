-- database/migrations/2026-05-06-batch-c2.sql
-- Batch C2 — cancellation broadcast audit / dedupe table.
-- Idempotent: re-running on a partially-migrated DB should not error.

CREATE TABLE IF NOT EXISTS cancel_broadcasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cancelled_appointment_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  notification_id INT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_broadcast_recipient (cancelled_appointment_id, recipient_user_id),
  FOREIGN KEY (cancelled_appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
  INDEX idx_recipient (recipient_user_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
