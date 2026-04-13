# Batch A — Appointments & Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add patient cancellation cutoff, doctor emergency day-cancel flow, booking/cancellation email notifications via existing Mailer, persistent in-app notifications with bell inbox, and a Mon–Sun weekly schedule view for doctors and admins.

**Architecture:** Extend existing `utils/Mailer.php` with new email types; add a new `notifications` table plus `notify()` helper used from all appointment mutation endpoints; enforce cancellation cutoff in `api/patient/cancel-appointment.php`; add `api/doctor/cancel-day.php` and `api/doctor/get-weekly-appointments.php`; add bell icon + weekly grid UI to `patient-dashboard.html`, `doctor-dashboard.html`, and `admin-dashboard.html`, following existing Tailwind CDN + vanilla JS patterns.

**Tech Stack:** PHP 8 + PDO, MySQL/MariaDB, vanilla JS, Tailwind CDN, existing cPanel SMTP Mailer.

**Testing approach:** This repo has no unit test infrastructure. Verification is manual (curl for APIs, browser for UI) with exact commands per step. Each task ends with a commit.

**Spec:** `docs/superpowers/specs/2026-04-14-batch-a-appointments-notifications-design.md`

---

## File Structure

**New files:**
- `database/migrations/2026-04-14-batch-a.sql` — schema changes
- `utils/Notifier.php` — `notify($db, $user_id, $type, $title, $message, $link)` helper
- `api/notifications/list.php` — list recent + unread count
- `api/notifications/mark-read.php` — mark single or all read
- `api/doctor/cancel-day.php` — doctor/admin day-cancel endpoint
- `api/doctor/get-weekly-appointments.php` — weekly grid data
- `assets/js/notifications-bell.js` — shared bell icon component
- `assets/js/weekly-schedule.js` — shared weekly grid component

**Modified files:**
- `utils/Mailer.php` — add `sendCancellationConfirmation`, `sendDayCancelled`
- `api/patient/cancel-appointment.php` — cutoff enforcement + notify + email
- `api/patient/book-appointment.php` — send booking email + in-app notification
- `pages/patient-dashboard.html` — bell icon; disable cancel past cutoff
- `pages/doctor-dashboard.html` — bell; "Mark day unavailable" button; weekly view tab
- `pages/admin-dashboard.html` — bell; day-cancel on behalf of doctor; weekly view tab
- `database/schema.sql` — reference-schema sync (notifications table + new appointment columns)

---

## Task 1: Database migration

**Files:**
- Create: `database/migrations/2026-04-14-batch-a.sql`
- Modify: `database/schema.sql`

- [ ] **Step 1: Write the migration file**

Create `database/migrations/2026-04-14-batch-a.sql` with this exact content:

```sql
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
```

- [ ] **Step 2: Apply migration locally**

Run:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < database/migrations/2026-04-14-batch-a.sql
```
Expected: no output (success). If DB name differs, check `config/database.php`.

- [ ] **Step 3: Verify schema**

Run:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SHOW COLUMNS FROM appointments LIKE 'cancel%'; SHOW TABLES LIKE 'notifications';"
```
Expected: rows for `cancelled_at`, `cancelled_by`, `cancel_reason`, and a `notifications` table.

- [ ] **Step 4: Sync reference schema**

Edit `database/schema.sql`:
1. Add `DROP TABLE IF EXISTS notifications;` near the top drop block if missing (it already is per inspection — leave as-is).
2. In the `CREATE TABLE appointments` block, after the `cancelled_at DATETIME NULL,` line, insert:
   ```
       cancelled_by ENUM('patient','doctor','admin','system') NULL,
       cancel_reason VARCHAR(255) NULL,
   ```
3. Before the `-- SEED DATA` comment, append:
   ```sql
   CREATE TABLE notifications (
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
   ```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026-04-14-batch-a.sql database/schema.sql
git commit -m "feat(db): add cancellation metadata and notifications table"
```

---

## Task 2: Notifier helper

**Files:**
- Create: `utils/Notifier.php`

- [ ] **Step 1: Write the helper**

Create `utils/Notifier.php`:

```php
<?php
/**
 * In-app notification helper.
 * Inserts a row into `notifications`. Never throws — logs on failure.
 */
class Notifier {
    /**
     * @param PDO    $db
     * @param int    $user_id  Recipient user id
     * @param string $type     Event type, e.g. "booking_confirmed"
     * @param string $title    Short headline
     * @param string $message  Body text
     * @param string|null $link Optional in-app link
     * @return bool success
     */
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
```

- [ ] **Step 2: Verify syntax**

Run:
```bash
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/utils/Notifier.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Smoke test via CLI**

Run:
```bash
php -r "require '/Applications/XAMPP/xamppfiles/htdocs/meditrack/config/database.php'; require '/Applications/XAMPP/xamppfiles/htdocs/meditrack/utils/Notifier.php'; \$db=(new Database())->getConnection(); var_dump(Notifier::notify(\$db, 1, 'test', 'Test title', 'Test body'));"
```
Expected: `bool(true)`.

Then verify the row landed:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT id,user_id,type,title FROM notifications ORDER BY id DESC LIMIT 1;"
```
Expected: the test row. Delete it:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "DELETE FROM notifications WHERE type='test';"
```

- [ ] **Step 4: Commit**

```bash
git add utils/Notifier.php
git commit -m "feat(notifications): add Notifier helper"
```

---

## Task 3: Notifications API endpoints

**Files:**
- Create: `api/notifications/list.php`
- Create: `api/notifications/mark-read.php`

- [ ] **Step 1: Create list endpoint**

Create `api/notifications/list.php`:

```php
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
```

- [ ] **Step 2: Create mark-read endpoint**

Create `api/notifications/mark-read.php`:

```php
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
```

- [ ] **Step 3: Syntax check**

Run:
```bash
php -l api/notifications/list.php && php -l api/notifications/mark-read.php
```
Expected: both report `No syntax errors detected`.

- [ ] **Step 4: Manual API test**

Insert a test notification for user 1, then hit the endpoints via browser at `http://localhost/meditrack/api/notifications/list.php` while logged in as admin (user 1). Expected JSON: `{"success":true,"notifications":[{...}],"unread_count":1}`.

Then POST to `mark-read.php` with `{"all":true}` using the browser devtools console:
```js
fetch('/meditrack/api/notifications/mark-read.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:'{"all":true}'}).then(r=>r.json()).then(console.log)
```
Expected: `{success: true}`. Re-hit list — `unread_count` should be 0.

- [ ] **Step 5: Commit**

```bash
git add api/notifications/list.php api/notifications/mark-read.php
git commit -m "feat(notifications): add list and mark-read API endpoints"
```

---

## Task 4: Extend Mailer with cancellation emails

**Files:**
- Modify: `utils/Mailer.php`

- [ ] **Step 1: Add two new methods**

Open `utils/Mailer.php`. Immediately after the closing `}` of `sendAppointmentConfirmation(...)` (before `private function sendCommand`), insert:

```php
    /**
     * Send patient self-cancellation confirmation
     */
    public function sendCancellationConfirmation($to, $patientName, $appointmentNumber, $date, $time) {
        $subject = "IM-OPD - Appointment Cancelled #{$appointmentNumber}";
        $formattedDate = date('F j, Y', strtotime($date));
        $formattedTime = date('g:i A', strtotime($time));
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#0f766e,#0284c7);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>Hi <strong>{$patientName}</strong>,</p>
                <p style='color:#374151;'>Your appointment has been cancelled as requested.</p>
                <table style='width:100%;margin:20px 0;border-collapse:collapse;'>
                    <tr><td style='padding:8px;color:#6b7280;'>Appointment #</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$appointmentNumber}</td></tr>
                    <tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;'>Date</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedDate}</td></tr>
                    <tr><td style='padding:8px;color:#6b7280;'>Time</td><td style='padding:8px;font-weight:bold;color:#374151;'>{$formattedTime}</td></tr>
                </table>
                <p style='color:#6b7280;font-size:14px;'>You may book a new appointment anytime from your dashboard.</p>
            </div>
        </div>";
        return $this->send($to, $subject, $body);
    }

    /**
     * Send doctor day-cancelled notification
     */
    public function sendDayCancelled($to, $patientName, $appointmentNumber, $date, $time, $reason) {
        $subject = "IM-OPD - Appointment Cancelled (Doctor Unavailable) #{$appointmentNumber}";
        $formattedDate = date('F j, Y', strtotime($date));
        $formattedTime = date('g:i A', strtotime($time));
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;'>
            <div style='text-align:center;padding:20px;background:linear-gradient(135deg,#b91c1c,#ea580c);border-radius:12px 12px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:24px;'>IM-OPD</h1>
            </div>
            <div style='padding:30px;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p style='color:#374151;'>Hi <strong>{$patientName}</strong>,</p>
                <p style='color:#374151;'>We regret to inform you that your appointment on <strong>{$formattedDate}</strong> at <strong>{$formattedTime}</strong> (#{$appointmentNumber}) has been cancelled because the doctor is unavailable.</p>
                <p style='color:#374151;'><strong>Reason:</strong> {$safeReason}</p>
                <p style='color:#6b7280;font-size:14px;'>Please do not come to the clinic on this date. You may rebook an appointment from your dashboard at your earliest convenience.</p>
            </div>
        </div>";
        return $this->send($to, $subject, $body);
    }

```

- [ ] **Step 2: Syntax check**

Run:
```bash
php -l utils/Mailer.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add utils/Mailer.php
git commit -m "feat(mailer): add cancellation and day-cancelled email templates"
```

---

## Task 5: Patient cancel cutoff + notifications

**Files:**
- Modify: `api/patient/cancel-appointment.php`

- [ ] **Step 1: Rewrite endpoint with cutoff + side effects**

Replace entire contents of `api/patient/cancel-appointment.php` with:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Notifier.php';
require_once __DIR__ . '/../../utils/Mailer.php';

const CANCEL_CUTOFF_HOURS = 2;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, full_name FROM patients WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    $stmt = $db->prepare("
        SELECT a.id, a.status, a.appointment_number, a.appointment_date, a.appointment_time,
               u.email AS patient_email
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE a.id = :aid AND a.patient_id = :pid
        LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id, ':pid' => $patient['id']]);
    $appt = $stmt->fetch();

    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if ($appt['status'] !== 'scheduled') {
        sendJSON(['success' => false, 'message' => 'Only scheduled appointments can be cancelled'], 400);
    }

    // Cutoff enforcement: must be at least CANCEL_CUTOFF_HOURS before appointment
    $apptTs   = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
    $cutoffTs = $apptTs - (CANCEL_CUTOFF_HOURS * 3600);
    if (time() > $cutoffTs) {
        sendJSON([
            'success' => false,
            'message' => 'Cancellation window closed. Please contact the clinic.',
        ], 400);
    }

    $db->prepare("
        UPDATE appointments
           SET status = 'cancelled', cancelled_at = NOW(),
               cancelled_by = 'patient', cancel_reason = NULL
         WHERE id = :aid
    ")->execute([':aid' => $appointment_id]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'patient', 'UPDATE', 'Appointments', $appointment_id, "Appointment cancelled by patient");

    // In-app notification
    Notifier::notify(
        $db, $userId, 'appointment_cancelled_by_patient',
        'Appointment cancelled',
        "Your appointment #{$appt['appointment_number']} on {$appt['appointment_date']} has been cancelled.",
        'patient-dashboard.html'
    );

    // Email (best-effort)
    try {
        (new Mailer())->sendCancellationConfirmation(
            $appt['patient_email'],
            $patient['full_name'],
            $appt['appointment_number'],
            $appt['appointment_date'],
            $appt['appointment_time']
        );
    } catch (Exception $e) {
        error_log("cancel-appointment email error: " . $e->getMessage());
    }

    sendJSON(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    error_log("cancel-appointment (patient) error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to cancel appointment'], 500);
}
```

- [ ] **Step 2: Syntax check**

Run:
```bash
php -l api/patient/cancel-appointment.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual verification — past cutoff**

In MySQL, temporarily set a scheduled appointment for 1 hour from now (within cutoff):
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "UPDATE appointments SET status='scheduled', appointment_date=CURDATE(), appointment_time=DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 HOUR),'%H:%i:00') WHERE id=(SELECT min_id FROM (SELECT MIN(id) AS min_id FROM appointments) t);"
```
Log in as the patient owner of that appointment, click Cancel in the dashboard. Expected: error toast *"Cancellation window closed. Please contact the clinic."*

- [ ] **Step 4: Manual verification — before cutoff**

Update the same appointment to 5 hours in future:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "UPDATE appointments SET status='scheduled', appointment_time=DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 5 HOUR),'%H:%i:00') WHERE id=(SELECT min_id FROM (SELECT MIN(id) AS min_id FROM appointments) t);"
```
Click Cancel. Expected: success, status becomes `cancelled`, `cancelled_by='patient'`, row in `notifications`, email received (if SMTP configured).

Verify:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT id,status,cancelled_by,cancel_reason FROM appointments ORDER BY updated_at DESC LIMIT 1; SELECT type,title FROM notifications ORDER BY id DESC LIMIT 1;"
```

- [ ] **Step 5: Commit**

```bash
git add api/patient/cancel-appointment.php
git commit -m "feat(appointments): enforce 2-hour cancel cutoff + notify on cancel"
```

---

## Task 6: Booking confirmation email + notification

**Files:**
- Modify: `api/patient/book-appointment.php`

- [ ] **Step 1: Add requires**

In `api/patient/book-appointment.php`, directly after line 4 (`require_once __DIR__ . '/../../utils/QRCodeGenerator.php';`), insert:

```php
require_once __DIR__ . '/../../utils/Mailer.php';
require_once __DIR__ . '/../../utils/Notifier.php';
```

- [ ] **Step 2: Send email + notification after commit**

In `api/patient/book-appointment.php`, find the block starting with `logActivity($db, $userId, ...` (currently around line 117). Immediately after that line and before `sendJSON([...` around line 119, insert:

```php
    // Fetch patient email for notification
    try {
        $stmt = $db->prepare("SELECT email FROM users WHERE id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $patientEmail = $stmt->fetchColumn() ?: null;

        Notifier::notify(
            $db, $userId, 'booking_confirmed',
            'Appointment booked',
            "Your appointment #{$appointment_number} on {$appointment_date} at {$appointment_time} is confirmed. You can cancel up to 2 hours before your scheduled time.",
            'patient-dashboard.html'
        );

        if ($patientEmail) {
            (new Mailer())->sendAppointmentConfirmation(
                $patientEmail,
                $patient['full_name'],
                $appointment_number,
                $appointment_date,
                $appointment_time,
                $doctor['full_name']
            );
        }
    } catch (Exception $e) {
        error_log("book-appointment notify/email error: " . $e->getMessage());
    }

```

- [ ] **Step 3: Syntax check**

Run:
```bash
php -l api/patient/book-appointment.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification**

Log in as a patient, book a new appointment. Expected:
- Success toast/redirect.
- A new row in `notifications` with `type='booking_confirmed'`:
  ```bash
  /Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT id,type,title,message FROM notifications ORDER BY id DESC LIMIT 1;"
  ```
- If SMTP configured: confirmation email received.

- [ ] **Step 5: Commit**

```bash
git add api/patient/book-appointment.php
git commit -m "feat(appointments): send booking confirmation email + notification"
```

---

## Task 7: Doctor/admin cancel-day endpoint

**Files:**
- Create: `api/doctor/cancel-day.php`

- [ ] **Step 1: Write endpoint**

Create `api/doctor/cancel-day.php`:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Notifier.php';
require_once __DIR__ . '/../../utils/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('doctor') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$date   = sanitizeInput($input['date'] ?? '');
$reason = trim((string) ($input['reason'] ?? ''));
$targetDoctorId = (int) ($input['doctor_id'] ?? 0);

if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    sendJSON(['success' => false, 'message' => 'Valid date is required (YYYY-MM-DD)'], 400);
}
if ($reason === '') {
    sendJSON(['success' => false, 'message' => 'Reason is required'], 400);
}
if ($date < date('Y-m-d')) {
    sendJSON(['success' => false, 'message' => 'Date cannot be in the past'], 400);
}

try {
    $db     = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = $_SESSION['role'] ?? '';
    $actor  = $role === 'admin' ? 'admin' : 'doctor';

    // Resolve doctor id
    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doctor_id = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        $doctor_id = $targetDoctorId;
    }
    if (!$doctor_id) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }

    // Fetch affected appointments BEFORE update
    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_time, a.appointment_date,
               p.full_name AS patient_name, u.id AS patient_user_id, u.email AS patient_email
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          JOIN users    u ON u.id = p.user_id
         WHERE a.doctor_id = :did
           AND a.appointment_date = :date
           AND a.status = 'scheduled'
    ");
    $stmt->execute([':did' => $doctor_id, ':date' => $date]);
    $affected = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $db->beginTransaction();
    $upd = $db->prepare("
        UPDATE appointments
           SET status = 'cancelled', cancelled_at = NOW(),
               cancelled_by = :actor, cancel_reason = :reason
         WHERE doctor_id = :did
           AND appointment_date = :date
           AND status = 'scheduled'
    ");
    $upd->execute([
        ':actor'  => $actor,
        ':reason' => $reason,
        ':did'    => $doctor_id,
        ':date'   => $date,
    ]);
    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', $role, 'UPDATE', 'Appointments', 0,
        "Day cancelled for doctor $doctor_id on $date ($reason): " . count($affected) . " appointments");

    // Fan-out notifications + emails (best-effort)
    $mailer = new Mailer();
    foreach ($affected as $a) {
        Notifier::notify(
            $db, (int) $a['patient_user_id'], 'day_cancelled',
            'Appointment cancelled — doctor unavailable',
            "Your appointment #{$a['appointment_number']} on {$a['appointment_date']} has been cancelled. Reason: $reason. Please rebook.",
            'patient-dashboard.html'
        );
        try {
            if (!empty($a['patient_email'])) {
                $mailer->sendDayCancelled(
                    $a['patient_email'], $a['patient_name'],
                    $a['appointment_number'], $a['appointment_date'], $a['appointment_time'], $reason
                );
            }
        } catch (Exception $e) {
            error_log("cancel-day email error: " . $e->getMessage());
        }
    }

    sendJSON([
        'success' => true,
        'cancelled_count' => count($affected),
        'message' => count($affected) . " appointment(s) cancelled and patients notified.",
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("cancel-day error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to cancel day'], 500);
}
```

- [ ] **Step 2: Syntax check**

Run:
```bash
php -l api/doctor/cancel-day.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual verification**

Ensure at least 2 scheduled appointments exist on a chosen future date for doctor 1. From the doctor browser session, call via devtools console:
```js
fetch('/meditrack/api/doctor/cancel-day.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({date:'2026-05-01', reason:'Emergency test'})}).then(r=>r.json()).then(console.log)
```
Expected: `{success:true, cancelled_count:N, ...}`. Verify:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT id,status,cancelled_by,cancel_reason FROM appointments WHERE appointment_date='2026-05-01';"
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT COUNT(*) FROM notifications WHERE type='day_cancelled';"
```

- [ ] **Step 4: Commit**

```bash
git add api/doctor/cancel-day.php
git commit -m "feat(appointments): add doctor/admin cancel-day endpoint"
```

---

## Task 8: Weekly appointments endpoint

**Files:**
- Create: `api/doctor/get-weekly-appointments.php`

- [ ] **Step 1: Write endpoint**

Create `api/doctor/get-weekly-appointments.php`:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('doctor') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$weekStart = sanitizeInput($_GET['week_start'] ?? '');
$targetDoctorId = (int) ($_GET['doctor_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    // Default to current week's Monday
    $weekStart = date('Y-m-d', strtotime('monday this week'));
}
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

try {
    $db     = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = $_SESSION['role'] ?? '';

    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doctor_id = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        $doctor_id = $targetDoctorId;
        if (!$doctor_id) {
            // Admin default: first active doctor
            $doctor_id = (int) $db->query("SELECT id FROM doctors WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
        }
    }

    if (!$doctor_id) {
        sendJSON(['success' => true, 'week_start' => $weekStart, 'week_end' => $weekEnd, 'appointments' => []]);
    }

    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, p.full_name AS patient_name
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
         WHERE a.doctor_id = :did
           AND a.appointment_date BETWEEN :start AND :end
         ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->execute([':did' => $doctor_id, ':start' => $weekStart, ':end' => $weekEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSON([
        'success' => true,
        'doctor_id' => $doctor_id,
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'appointments' => $rows,
    ]);
} catch (Exception $e) {
    error_log("get-weekly-appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load weekly schedule'], 500);
}
```

- [ ] **Step 2: Syntax check**

Run:
```bash
php -l api/doctor/get-weekly-appointments.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual verification**

Logged in as doctor, visit:
```
http://localhost/meditrack/api/doctor/get-weekly-appointments.php?week_start=2026-04-13
```
Expected JSON with `week_start`, `week_end`, and `appointments` array.

- [ ] **Step 4: Commit**

```bash
git add api/doctor/get-weekly-appointments.php
git commit -m "feat(appointments): add weekly schedule endpoint"
```

---

## Task 9: Bell icon shared component

**Files:**
- Create: `assets/js/notifications-bell.js`

- [ ] **Step 1: Write shared component**

Create `assets/js/notifications-bell.js`:

```javascript
/* Notifications bell dropdown.
 * Usage: call window.NotificationsBell.mount('#bell-container')
 * Container must be empty. Injects a button + dropdown; polls every 30s.
 */
(function () {
  const API = {
    list: '../api/notifications/list.php',
    read: '../api/notifications/mark-read.php',
  };

  function el(html) {
    const d = document.createElement('div');
    d.innerHTML = html.trim();
    return d.firstElementChild;
  }

  function fmtTime(s) {
    try { return new Date(s.replace(' ', 'T')).toLocaleString(); } catch { return s; }
  }

  async function fetchList() {
    const r = await fetch(API.list, { credentials: 'same-origin' });
    return r.json();
  }

  async function markRead(body) {
    const r = await fetch(API.read, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    return r.json();
  }

  function render(root, data) {
    const badge = root.querySelector('[data-badge]');
    const list = root.querySelector('[data-list]');
    const unread = data.unread_count || 0;
    badge.textContent = unread > 99 ? '99+' : String(unread);
    badge.style.display = unread > 0 ? 'inline-flex' : 'none';

    if (!data.notifications || data.notifications.length === 0) {
      list.innerHTML = '<div class="p-4 text-sm text-slate-500">No notifications</div>';
      return;
    }
    list.innerHTML = '';
    data.notifications.forEach(n => {
      const item = el(`
        <button class="w-full text-left p-3 border-b border-slate-100 hover:bg-slate-50 ${n.is_read ? '' : 'bg-teal-50'}" data-id="${n.id}" ${n.link ? `data-link="${n.link}"` : ''}>
          <div class="font-semibold text-sm text-slate-800">${escapeHtml(n.title)}</div>
          <div class="text-xs text-slate-600 mt-0.5">${escapeHtml(n.message)}</div>
          <div class="text-[10px] text-slate-400 mt-1">${fmtTime(n.created_at)}</div>
        </button>
      `);
      item.addEventListener('click', async () => {
        await markRead({ id: Number(n.id) });
        if (n.link) { window.location.href = n.link; }
        else { refresh(root); }
      });
      list.appendChild(item);
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  async function refresh(root) {
    try {
      const data = await fetchList();
      if (data.success) render(root, data);
    } catch (e) { /* ignore */ }
  }

  function mount(selector) {
    const container = document.querySelector(selector);
    if (!container) return;
    container.innerHTML = '';
    const root = el(`
      <div class="relative">
        <button data-toggle class="relative p-2 rounded-full hover:bg-slate-100">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span data-badge class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 items-center justify-center" style="display:none;"></span>
        </button>
        <div data-dropdown class="hidden absolute right-0 mt-2 w-80 bg-white border border-slate-200 rounded-lg shadow-lg z-50">
          <div class="flex items-center justify-between p-3 border-b border-slate-100">
            <div class="font-semibold text-slate-800">Notifications</div>
            <button data-mark-all class="text-xs text-teal-700 hover:underline">Mark all read</button>
          </div>
          <div data-list class="max-h-96 overflow-y-auto"></div>
        </div>
      </div>
    `);
    container.appendChild(root);

    const toggle = root.querySelector('[data-toggle]');
    const dropdown = root.querySelector('[data-dropdown]');
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('hidden');
      if (!dropdown.classList.contains('hidden')) refresh(root);
    });
    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) dropdown.classList.add('hidden');
    });
    root.querySelector('[data-mark-all]').addEventListener('click', async (e) => {
      e.stopPropagation();
      await markRead({ all: true });
      refresh(root);
    });

    refresh(root);
    setInterval(() => refresh(root), 30000);
  }

  window.NotificationsBell = { mount };
})();
```

- [ ] **Step 2: Mount in each dashboard**

In `pages/patient-dashboard.html`, `pages/doctor-dashboard.html`, and `pages/admin-dashboard.html`:

1. Find the top header area (look for the user menu or logout button in the top bar). Immediately before the logout/user element, insert:
   ```html
   <div id="bell-container" class="mr-2"></div>
   ```
2. Before the closing `</body>`, add:
   ```html
   <script src="../assets/js/notifications-bell.js"></script>
   <script>NotificationsBell.mount('#bell-container');</script>
   ```
   If the file already has a bundled script block, append the mount call there instead of creating a new one.

- [ ] **Step 3: Manual verification**

Log in as each role. Expected: a bell icon appears in the top bar; clicking opens a dropdown; if notifications exist, they appear with correct read/unread coloring; clicking "Mark all read" clears the badge.

- [ ] **Step 4: Commit**

```bash
git add assets/js/notifications-bell.js pages/patient-dashboard.html pages/doctor-dashboard.html pages/admin-dashboard.html
git commit -m "feat(ui): add notifications bell to all dashboards"
```

---

## Task 10: Disable cancel UI past cutoff + confirm dialog

**Files:**
- Modify: `pages/patient-dashboard.html`

- [ ] **Step 1: Find appointment rendering**

Open `pages/patient-dashboard.html` and locate the JavaScript that renders appointment cards or rows (look for the function that calls `api/patient/get-appointments.php`, probably named `loadAppointments`, `renderAppointments`, or similar).

- [ ] **Step 2: Add cutoff helper**

Inside the dashboard's main `<script>` block, near the top, add:

```javascript
const CANCEL_CUTOFF_HOURS = 2;
function canCancelAppointment(appt) {
  if (appt.status !== 'scheduled') return false;
  const apptTs = new Date(`${appt.appointment_date}T${appt.appointment_time}`).getTime();
  return Date.now() <= apptTs - CANCEL_CUTOFF_HOURS * 3600 * 1000;
}
```

- [ ] **Step 3: Update cancel button rendering**

Find the place where the Cancel button is rendered for each appointment. Replace the unconditional Cancel button with:

```javascript
// Inside the appointment card template:
${appt.status === 'scheduled'
  ? (canCancelAppointment(appt)
      ? `<button class="btn-cancel px-3 py-1.5 text-sm rounded bg-red-600 text-white hover:bg-red-700" data-id="${appt.id}">Cancel</button>`
      : `<span class="text-xs text-slate-500 italic">Cancellation window closed</span>`)
  : ''}
```

Exact syntax depends on whether the dashboard uses template literals or DOM building — match the existing style.

- [ ] **Step 4: Wrap click handler in confirm**

Find the existing click handler that calls `api/patient/cancel-appointment.php`. Wrap the handler so the request only fires after user confirmation:

```javascript
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-cancel');
  if (!btn) return;
  if (!confirm('Cancel this appointment? You can rebook anytime.')) return;
  const id = Number(btn.dataset.id);
  const r = await fetch('../api/patient/cancel-appointment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ appointment_id: id }),
  });
  const data = await r.json();
  if (data.success) {
    alert('Appointment cancelled.');
    loadAppointments(); // or whatever the refresh function is called
  } else {
    alert(data.message || 'Failed to cancel.');
  }
});
```

If an existing handler already exists, modify that handler to add the `confirm()` check rather than adding a second handler.

- [ ] **Step 5: Show cancellation policy on booking success**

Find the success toast/message for booking. Append: *" You can cancel up to 2 hours before your scheduled time."*

- [ ] **Step 6: Manual verification**

- Book an appointment with date ≥ 6 hours from now → Cancel button visible → click → confirm dialog → success.
- Set an appointment to 1h from now via MySQL → Cancel button replaced with "Cancellation window closed".

- [ ] **Step 7: Commit**

```bash
git add pages/patient-dashboard.html
git commit -m "feat(ui): enforce cancel cutoff in patient dashboard + confirm dialog"
```

---

## Task 11: Doctor/admin day-cancel UI

**Files:**
- Modify: `pages/doctor-dashboard.html`, `pages/admin-dashboard.html`

- [ ] **Step 1: Add button + modal to doctor dashboard**

In `pages/doctor-dashboard.html`, find the top of the appointments section. Add a button:

```html
<button id="btn-mark-day-unavailable" class="px-3 py-1.5 text-sm rounded bg-red-600 text-white hover:bg-red-700">Mark day unavailable</button>
```

And a modal (place near end of `<body>`):

```html
<div id="day-cancel-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Mark day unavailable</h3>
    <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
    <input id="dc-date" type="date" class="w-full border border-slate-300 rounded px-3 py-2 mb-3">
    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
    <textarea id="dc-reason" rows="3" class="w-full border border-slate-300 rounded px-3 py-2 mb-4" placeholder="e.g., Emergency, sick leave"></textarea>
    <div id="dc-preview" class="text-sm text-slate-600 mb-4"></div>
    <div class="flex gap-2 justify-end">
      <button id="dc-close" class="px-3 py-1.5 text-sm rounded bg-slate-200 hover:bg-slate-300">Close</button>
      <button id="dc-confirm" class="px-3 py-1.5 text-sm rounded bg-red-600 text-white hover:bg-red-700">Cancel all on this date</button>
    </div>
  </div>
</div>
```

- [ ] **Step 2: Add JS handlers**

Add inside the dashboard's main `<script>` block:

```javascript
const dcModal = document.getElementById('day-cancel-modal');
const dcDate = document.getElementById('dc-date');
const dcReason = document.getElementById('dc-reason');
const dcPreview = document.getElementById('dc-preview');
const dcConfirm = document.getElementById('dc-confirm');

document.getElementById('btn-mark-day-unavailable').addEventListener('click', () => {
  dcDate.value = new Date().toISOString().slice(0, 10);
  dcReason.value = '';
  dcPreview.textContent = '';
  dcModal.classList.remove('hidden');
});
document.getElementById('dc-close').addEventListener('click', () => dcModal.classList.add('hidden'));

dcDate.addEventListener('change', async () => {
  if (!dcDate.value) return;
  const r = await fetch(`../api/doctor/get-weekly-appointments.php?week_start=${dcDate.value}`, { credentials: 'same-origin' });
  const data = await r.json();
  const count = (data.appointments || []).filter(a => a.appointment_date === dcDate.value && a.status === 'scheduled').length;
  dcPreview.textContent = `${count} scheduled appointment(s) on ${dcDate.value} will be cancelled and patients notified.`;
});

dcConfirm.addEventListener('click', async () => {
  const date = dcDate.value;
  const reason = dcReason.value.trim();
  if (!date || !reason) { alert('Date and reason are required.'); return; }
  if (!confirm('This action cannot be undone. Continue?')) return;
  const r = await fetch('../api/doctor/cancel-day.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ date, reason }),
  });
  const data = await r.json();
  if (data.success) {
    alert(`Cancelled ${data.cancelled_count} appointment(s).`);
    dcModal.classList.add('hidden');
    if (typeof loadAppointments === 'function') loadAppointments();
  } else {
    alert(data.message || 'Failed to cancel day.');
  }
});
```

- [ ] **Step 3: Mirror in admin dashboard**

Copy the same button, modal, and JS into `pages/admin-dashboard.html`. Additionally, in the admin version, add a doctor selector next to the date:

```html
<label class="block text-sm font-medium text-slate-700 mb-1">Doctor</label>
<select id="dc-doctor" class="w-full border border-slate-300 rounded px-3 py-2 mb-3"></select>
```

In admin JS, populate the selector from the existing doctors API (look for where the admin dashboard already loads doctors — reuse that list). In `dcConfirm`, include `doctor_id: Number(document.getElementById('dc-doctor').value)` in the POST body. In the preview fetch, append `&doctor_id=${...}`.

- [ ] **Step 4: Manual verification**

- As doctor, pick today's date (ensure ≥1 scheduled appointment), confirm → expect cancelled_count > 0, patient row flips to cancelled, patient's bell shows new notification.
- As admin, same flow using doctor selector.

- [ ] **Step 5: Commit**

```bash
git add pages/doctor-dashboard.html pages/admin-dashboard.html
git commit -m "feat(ui): add day-cancel modal for doctor and admin dashboards"
```

---

## Task 12: Weekly schedule grid

**Files:**
- Create: `assets/js/weekly-schedule.js`
- Modify: `pages/doctor-dashboard.html`, `pages/admin-dashboard.html`

- [ ] **Step 1: Write shared component**

Create `assets/js/weekly-schedule.js`:

```javascript
/* Weekly schedule grid.
 * Usage: WeeklySchedule.mount('#container', { role: 'doctor' | 'admin', doctorIdGetter: () => null })
 */
(function () {
  const STATUS_COLORS = {
    scheduled: 'bg-blue-100 text-blue-800',
    checked_in: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-purple-100 text-purple-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-slate-200 text-slate-500 line-through',
    no_show: 'bg-slate-200 text-slate-500',
  };

  function mondayOf(d) {
    const x = new Date(d);
    const day = x.getDay() || 7; // Sun -> 7
    if (day !== 1) x.setDate(x.getDate() - (day - 1));
    x.setHours(0, 0, 0, 0);
    return x;
  }
  function addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function fmt(d) { return d.toISOString().slice(0, 10); }
  function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

  async function fetchWeek(weekStart, doctorId) {
    const qs = new URLSearchParams({ week_start: weekStart });
    if (doctorId) qs.set('doctor_id', String(doctorId));
    const r = await fetch(`../api/doctor/get-weekly-appointments.php?${qs}`, { credentials: 'same-origin' });
    return r.json();
  }

  function render(root, weekStart, data) {
    const days = [];
    for (let i = 0; i < 7; i++) days.push(addDays(weekStart, i));
    const byDate = {};
    (data.appointments || []).forEach(a => {
      (byDate[a.appointment_date] ||= []).push(a);
    });

    const grid = root.querySelector('[data-grid]');
    const label = root.querySelector('[data-label]');
    label.textContent = `${fmt(days[0])} – ${fmt(days[6])}`;

    grid.innerHTML = '';
    const header = document.createElement('div');
    header.className = 'grid grid-cols-7 gap-1 mb-1';
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach((name, i) => {
      const cell = document.createElement('div');
      cell.className = 'text-xs font-semibold text-slate-600 text-center p-1';
      cell.textContent = `${name} ${days[i].getDate()}`;
      header.appendChild(cell);
    });
    grid.appendChild(header);

    const body = document.createElement('div');
    body.className = 'grid grid-cols-7 gap-1';
    days.forEach(d => {
      const col = document.createElement('div');
      col.className = 'min-h-[160px] border border-slate-200 rounded p-1 bg-slate-50';
      const list = byDate[fmt(d)] || [];
      list.sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
      list.forEach(a => {
        const chip = document.createElement('div');
        chip.className = `text-[11px] rounded px-1.5 py-1 mb-1 ${STATUS_COLORS[a.status] || 'bg-slate-100'}`;
        chip.innerHTML = `<div class="font-semibold">${a.appointment_time.slice(0,5)}</div><div>${esc(a.patient_name)}</div>`;
        col.appendChild(chip);
      });
      body.appendChild(col);
    });
    grid.appendChild(body);
  }

  function mount(selector, opts = {}) {
    const container = document.querySelector(selector);
    if (!container) return;
    const doctorIdGetter = opts.doctorIdGetter || (() => null);
    let weekStart = mondayOf(new Date());

    container.innerHTML = `
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold text-slate-800">Weekly Schedule</h3>
          <div class="flex items-center gap-2">
            <button data-prev class="px-2 py-1 text-sm rounded bg-slate-100 hover:bg-slate-200">◀</button>
            <span data-label class="text-sm font-medium text-slate-700"></span>
            <button data-this class="px-2 py-1 text-sm rounded bg-slate-100 hover:bg-slate-200">Today</button>
            <button data-next class="px-2 py-1 text-sm rounded bg-slate-100 hover:bg-slate-200">▶</button>
          </div>
        </div>
        <div data-grid></div>
      </div>
    `;

    async function refresh() {
      const data = await fetchWeek(fmt(weekStart), doctorIdGetter());
      if (data.success) render(container, weekStart, data);
    }

    container.querySelector('[data-prev]').addEventListener('click', () => { weekStart = addDays(weekStart, -7); refresh(); });
    container.querySelector('[data-next]').addEventListener('click', () => { weekStart = addDays(weekStart, 7); refresh(); });
    container.querySelector('[data-this]').addEventListener('click', () => { weekStart = mondayOf(new Date()); refresh(); });

    refresh();
    return { refresh, setWeek(d) { weekStart = mondayOf(d); refresh(); } };
  }

  window.WeeklySchedule = { mount };
})();
```

- [ ] **Step 2: Mount in doctor dashboard**

In `pages/doctor-dashboard.html`, add a container under the existing "Today's Appointments" section:

```html
<div id="weekly-schedule-container" class="mt-6"></div>
```

Before `</body>`, add:

```html
<script src="../assets/js/weekly-schedule.js"></script>
<script>WeeklySchedule.mount('#weekly-schedule-container', { role: 'doctor' });</script>
```

- [ ] **Step 3: Mount in admin dashboard**

In `pages/admin-dashboard.html`, add the same container. Because admin has a doctor selector, mount like:

```html
<div id="weekly-schedule-container" class="mt-6"></div>
<script src="../assets/js/weekly-schedule.js"></script>
<script>
  // Wire to existing admin doctor selector (adjust selector if needed)
  const weekly = WeeklySchedule.mount('#weekly-schedule-container', {
    role: 'admin',
    doctorIdGetter: () => {
      const sel = document.querySelector('#admin-doctor-select');
      return sel && sel.value ? Number(sel.value) : null;
    }
  });
  const sel = document.querySelector('#admin-doctor-select');
  if (sel) sel.addEventListener('change', () => weekly.refresh());
</script>
```

If the admin dashboard does not yet have a doctor selector, add one using the existing admin doctors API (look for the endpoint the admin page already calls to list doctors) and give it `id="admin-doctor-select"`.

- [ ] **Step 4: Manual verification**

- Doctor view: weekly grid shows current week's appointments, nav arrows work, status colors render.
- Admin view: same, plus changing doctor selector refreshes the grid.

- [ ] **Step 5: Commit**

```bash
git add assets/js/weekly-schedule.js pages/doctor-dashboard.html pages/admin-dashboard.html
git commit -m "feat(ui): add weekly schedule grid to doctor and admin dashboards"
```

---

## Final Verification

- [ ] **Walkthrough:** Book appointment as patient → confirm email + bell notification → cancel before cutoff → confirm email + bell notification. Set up another appointment, move its time to <2h via SQL, confirm Cancel button hidden and server rejects. Doctor marks a day unavailable → other patients' bells show "day cancelled" → their email arrives. Admin changes weekly view doctor → grid updates.

- [ ] **Schema self-check:**
  ```bash
  /Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SHOW COLUMNS FROM appointments; SHOW CREATE TABLE notifications\G"
  ```

- [ ] **Lint pass:**
  ```bash
  find api utils -name '*.php' -exec php -l {} \;
  ```
  Expected: all files `No syntax errors detected`.

---

## Out of Scope (per spec)

- Auto-rescheduling patients to other days.
- 24h reminder cron job.
- SMS notifications.
- Multi-doctor conflict resolution beyond existing.
