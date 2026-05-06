# Batch C2 — Cancellation Broadcast & Self-Reschedule (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a patient self-cancels, broadcast a `slot_available` notification to a bounded set of other patients who could plausibly take the freed slot, with a one-click deep link into the booking page pre-filled to the freed date/time.

**Architecture:** Layered on top of Batch A's notification inbox. A new `utils/CancelBroadcaster.php` class encapsulates the recipient-set query (active patients, no clashing appointment that day, rate-limited at 3 unread `slot_available` per 24h, capped at the 20 most-recently-active). It's invoked from `api/patient/cancel-appointment.php` after the existing cancellation logic commits. A new `cancel_broadcasts` table audits + dedupes recipients per cancellation. The booking page reads `date`, `doctor`, `time` query params and pre-fills its picker.

**Tech Stack:** PHP 8 + PDO + MySQL/MariaDB (XAMPP), vanilla JS frontend. No build step. No automated tests — verification is manual XAMPP per project convention.

**Spec:** `docs/superpowers/specs/2026-05-06-batch-c2-cancel-broadcast-reschedule-design.md`

**Branch:** Create `feature/batch-c2-cancel-broadcast-reschedule` from `master` (or from your post-C1 merge point) before starting.

---

## File Structure

**New files:**

| Path | Responsibility |
|---|---|
| `database/migrations/2026-05-06-batch-c2.sql` | Idempotent migration that creates `cancel_broadcasts` |
| `utils/CancelBroadcaster.php` | Static helper: computes recipient set, inserts notification + audit rows, returns count |

**Modified files:**

| Path | Change |
|---|---|
| `database/schema.sql` | Append `cancel_broadcasts` CREATE TABLE; add to DROP block |
| `config/config.php` | Add `define('CANCEL_BROADCAST_LIMIT', 20)` |
| `api/patient/cancel-appointment.php` | Call `CancelBroadcaster::broadcast(...)` after the existing cancellation commits and the cancelling patient's own confirmation notification |
| `pages/qr-booking.html` | Read `date`, `doctor`, `time` query params; pre-select; show "slot taken" notice when no longer available |

---

## Conventions

- **No automated tests.** Each task ends with concrete manual verification steps performed in a local XAMPP browser session.
- **Commit after each task** with a focused conventional-commit message.
- **Never use `git add -A` or `git add .`** — there are untracked files at repo root that must NOT be committed. Use exact file paths.
- **PHP role check** — patient cancellation already uses `hasRole('patient')`; `CancelBroadcaster` runs server-side so doesn't need its own auth.
- **Notification pattern** uses the existing `Notifier::notify($db, $user_id, $type, $title, $message, $link)` (see `utils/Notifier.php`).

---

## Task 1: DB Migration — `cancel_broadcasts`

**Files:**
- Create: `database/migrations/2026-05-06-batch-c2.sql`
- Modify: `database/schema.sql`

- [ ] **Step 1: Create the migration file**

Create `database/migrations/2026-05-06-batch-c2.sql` with EXACTLY:

```sql
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
```

- [ ] **Step 2: Update `database/schema.sql`**

Two edits in `database/schema.sql`:

1. **Append the table definition** after the existing `medical_certificates` CREATE TABLE block (which is the last domain table, just before `-- SEED DATA`). Use the same content as the migration above, but **without** `IF NOT EXISTS` — schema.sql is the from-scratch source of truth.

2. **Add `DROP TABLE IF EXISTS cancel_broadcasts;`** to the DROP block at the top (around lines 5–22), BEFORE the lines that drop `appointments`, `users`, and `notifications`. Position it near the other broadcast/notification-related drops. A safe placement is at the very top of the DROP block:

```sql
DROP TABLE IF EXISTS cancel_broadcasts;
```

- [ ] **Step 3: Run the migration locally**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
# Read DB credentials
cat env.php
# Apply migration (substitute values from env.php; or use root with no password against the local 'meditrack' DB)
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < database/migrations/2026-05-06-batch-c2.sql
```

Expected: zero errors, exit code 0.

- [ ] **Step 4: Verify schema**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "DESC cancel_broadcasts; SHOW INDEX FROM cancel_broadcasts;"
```

Expected:
- Columns: `id`, `cancelled_appointment_id`, `recipient_user_id`, `notification_id`, `sent_at`.
- Indexes: `PRIMARY`, `uniq_broadcast_recipient` (UNIQUE on the two id columns), `idx_recipient`, plus implicit indexes from FKs.

- [ ] **Step 5: Idempotency check**

Re-run the migration command. Expected: still zero errors. The `CREATE TABLE IF NOT EXISTS` skips the existing table.

- [ ] **Step 6: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add database/migrations/2026-05-06-batch-c2.sql database/schema.sql
git commit -m "feat(db): add cancel_broadcasts table for slot-broadcast dedupe (Batch C2)"
```

---

## Task 2: Configuration Constant

**Files:**
- Modify: `config/config.php`

- [ ] **Step 1: Add the constant**

Open `config/config.php`. Find the existing block of `define(...)` calls (likely near the top — there's already `ITEMS_PER_PAGE`, etc.). Add this line near them:

```php
define('CANCEL_BROADCAST_LIMIT', 20);
```

The exact location doesn't matter, but place it next to the other domain constants (e.g., right after `ITEMS_PER_PAGE`) for discoverability.

- [ ] **Step 2: Verify**

```bash
grep -n "CANCEL_BROADCAST_LIMIT" /Applications/XAMPP/xamppfiles/htdocs/meditrack/config/config.php
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/config/config.php
```

Expected: one match showing the new define; `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add config/config.php
git commit -m "feat(config): add CANCEL_BROADCAST_LIMIT (20)"
```

---

## Task 3: `CancelBroadcaster` Utility

**Files:**
- Create: `utils/CancelBroadcaster.php`

This class encapsulates the recipient-set query, the rate-limit check, the audit-row insert, and the notification fan-out. It's invoked from `cancel-appointment.php` (Task 4).

- [ ] **Step 1: Implement the class**

Create `utils/CancelBroadcaster.php` with EXACTLY this content:

```php
<?php
/**
 * CancelBroadcaster — fans out a `slot_available` in-app notification to
 * patients who could plausibly take a freed appointment slot.
 *
 * Recipient set rules (see Batch C2 spec):
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
    /**
     * Broadcast that the slot freed by a cancelled appointment is available.
     *
     * @param PDO   $db
     * @param int   $cancelledAppointmentId
     * @param array $appt  Must contain: doctor_id, appointment_date, appointment_time,
     *                     appointment_number, cancelling_patient_id, cancelling_user_id
     * @return int  Number of recipients newly notified.
     */
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
            $link = sprintf(
                'qr-booking.html?date=%s&doctor=%d&time=%s',
                rawurlencode($appt['appointment_date']),
                (int) $appt['doctor_id'],
                rawurlencode($appt['appointment_time'])
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
                // Reserve the audit row first (INSERT IGNORE on the unique key).
                $insertBroadcast->execute([':cid' => $cancelledAppointmentId, ':uid' => $userId]);
                if ($insertBroadcast->rowCount() === 0) {
                    // Already notified for this cancellation — skip silently.
                    continue;
                }

                // Send the in-app notification, then back-link the audit row to it.
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
```

- [ ] **Step 2: Lint**

```bash
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/utils/CancelBroadcaster.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Quick sanity check via PHP CLI**

(This requires a running local DB and PHP CLI access. Optional but recommended.)

```bash
php -r '
require_once "/Applications/XAMPP/xamppfiles/htdocs/meditrack/config/config.php";
require_once "/Applications/XAMPP/xamppfiles/htdocs/meditrack/config/database.php";
require_once "/Applications/XAMPP/xamppfiles/htdocs/meditrack/utils/CancelBroadcaster.php";
$db = (new Database())->getConnection();
// Dry run with a synthetic appointment id of 999999 (no real row); no recipients should
// satisfy the join, but the function should return 0 cleanly without throwing.
$n = CancelBroadcaster::broadcast($db, 999999, [
    "doctor_id" => 1,
    "appointment_date" => "2099-01-01",
    "appointment_time" => "10:00",
    "appointment_number" => "TEST",
    "cancelling_patient_id" => 0,
    "cancelling_user_id" => 0,
]);
echo "Returned: $n\n";
'
```

Expected: prints `Returned: 0` with no errors.

- [ ] **Step 4: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add utils/CancelBroadcaster.php
git commit -m "feat(util): CancelBroadcaster — same-day slot broadcast with dedupe + rate limit"
```

---

## Task 4: Wire Broadcaster into Patient Cancellation

**Files:**
- Modify: `api/patient/cancel-appointment.php`

The existing endpoint already cancels the appointment, writes the cancelling patient's own notification, and emails the patient. We append a broadcast call after that.

- [ ] **Step 1: Read the current state**

Open `api/patient/cancel-appointment.php`. Note the structure:
- Pulls `$patient` and `$appt` from the DB.
- Updates the appointment to `cancelled`.
- Calls `Notifier::notify(...)` for the cancelling patient.
- Optionally emails the cancelling patient.
- Calls `sendJSON(['success' => true, ...])`.

We need access to the `doctor_id` for the broadcast deep link, but the current SELECT doesn't pull it. Modify the SELECT first.

- [ ] **Step 2: Pull `doctor_id` in the appointment SELECT**

Find the SELECT around line 34–43 that loads `$appt`:

```php
$stmt = $db->prepare("
    SELECT a.id, a.status, a.appointment_number, a.appointment_date, a.appointment_time,
           u.email AS patient_email
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE a.id = :aid AND a.patient_id = :pid
    LIMIT 1
");
```

Add `a.doctor_id` to the SELECT list:

```php
$stmt = $db->prepare("
    SELECT a.id, a.status, a.appointment_number, a.appointment_date, a.appointment_time,
           a.doctor_id,
           u.email AS patient_email
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    WHERE a.id = :aid AND a.patient_id = :pid
    LIMIT 1
");
```

- [ ] **Step 3: Add the require + broadcast call**

Near the top of the file, alongside the other `require_once` lines, add:

```php
require_once __DIR__ . '/../../utils/CancelBroadcaster.php';
```

Then, after the existing `Notifier::notify(...)` call for the cancelling patient (around line 72–77) and before the email try/catch block, insert the broadcast call:

```php
    // Broadcast the freed slot to other eligible patients (best-effort; never blocks the cancel).
    try {
        CancelBroadcaster::broadcast($db, $appointment_id, [
            'doctor_id'             => (int) $appt['doctor_id'],
            'appointment_date'      => $appt['appointment_date'],
            'appointment_time'      => $appt['appointment_time'],
            'appointment_number'    => $appt['appointment_number'],
            'cancelling_patient_id' => (int) $patient['id'],
            'cancelling_user_id'    => (int) $userId,
        ]);
    } catch (Exception $e) {
        error_log("cancel-appointment broadcast error: " . $e->getMessage());
    }
```

The `try/catch` here is defensive: `CancelBroadcaster::broadcast` already swallows its own errors, but wrapping protects the patient's cancellation flow against any unexpected throw.

- [ ] **Step 4: Lint**

```bash
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/patient/cancel-appointment.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Manual verification**

This requires Apache running and at least three active patient accounts. Set up:

```sql
-- In MySQL, confirm test setup:
SELECT id, full_name FROM patients ORDER BY id LIMIT 5;
```

Pick three patients: one will be the canceller (Patient A), one will already have an appointment that day (Patient B, ineligible), one is unbooked for that day (Patient C, eligible).

1. Log in as Patient A and book an appointment for tomorrow at 10:00 (or any future date).
2. In another browser/incognito, log in as Patient B and book an appointment for the same date as Patient A's, at any time.
3. Patient C remains unbooked for that date.
4. Log in as Patient A again, cancel their appointment.
5. **Expected DB state:**
   ```sql
   SELECT * FROM cancel_broadcasts WHERE cancelled_appointment_id = <A's id>;
   ```
   - Patient C's `user_id` is present.
   - Patient B's `user_id` is NOT present (already booked that day).
   - Patient A's own `user_id` is NOT present (excluded as the canceller).
   - `notification_id` is non-NULL on each row.
6. **Expected notification row:**
   ```sql
   SELECT id, type, title, link FROM notifications WHERE user_id = <C's id> AND type = 'slot_available' ORDER BY id DESC LIMIT 1;
   ```
   - `type` = `slot_available`.
   - `title` like "Open slot — 2026-05-07 10:00:00".
   - `link` like `qr-booking.html?date=2026-05-07&doctor=1&time=10%3A00%3A00`.
7. **Idempotency:** re-cancel by calling the endpoint again (it'll fail because the appointment is already `cancelled`, but if you forge a duplicate via DB or by cancelling another appointment of the same patient on the same day, the unique key on `cancel_broadcasts` ensures Patient C is not double-notified).
8. **Rate limit:** create 3 unread `slot_available` notifications for Patient C (manually or by repeated cancels), then cancel another appointment. Patient C should NOT be notified for this 4th cancellation — verify by checking the notification row count is unchanged.

- [ ] **Step 6: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add api/patient/cancel-appointment.php
git commit -m "feat(patient): broadcast freed slot to eligible patients on cancel"
```

---

## Task 5: Booking Page Deep-Link

**Files:**
- Modify: `pages/qr-booking.html`

The notification link looks like `qr-booking.html?date=YYYY-MM-DD&doctor=N&time=HH:MM:SS`. The booking page must read these params, pre-select the date and time, and (if the slot is no longer available) display a "slot taken" notice.

- [ ] **Step 1: Inspect the current booking flow**

Read `pages/qr-booking.html`. Identify:
- The date `<input type="date">` element id (e.g., `appt-date`).
- The time slot picker (likely populated via a `loadSlots()` call after the date is selected).
- The submit handler.
- Whether the page already has any URL-param parsing (e.g., for a doctor pre-selection).

(If the file structure is materially different from what this plan assumes — for example, if the booking flow is a multi-step wizard — adapt the deep-link wiring to the actual structure; the goal is "land on date+time pre-selected.")

- [ ] **Step 2: Add the URL-param reader**

Inside the page's main `<script>` block, near the top (before any DOMContentLoaded handler that fetches doctor/slot data), add:

```javascript
const __deepLink = (function () {
    const p = new URLSearchParams(location.search);
    return {
        date:    p.get('date')   || null,
        doctor:  p.get('doctor') || null,
        time:    p.get('time')   || null,
    };
})();
```

- [ ] **Step 3: Pre-fill the date input**

Find the existing logic that initializes the date input (e.g., `dateInput.min = ...`, default = today). Just after it, add:

```javascript
if (__deepLink.date) {
    const dateInput = document.getElementById('appt-date'); // adjust id to match the actual input
    if (dateInput) {
        dateInput.value = __deepLink.date;
        // Trigger change event so any slot loader runs
        dateInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
```

If the date input has a different id, replace `appt-date` with the actual id from the file.

- [ ] **Step 4: Pre-select the time slot after slots load**

Find the function that populates the time slot picker after a date is chosen (likely something like `loadAvailableSlots(date)` that fetches from `api/patient/get-available-slots.php` and renders option buttons or a `<select>`). At the end of that function, after slots are rendered, add:

```javascript
if (__deepLink.time) {
    // Normalize 'HH:MM:SS' to 'HH:MM' if needed; the slot identifiers may use either.
    const normalized = __deepLink.time.length >= 5 ? __deepLink.time.substring(0, 5) : __deepLink.time;
    const slotEl = document.querySelector(`[data-slot-time="${normalized}"]`)
                || document.querySelector(`[data-slot-time="${__deepLink.time}"]`);
    if (slotEl) {
        slotEl.click(); // or the equivalent "select this slot" call
        // Clear so we don't re-trigger on subsequent date changes
        __deepLink.time = null;
    } else {
        // Slot not available — show a notice
        const notice = document.getElementById('slot-taken-notice');
        if (notice) {
            notice.classList.remove('hidden');
            notice.textContent = 'That slot was just taken — pick another one below.';
        } else {
            console.warn('Pre-selected slot ' + __deepLink.time + ' not available — pick another.');
        }
        __deepLink.time = null;
    }
}
```

If the slot picker uses different selectors (e.g., a `<select>` with `<option value="HH:MM">`), adapt: e.g., `document.getElementById('time-select').value = normalized`.

- [ ] **Step 5: Add the notice element**

Near the slot picker in the HTML, add a hidden notice element the script above can show:

```html
<div id="slot-taken-notice" class="hidden mt-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs"></div>
```

(Adjust class names to match the booking page's styling conventions if they differ from Tailwind utilities.)

- [ ] **Step 6: Manual verification**

1. Browse to `http://localhost/meditrack/pages/qr-booking.html` (after logging in as a patient).
2. Confirm the page loads normally with no date pre-filled.
3. Browse to `http://localhost/meditrack/pages/qr-booking.html?date=YYYY-MM-DD&doctor=1&time=10:00` where the date is a future date and 10:00 is an available slot.
4. Expected: the date input is pre-filled, the slot picker loads, and 10:00 is highlighted as the chosen slot. The submit button should be enabled (or whatever the existing UX requires for a complete booking).
5. Browse to the same URL but with a `time=` value that is NOT in the slot list (e.g., `time=03:00`). Expected: the "slot taken" notice appears with the message "That slot was just taken — pick another one below."
6. Complete the booking normally; confirm a new appointment row is created.

- [ ] **Step 7: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add pages/qr-booking.html
git commit -m "feat(booking): deep-link query params (date, doctor, time) for slot reschedule"
```

---

## End-to-End Verification

After completing all 5 tasks, walk through the full slot-recapture flow:

1. **Three patient accounts:** Alice (will book and cancel), Bob (will already be booked that day, ineligible), Carol (unbooked, eligible).
2. Log in as Alice, book a 10:00 appointment for tomorrow.
3. Log in as Bob, book any time on the same day.
4. Log in as Carol — confirm her notifications inbox is empty (or note the count).
5. Log in as Alice, cancel her 10:00 appointment.
6. Log back in as Carol — bell badge should show one new unread `slot_available` notification. Title: "Open slot — {tomorrow} 10:00:00". Click it — booking page opens with date and 10:00 slot pre-selected.
7. Confirm the booking — Carol's appointment for tomorrow at 10:00 is created.
8. Log back in as Bob — bell badge unchanged (he was excluded).
9. Log into MySQL and confirm:
   ```sql
   SELECT cancelled_appointment_id, recipient_user_id, notification_id FROM cancel_broadcasts;
   SELECT user_id, type, is_read FROM notifications WHERE type = 'slot_available' ORDER BY id DESC;
   ```
   - Carol's user_id appears in `cancel_broadcasts` for Alice's cancelled appointment.
   - The notification row count matches.

If all eight steps pass, C2 is complete.

---

## Self-Review Checklist (against spec)

- [x] **Spec Feature 1 (broadcast on patient cancellation):** Tasks 1 (table), 3 (broadcaster), 4 (wiring).
- [x] **Recipient set rules** (active patient, exclude canceller, exclude same-day-already-booked, capped 20, ordered by last_login): Task 3 SQL.
- [x] **Rate limit** (3 unread slot_available in 24h): Task 3 SQL inline subquery.
- [x] **Email NOT sent for slot_available:** confirmed — `CancelBroadcaster::broadcast` only calls `Notifier::notify` (in-app only); no `Mailer` invocation.
- [x] **Idempotency** (one row per (cancelled_appointment_id, recipient_user_id)): Task 1 unique key + Task 3 INSERT IGNORE.
- [x] **Spec Feature 2 (booking page deep-link):** Task 5.
- [x] **Slot-taken notice when pre-selected slot is unavailable:** Task 5 step 4 + step 5.
- [x] **Spec Feature 3 (rate-limit hygiene):** Task 3 SQL `< 3` clause.
- [x] **Doctor day-cancel does NOT trigger broadcast:** confirmed — wiring is only in `api/patient/cancel-appointment.php`, not `api/doctor/cancel-day.php`.
- [x] **Manual verification per task:** present in Tasks 1, 3, 4, 5.

## Open Questions

None.
