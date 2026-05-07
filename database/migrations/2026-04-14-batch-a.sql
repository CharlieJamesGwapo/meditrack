-- Batch A: cancellation metadata + in-app notifications
-- 2026-04-14

ALTER TABLE appointments
  ADD COLUMN cancelled_by ENUM('patient','doctor','admin','system') NULL AFTER cancelled_at,
  ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by;

CREATE TABLE IF NOT EXISTS notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
