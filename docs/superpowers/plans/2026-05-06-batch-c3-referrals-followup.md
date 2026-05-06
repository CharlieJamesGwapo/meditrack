# Batch C3 — Specialist Referrals & Follow-Up Auto-Booking (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add doctor-side flows to refer a patient to an external specialist (with a printable referral letter) and to schedule a follow-up appointment that lands directly on the patient's calendar with a fresh QR token.

**Architecture:** Two purely-additive feature blocks on the doctor's medical-record drawer. Referrals get a new `referrals` table (one per appointment), three thin endpoints, and a print-only HTML page mirroring the C1 cert print page. Follow-ups extend `appointments` with `parent_appointment_id` and `is_followup`, reuse the existing booking transaction pattern (slot lock, appointment-number generation, QR token), and surface to the patient through their existing dashboard.

**Tech Stack:** PHP 8 + PDO + MySQL/MariaDB (XAMPP), vanilla JS frontend, existing `QRCodeGenerator` and `Notifier` utilities. No build step. No automated tests — verification is manual XAMPP per project convention.

**Spec:** `docs/superpowers/specs/2026-05-06-batch-c3-referrals-followup-design.md`

**Branch:** Create `feature/batch-c3-referrals-followup` from `master` (or your post-C1/C2 merge point) before starting.

---

## File Structure

**New files:**

| Path | Responsibility |
|---|---|
| `database/migrations/2026-05-06-batch-c3.sql` | Idempotent migration: `referrals` table + `appointments` parent/followup columns |
| `api/doctor/save-referral.php` | Upsert a referral keyed on appointment_id |
| `api/doctor/get-referral.php` | Read referral for prefill on the doctor's screen |
| `api/staff/referral.php` | Shared read endpoint for printing (doctor / staff / admin / owning patient) |
| `api/doctor/schedule-followup.php` | Creates or updates the active follow-up appointment for a parent |
| `api/doctor/get-followup.php` | Returns the active follow-up if any |
| `pages/print-referral.html` | Print-only letterhead page; reads `?appointment_id=N` |

**Modified files:**

| Path | Change |
|---|---|
| `database/schema.sql` | Append `referrals` CREATE TABLE; add to DROP block; alter `appointments` shape in the from-scratch source-of-truth |
| `pages/doctor-dashboard.html` | Replace the bare `follow_up_date` input with a richer "Schedule follow-up" card; add a "Refer to specialist" card |
| `js/doctor-dashboard.js` | Wire the two new cards: load existing referral/follow-up on drawer open; save handlers; print referral button |
| `pages/patient-dashboard.html` | Render a "Follow-up" pill on appointments where `is_followup=1`; add "Print referral" button on past appointments where a referral exists |

---

## Conventions

- **No automated tests.** Each task ends with manual verification in XAMPP.
- **Commit after each task.** Use exact paths in `git add` (no `git add -A` / `.`).
- **Auth helpers:** `isLoggedIn()`, `hasRole($role)`, `getCurrentUserId()`, `getCurrentUserRole()` from `config/config.php`.
- **JSON response:** `sendJSON([...], $status)` from `config/config.php`.
- **Sanitize:** `sanitizeInput($v)`.
- **DB:** `(new Database())->getConnection()` — PDO in exception mode.
- **QR generation:** `(new QRCodeGenerator($db))->generateQRCode($appointment_id)` — used by `api/patient/book-appointment.php`. Same call works for follow-up appointments.
- **Notifications:** `Notifier::notify($db, $user_id, $type, $title, $message, $link)`.
- **Email:** `(new Mailer())->sendAppointmentConfirmation($email, $name, $apptNum, $date, $time, $doctorName)`.

---

## Task 1: Database Migration

**Files:**
- Create: `database/migrations/2026-05-06-batch-c3.sql`
- Modify: `database/schema.sql`

- [ ] **Step 1: Create the migration file**

Create `database/migrations/2026-05-06-batch-c3.sql` with EXACTLY this content:

```sql
-- database/migrations/2026-05-06-batch-c3.sql
-- Batch C3 — specialist referrals + follow-up auto-booking.
-- Idempotent: re-running on a partially-migrated DB should not error.

-- 1. Referrals table
CREATE TABLE IF NOT EXISTS referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  referring_doctor_id INT NOT NULL,
  specialty VARCHAR(100) NOT NULL,
  specialty_other VARCHAR(100) NULL,
  suggested_specialist VARCHAR(150) NULL,
  reason TEXT NOT NULL,
  urgency ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_referral_appointment (appointment_id),
  INDEX idx_referral_patient (patient_id),
  INDEX idx_referral_doctor (referring_doctor_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (referring_doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. appointments: parent_appointment_id + is_followup
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'parent_appointment_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE appointments ADD COLUMN parent_appointment_id INT NULL AFTER doctor_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'is_followup'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE appointments ADD COLUMN is_followup TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_appointment_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND index_name = 'idx_parent_appointment'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE appointments ADD INDEX idx_parent_appointment (parent_appointment_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND constraint_name = 'fk_parent_appointment'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE appointments ADD CONSTRAINT fk_parent_appointment FOREIGN KEY (parent_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
```

- [ ] **Step 2: Update `database/schema.sql`**

Three edits:

1. **Add to DROP block** (top of file, around lines 5–22): insert `DROP TABLE IF EXISTS referrals;` near the other domain-table drops, BEFORE `DROP TABLE IF EXISTS appointments;` (because `referrals` has an FK to `appointments`).

2. **Update the `appointments` CREATE TABLE block** (around line 86): add two columns and one FK to the table definition. Specifically, after the `doctor_id INT NOT NULL,` line, insert:

   ```sql
       parent_appointment_id INT NULL,
       is_followup TINYINT(1) NOT NULL DEFAULT 0,
   ```

   And before the closing `) ENGINE=...` line, immediately after the existing `FOREIGN KEY (doctor_id) ...` line, append:

   ```sql
       FOREIGN KEY (parent_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
       INDEX idx_parent_appointment (parent_appointment_id),
   ```

3. **Append the `referrals` table** after the existing `medical_certificates` (or `cancel_broadcasts` if Batch C2 has been merged in) block, before `-- SEED DATA`. Use the migration's `referrals` CREATE TABLE (without `IF NOT EXISTS`).

- [ ] **Step 3: Run the migration locally**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < database/migrations/2026-05-06-batch-c3.sql
```

Expected: zero errors. (Adjust user/password from `env.php` if root-no-password doesn't work locally.)

- [ ] **Step 4: Verify schema**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "
  DESC referrals;
  SHOW COLUMNS FROM appointments LIKE 'parent_appointment_id';
  SHOW COLUMNS FROM appointments LIKE 'is_followup';
  SHOW INDEX FROM appointments WHERE Key_name = 'idx_parent_appointment';
"
```

Expected: `referrals` has all columns from Step 1; `appointments` has the two new columns and the index.

- [ ] **Step 5: Idempotency check**

Re-run the migration command. Expected: still zero errors.

- [ ] **Step 6: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add database/migrations/2026-05-06-batch-c3.sql database/schema.sql
git commit -m "feat(db): add referrals table + appointments parent/followup columns (Batch C3)"
```

---

## Task 2: Referral Endpoints (save / get / shared read)

**Files:**
- Create: `api/doctor/save-referral.php`
- Create: `api/doctor/get-referral.php`
- Create: `api/staff/referral.php`

- [ ] **Step 1: Implement `save-referral.php`**

Create `api/doctor/save-referral.php` with EXACTLY:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$ALLOWED_SPECIALTIES = ['ENT','Cardiology','OB-GYN','Pediatrics','General Surgery','Dermatology','Orthopedics','Ophthalmology','Neurology','Other'];
$ALLOWED_URGENCY     = ['routine','urgent','emergency'];

$input = json_decode(file_get_contents('php://input'), true);

$appointment_id        = (int) ($input['appointment_id'] ?? 0);
$specialty             = sanitizeInput($input['specialty'] ?? '');
$specialty_other       = sanitizeInput($input['specialty_other'] ?? '');
$suggested_specialist  = sanitizeInput($input['suggested_specialist'] ?? '');
$reason                = sanitizeInput($input['reason'] ?? '');
$urgency               = sanitizeInput($input['urgency'] ?? 'routine');

if (!$appointment_id || empty($specialty) || empty($reason)) {
    sendJSON(['success' => false, 'message' => 'appointment_id, specialty, and reason are required'], 400);
}
if (!in_array($specialty, $ALLOWED_SPECIALTIES, true)) {
    sendJSON(['success' => false, 'message' => 'Unsupported specialty'], 400);
}
if ($specialty === 'Other' && empty($specialty_other)) {
    sendJSON(['success' => false, 'message' => 'Please describe the specialty when "Other" is selected'], 400);
}
if (!in_array($urgency, $ALLOWED_URGENCY, true)) {
    sendJSON(['success' => false, 'message' => 'Invalid urgency'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt || (int) $appt['doctor_id'] !== $doctor_id) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or not assigned to you'], 404);
    }
    if (!in_array($appt['status'], ['in_progress','completed'], true)) {
        sendJSON(['success' => false, 'message' => 'Referral can only be issued during or after the consultation'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO referrals
          (appointment_id, patient_id, referring_doctor_id, specialty, specialty_other, suggested_specialist, reason, urgency)
        VALUES
          (:aid, :pid, :did, :spec, :other, :sugg, :reason, :urg)
        ON DUPLICATE KEY UPDATE
          specialty            = VALUES(specialty),
          specialty_other      = VALUES(specialty_other),
          suggested_specialist = VALUES(suggested_specialist),
          reason               = VALUES(reason),
          urgency              = VALUES(urgency)
    ");
    $stmt->execute([
        ':aid'    => $appointment_id,
        ':pid'    => $appt['patient_id'],
        ':did'    => $doctor_id,
        ':spec'   => $specialty,
        ':other'  => $specialty === 'Other' ? $specialty_other : null,
        ':sugg'   => $suggested_specialist ?: null,
        ':reason' => $reason,
        ':urg'    => $urgency,
    ]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', 'CREATE', 'Referrals', $appointment_id, "Referral issued ($specialty) for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Referral saved']);
} catch (Exception $e) {
    error_log("doctor/save-referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to save referral'], 500);
}
```

- [ ] **Step 2: Implement `get-referral.php`**

Create `api/doctor/get-referral.php` with EXACTLY:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("
        SELECT r.* FROM referrals r
          JOIN appointments a ON a.id = r.appointment_id
         WHERE r.appointment_id = :aid
           AND a.doctor_id = :did
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id, ':did' => $doctor_id]);
    sendJSON(['success' => true, 'referral' => $stmt->fetch() ?: null]);
} catch (Exception $e) {
    error_log("doctor/get-referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load referral'], 500);
}
```

- [ ] **Step 3: Implement `staff/referral.php`** (shared read for printing)

Create `api/staff/referral.php` with EXACTLY:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = getCurrentUserRole();

    // Fetch the referral with joined patient + doctor + appointment
    $stmt = $db->prepare("
        SELECT r.*,
               a.appointment_number, a.appointment_date,
               p.user_id AS patient_user_id,
               p.full_name AS patient_name, p.date_of_birth, p.gender, p.address, p.contact_number AS patient_contact,
               d.user_id AS doctor_user_id,
               d.full_name AS doctor_name, d.license_number, d.specialization
          FROM referrals r
          JOIN appointments a ON a.id = r.appointment_id
          JOIN patients p ON p.id = r.patient_id
          JOIN doctors d ON d.id = r.referring_doctor_id
         WHERE r.appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        sendJSON(['success' => false, 'message' => 'Referral not found'], 404);
    }

    // Authorization: staff/admin OK; doctor OK if owner; patient OK if their appointment.
    $authorized = in_array($role, ['staff','admin'], true)
               || ($role === 'doctor'  && (int) $row['doctor_user_id']  === (int) $userId)
               || ($role === 'patient' && (int) $row['patient_user_id'] === (int) $userId);
    if (!$authorized) {
        sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    // Strip the user_id columns from the response — they were only needed for auth.
    unset($row['patient_user_id'], $row['doctor_user_id']);

    sendJSON(['success' => true, 'referral' => $row]);
} catch (Exception $e) {
    error_log("staff/referral error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load referral'], 500);
}
```

- [ ] **Step 4: Lint**

```bash
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/doctor/save-referral.php
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/doctor/get-referral.php
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/staff/referral.php
```

Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Manual verification**

1. Set up: pick an appointment with status `in_progress` or `completed` for a known doctor user. Note its id and the doctor's session cookie.
2. POST a referral (substituting your `PHPSESSID` and `<APPT_ID>`):
   ```bash
   curl -X POST -H "Content-Type: application/json" -b "PHPSESSID=<doctor>" \
     -d '{"appointment_id":<APPT_ID>,"specialty":"ENT","reason":"Persistent ear pain","urgency":"routine"}' \
     http://localhost/meditrack/api/doctor/save-referral.php
   ```
   Expected: `{"success":true,...}`. In MySQL: `SELECT * FROM referrals WHERE appointment_id=<APPT_ID>;` returns one row.
3. Re-POST with `"specialty":"Cardiology"` — expected: still `success:true`, the existing row is updated (no duplicate).
4. POST with `"specialty":"Other"` and no `specialty_other` → expected: 400.
5. POST with `"specialty":"Made-Up"` → expected: 400.
6. POST against an appointment with `status='scheduled'` → expected: 400 ("Referral can only be issued during or after the consultation").
7. GET `api/doctor/get-referral.php?appointment_id=<APPT_ID>` → expected: returns the row.
8. GET `api/staff/referral.php?appointment_id=<APPT_ID>` as a staff user → expected: returns the row with joined patient/doctor data.
9. GET `api/staff/referral.php?appointment_id=<APPT_ID>` as the owning patient → expected: returns the row.
10. GET as a different patient → expected: 401.

- [ ] **Step 6: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add api/doctor/save-referral.php api/doctor/get-referral.php api/staff/referral.php
git commit -m "feat(referrals): doctor save/get + shared print-read endpoints"
```

---

## Task 3: Print Referral Page

**Files:**
- Create: `pages/print-referral.html`

- [ ] **Step 1: Build the print page**

Create `pages/print-referral.html` with EXACTLY:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Referral Letter</title>
  <link rel="icon" type="image/png" href="../assets/images/medicare.png">
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: 'Times New Roman', serif; color:#111; max-width: 720px; margin:0 auto; padding:24px; }
    .letterhead { display:flex; align-items:center; gap:16px; border-bottom:2px solid #0f172a; padding-bottom:12px; }
    .letterhead img { width:64px; height:64px; }
    .letterhead h1 { margin:0; font-size:20px; }
    .letterhead p { margin:2px 0; font-size:12px; color:#475569; }
    h2.title { text-align:center; letter-spacing:2px; margin:32px 0 16px; }
    .recipient { margin: 0 0 24px; font-size: 14px; }
    .recipient strong { display:block; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px; }
    .body p { line-height:1.7; margin:8px 0; text-align:justify; }
    .urgency { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:.05em; }
    .urgency.routine   { background:#ecfdf5; color:#065f46; }
    .urgency.urgent    { background:#fef3c7; color:#92400e; }
    .urgency.emergency { background:#fee2e2; color:#991b1b; }
    .signatures { margin-top:64px; }
    .sig { display:inline-block; width: 280px; text-align:center; }
    .sig-line { border-top:1px solid #111; margin-top:48px; padding-top:4px; font-size:13px; font-weight:bold; }
    .sig-role { font-size:11px; color:#64748b; }
    .meta { margin-top:24px; font-size:11px; color:#64748b; }
    .actions { text-align:center; margin:24px 0; }
    .actions button { padding:8px 16px; border:1px solid #0f172a; background:#0f172a; color:#fff; border-radius:6px; cursor:pointer; font-family: inherit; }
    .actions button.secondary { background:#fff; color:#0f172a; margin-left:8px; }
    @media print { .actions { display:none; } body { padding:0; } }
  </style>
</head>
<body>
  <div class="actions">
    <button onclick="window.print()">Print</button>
    <button class="secondary" onclick="history.back()">Back</button>
  </div>

  <div class="letterhead">
    <img src="../assets/images/medicare.png" alt="Clinic logo" onerror="this.style.display='none'">
    <div>
      <h1>Internal Medicine OPD</h1>
      <p>Consultation Clinic &middot; Referral Letter</p>
    </div>
  </div>

  <h2 class="title">REFERRAL LETTER</h2>

  <div id="ref-body">Loading…</div>
  <p class="meta" id="ref-meta"></p>

  <script src="../js/auth.js?v=3.0"></script>
  <script>
    function escHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function ageFromDob(dob) {
      if (!dob) return '';
      const d = new Date(dob), now = new Date();
      let a = now.getFullYear() - d.getFullYear();
      if (now.getMonth() < d.getMonth() || (now.getMonth() === d.getMonth() && now.getDate() < d.getDate())) a--;
      return a;
    }
    function fmtDate(s) {
      if (!s) return '';
      try { return new Date(s).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
      catch (_) { return s; }
    }

    (async function () {
      const params = new URLSearchParams(location.search);
      const aid = params.get('appointment_id');
      const out = document.getElementById('ref-body');
      if (!aid) { out.textContent = 'Missing appointment_id.'; return; }
      try {
        const data = await apiRequest('/staff/referral.php?appointment_id=' + encodeURIComponent(aid));
        if (!data || !data.success) {
          out.innerHTML = `<p style="color:#991b1b">${escHtml(data?.message || 'Referral not found.')}</p>`;
          return;
        }
        const r = data.referral;
        const specialtyLabel = r.specialty === 'Other' && r.specialty_other ? r.specialty_other : r.specialty;
        const age = ageFromDob(r.date_of_birth);
        out.innerHTML = `
          <div class="recipient">
            <strong>To</strong>
            ${r.suggested_specialist ? escHtml(r.suggested_specialist) : `Attending ${escHtml(specialtyLabel)} Specialist`}
          </div>

          <div class="body">
            <p>Dear Colleague,</p>
            <p>I am referring my patient, <strong>${escHtml(r.patient_name)}</strong>${age ? `, ${age} years old` : ''}${r.gender ? `, ${escHtml(r.gender)}` : ''}, for further evaluation and management in your specialty (<strong>${escHtml(specialtyLabel)}</strong>).</p>
            <p><strong>Reason for referral:</strong> ${escHtml(r.reason)}</p>
            <p><strong>Urgency:</strong> <span class="urgency ${escHtml(r.urgency)}">${escHtml(r.urgency)}</span></p>
            <p>This patient was seen on ${fmtDate(r.appointment_date)} at our clinic (Appointment #${escHtml(r.appointment_number)}). Kindly find the patient's contact information below and arrange the consultation accordingly.</p>
            <p><strong>Contact:</strong> ${escHtml(r.patient_contact || '—')}</p>
            <p>Thank you for your kind attention.</p>
          </div>

          <div class="signatures">
            <div class="sig">
              <div class="sig-line">${escHtml(r.doctor_name)}, M.D.</div>
              <div class="sig-role">Internal Medicine · License: ${escHtml(r.license_number || '—')}</div>
            </div>
          </div>
        `;
        document.getElementById('ref-meta').textContent =
          `Referral #${r.id} · Issued ${new Date(r.issued_at).toLocaleString()}`;
      } catch (e) {
        out.textContent = 'Failed to load referral: ' + e.message;
      }
    })();
  </script>
</body>
</html>
```

- [ ] **Step 2: Manual verification**

In a browser logged in as the doctor (after Task 2 wrote a referral), visit:
`http://localhost/meditrack/pages/print-referral.html?appointment_id=<APPT_ID>`

Expected: letterhead, "REFERRAL LETTER" title, recipient line, body paragraphs, urgency pill, doctor signature block. The "Print" button opens the browser print dialog; the "Back" button navigates back. Print preview shows the letter on one A4 page.

Test a missing referral:
`http://localhost/meditrack/pages/print-referral.html?appointment_id=999999`
Expected: "Referral not found." in red.

Test as another role (open in incognito and log in as staff or admin or the owning patient): expected to render the letter (per `staff/referral.php` authorization).

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add pages/print-referral.html
git commit -m "feat(referrals): print-referral.html letterhead page"
```

---

## Task 4: Doctor UI — Refer to Specialist Card

**Files:**
- Modify: `pages/doctor-dashboard.html`
- Modify: `js/doctor-dashboard.js`

- [ ] **Step 1: Add the referral card markup**

Open `pages/doctor-dashboard.html`. Find the medical record drawer's form (search for `medical-record-form` or `rec-followup`). After the existing `Lab Tests + Notes` block (the `grid-cols-1 sm:grid-cols-2` block) and BEFORE the `Follow-up Date` block, insert:

```html
                    <!-- ─ Refer to specialist (optional) ─ -->
                    <div class="rounded-2xl border border-gray-100 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center space-x-2"
                             style="background:linear-gradient(135deg,#EFF6FF,#F8FBFF)">
                            <div class="w-6 h-6 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center">
                                <i class="fas fa-share-from-square text-blue-500 text-xs"></i>
                            </div>
                            <span class="text-xs font-bold text-[#083344] uppercase tracking-wide">Refer to Specialist</span>
                            <label class="ml-auto inline-flex items-center gap-2 text-xs">
                                <input type="checkbox" id="ref-enable" class="form-checkbox">
                                <span class="text-slate-600 normal-case">This visit needs a referral</span>
                            </label>
                        </div>
                        <div id="ref-body" class="hidden p-4 bg-white space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Specialty</span>
                                    <select id="ref-specialty" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                        <option value="">Choose a specialty…</option>
                                        <option>ENT</option>
                                        <option>Cardiology</option>
                                        <option>OB-GYN</option>
                                        <option>Pediatrics</option>
                                        <option>General Surgery</option>
                                        <option>Dermatology</option>
                                        <option>Orthopedics</option>
                                        <option>Ophthalmology</option>
                                        <option>Neurology</option>
                                        <option>Other</option>
                                    </select>
                                </label>
                                <label class="block" id="ref-specialty-other-wrap" style="display:none">
                                    <span class="text-xs font-semibold text-slate-600">Specify specialty</span>
                                    <input id="ref-specialty-other" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Suggested specialist (optional)</span>
                                    <input id="ref-suggested" placeholder="e.g., Dr. dela Cruz at St Luke's" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Urgency</span>
                                    <select id="ref-urgency" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                        <option value="routine" selected>Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </label>
                            </div>
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Reason for referral</span>
                                <textarea id="ref-reason" rows="2" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" placeholder="Justification, observed symptoms…"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" id="ref-print-btn" class="hidden px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">
                                    <i class="fa-solid fa-print mr-1"></i> Print referral
                                </button>
                                <button type="button" id="ref-save-btn" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                                    Save referral
                                </button>
                            </div>
                            <div id="ref-status" class="text-xs text-slate-400"></div>
                        </div>
                    </div>
```

- [ ] **Step 2: Wire the JS**

In `js/doctor-dashboard.js`, add this block at the end of the file (or just before the existing `function viewRecord(...)` definition — match the file's organization):

```javascript
// ─── Referrals (Batch C3) ────────────────────────────────────
function bindReferralCard() {
    const enable = document.getElementById('ref-enable');
    const body   = document.getElementById('ref-body');
    const spec   = document.getElementById('ref-specialty');
    const otherWrap = document.getElementById('ref-specialty-other-wrap');
    if (!enable || !body) return;

    enable.addEventListener('change', () => {
        body.classList.toggle('hidden', !enable.checked);
    });
    spec.addEventListener('change', () => {
        otherWrap.style.display = spec.value === 'Other' ? '' : 'none';
    });

    document.getElementById('ref-save-btn').addEventListener('click', saveReferral);
    document.getElementById('ref-print-btn').addEventListener('click', () => {
        if (!currentAppointment) return;
        window.open('print-referral.html?appointment_id=' + currentAppointment.id, '_blank');
    });
}

async function loadReferralForAppointment(appointment_id) {
    const status = document.getElementById('ref-status');
    const printBtn = document.getElementById('ref-print-btn');
    const enable = document.getElementById('ref-enable');
    const body   = document.getElementById('ref-body');
    const spec   = document.getElementById('ref-specialty');
    const otherWrap = document.getElementById('ref-specialty-other-wrap');
    // Reset
    enable.checked = false;
    body.classList.add('hidden');
    spec.value = '';
    otherWrap.style.display = 'none';
    document.getElementById('ref-specialty-other').value = '';
    document.getElementById('ref-suggested').value = '';
    document.getElementById('ref-urgency').value = 'routine';
    document.getElementById('ref-reason').value = '';
    status.textContent = '';
    printBtn.classList.add('hidden');

    try {
        const data = await apiRequest('/doctor/get-referral.php?appointment_id=' + appointment_id);
        if (data && data.success && data.referral) {
            const r = data.referral;
            enable.checked = true;
            body.classList.remove('hidden');
            spec.value = r.specialty || '';
            if (spec.value === 'Other') {
                otherWrap.style.display = '';
                document.getElementById('ref-specialty-other').value = r.specialty_other || '';
            }
            document.getElementById('ref-suggested').value = r.suggested_specialist || '';
            document.getElementById('ref-urgency').value   = r.urgency || 'routine';
            document.getElementById('ref-reason').value    = r.reason || '';
            printBtn.classList.remove('hidden');
            status.textContent = 'Referral on file — last updated ' + new Date(r.updated_at || r.issued_at).toLocaleString();
        }
    } catch (_) {}
}

async function saveReferral() {
    if (!currentAppointment) return;
    const status = document.getElementById('ref-status');
    const body = {
        appointment_id:       currentAppointment.id,
        specialty:            document.getElementById('ref-specialty').value,
        specialty_other:      document.getElementById('ref-specialty-other').value.trim(),
        suggested_specialist: document.getElementById('ref-suggested').value.trim(),
        urgency:              document.getElementById('ref-urgency').value,
        reason:               document.getElementById('ref-reason').value.trim(),
    };
    if (!body.specialty || !body.reason) {
        Swal.fire('Required', 'Specialty and reason are required.', 'warning');
        return;
    }
    if (body.specialty === 'Other' && !body.specialty_other) {
        Swal.fire('Required', 'Please describe the specialty.', 'warning');
        return;
    }
    status.textContent = 'Saving…';
    const data = await apiRequest('/doctor/save-referral.php', { method: 'POST', body: JSON.stringify(body) });
    if (!data || !data.success) {
        status.textContent = '';
        Swal.fire('Error', data?.message || 'Failed to save referral', 'error');
        return;
    }
    status.textContent = 'Saved ' + new Date().toLocaleString();
    document.getElementById('ref-print-btn').classList.remove('hidden');
}

// Bind once at page load
window.addEventListener('DOMContentLoaded', bindReferralCard);
```

- [ ] **Step 3: Trigger `loadReferralForAppointment` when the drawer opens**

Find `function openRecordModal(appointment) {` in `js/doctor-dashboard.js`. Just before the trailing `modal.classList.remove('hidden');` line, add:

```javascript
    if (appointment.id) loadReferralForAppointment(appointment.id);
```

- [ ] **Step 4: Manual verification**

1. Log in as doctor, open a `completed` (or `in_progress`) appointment's medical record drawer.
2. Expected: the new "Refer to Specialist" card is present with the toggle unchecked.
3. Toggle the checkbox → form reveals.
4. Fill specialty=ENT, reason=test, urgency=urgent → click "Save referral" → expected: status line updates, "Print referral" button appears.
5. Close and re-open the drawer for the same appointment → expected: the toggle is checked, fields pre-filled with previous values, "Print referral" visible.
6. Click "Print referral" → opens `print-referral.html` in a new tab with letterhead populated.
7. Change specialty to Other and fill the "Specify specialty" field (revealed conditionally) → save → re-open → confirm field round-trips.
8. Save without filling specialty → expected: SweetAlert "Required".

- [ ] **Step 5: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add pages/doctor-dashboard.html js/doctor-dashboard.js
git commit -m "feat(referrals): doctor refer-to-specialist card with print"
```

---

## Task 5: Follow-Up Endpoints

**Files:**
- Create: `api/doctor/schedule-followup.php`
- Create: `api/doctor/get-followup.php`

- [ ] **Step 1: Implement `schedule-followup.php`**

Create `api/doctor/schedule-followup.php` with EXACTLY:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';
require_once __DIR__ . '/../../utils/Mailer.php';
require_once __DIR__ . '/../../utils/Notifier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$parent_appointment_id = (int) ($input['parent_appointment_id'] ?? 0);
$appointment_date      = sanitizeInput($input['appointment_date'] ?? '');
$appointment_time      = sanitizeInput($input['appointment_time'] ?? '');
$reason_for_visit      = sanitizeInput($input['reason_for_visit'] ?? '');

if (!$parent_appointment_id || empty($appointment_date) || empty($appointment_time)) {
    sendJSON(['success' => false, 'message' => 'parent_appointment_id, appointment_date, appointment_time are required'], 400);
}
if ($appointment_date <= date('Y-m-d')) {
    sendJSON(['success' => false, 'message' => 'Follow-up date must be in the future'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    // Verify the doctor owns the parent appointment
    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, reason_for_visit FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $parent_appointment_id]);
    $parent = $stmt->fetch();
    if (!$parent || (int) $parent['doctor_id'] !== $doctor_id) {
        sendJSON(['success' => false, 'message' => 'Parent appointment not found or not assigned to you'], 404);
    }
    $patient_id = (int) $parent['patient_id'];
    if (empty($reason_for_visit)) {
        $reason_for_visit = 'Follow-up of ' . ($parent['reason_for_visit'] ?: 'previous consultation');
    }

    $db->beginTransaction();

    // Look for an ACTIVE existing follow-up to update; otherwise create new.
    $stmt = $db->prepare("
        SELECT id, status FROM appointments
         WHERE parent_appointment_id = :pid
           AND is_followup = 1
           AND status IN ('scheduled','checked_in','in_progress')
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_appointment_id]);
    $existing = $stmt->fetch();

    // Slot lock: ensure the chosen slot isn't taken by some OTHER appointment.
    $excludeId = $existing ? (int) $existing['id'] : 0;
    $stmt = $db->prepare("
        SELECT id FROM appointments
         WHERE doctor_id = :did
           AND appointment_date = :date
           AND appointment_time = :time
           AND status NOT IN ('cancelled','no_show')
           AND id != :excl
         LIMIT 1 FOR UPDATE
    ");
    $stmt->execute([':did' => $doctor_id, ':date' => $appointment_date, ':time' => $appointment_time, ':excl' => $excludeId]);
    if ($stmt->rowCount() > 0) {
        $db->rollBack();
        sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 409);
    }

    if ($existing) {
        // Update active follow-up in place
        $stmt = $db->prepare("
            UPDATE appointments
               SET appointment_date = :date,
                   appointment_time = :time,
                   reason_for_visit = :reason
             WHERE id = :aid
        ");
        $stmt->execute([
            ':date'   => $appointment_date,
            ':time'   => $appointment_time,
            ':reason' => $reason_for_visit,
            ':aid'    => $existing['id'],
        ]);
        $appointment_id = (int) $existing['id'];
        $appointment_number = null;
        $stmt = $db->prepare("SELECT appointment_number FROM appointments WHERE id = :aid");
        $stmt->execute([':aid' => $appointment_id]);
        $appointment_number = $stmt->fetchColumn();
        // Reissue QR token (delete existing and regenerate)
        $db->prepare("DELETE FROM qr_tokens WHERE appointment_id = :aid")->execute([':aid' => $appointment_id]);
        $mode = 'updated';
    } else {
        // Create new follow-up appointment
        $date_part = date('Ymd', strtotime($appointment_date));
        $stmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(appointment_number, -4) AS UNSIGNED)), 0) + 1 AS next_num FROM appointments WHERE appointment_date = :date FOR UPDATE");
        $stmt->execute([':date' => $appointment_date]);
        $cnt = (int) $stmt->fetch()['next_num'];
        $appointment_number = 'APT-' . $date_part . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO appointments
              (appointment_number, patient_id, doctor_id, parent_appointment_id, is_followup,
               appointment_date, appointment_time, reason_for_visit, status)
            VALUES
              (:num, :pid, :did, :parent, 1, :date, :time, :reason, 'scheduled')
        ");
        $stmt->execute([
            ':num'    => $appointment_number,
            ':pid'    => $patient_id,
            ':did'    => $doctor_id,
            ':parent' => $parent_appointment_id,
            ':date'   => $appointment_date,
            ':time'   => $appointment_time,
            ':reason' => $reason_for_visit,
        ]);
        $appointment_id = (int) $db->lastInsertId();
        $mode = 'created';
    }

    // (Re)generate QR token
    $qr = (new QRCodeGenerator($db))->generateQRCode($appointment_id);

    // Mirror onto medical_records.follow_up_date for backward compatibility
    $db->prepare("UPDATE medical_records SET follow_up_date = :d WHERE appointment_id = :pid")
       ->execute([':d' => $appointment_date, ':pid' => $parent_appointment_id]);

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'doctor', $mode === 'created' ? 'CREATE' : 'UPDATE', 'Followup', $appointment_id, "Follow-up $mode for parent #$parent_appointment_id on $appointment_date $appointment_time");

    // Notify patient (best-effort)
    try {
        $stmt = $db->prepare("SELECT u.id AS user_id, u.email, p.full_name, d.full_name AS doctor_name
                                FROM patients p
                                JOIN users u ON u.id = p.user_id
                                JOIN doctors d ON d.id = :did
                               WHERE p.id = :pid LIMIT 1");
        $stmt->execute([':pid' => $patient_id, ':did' => $doctor_id]);
        $info = $stmt->fetch();
        if ($info) {
            Notifier::notify(
                $db, (int) $info['user_id'], 'followup_scheduled',
                'Follow-up scheduled',
                "Your follow-up #{$appointment_number} is on {$appointment_date} at {$appointment_time}.",
                'patient-dashboard.html'
            );
            if (!empty($info['email'])) {
                (new Mailer())->sendAppointmentConfirmation(
                    $info['email'], $info['full_name'], $appointment_number,
                    $appointment_date, $appointment_time, $info['doctor_name']
                );
            }
        }
    } catch (Exception $e) {
        error_log("schedule-followup notify error: " . $e->getMessage());
    }

    sendJSON([
        'success'            => true,
        'mode'               => $mode,
        'appointment_id'     => $appointment_id,
        'appointment_number' => $appointment_number,
        'qr_payload'         => $qr,
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("schedule-followup error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to schedule follow-up'], 500);
}
```

- [ ] **Step 2: Implement `get-followup.php`**

Create `api/doctor/get-followup.php` with EXACTLY:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$parent_appointment_id = (int) ($_GET['parent_appointment_id'] ?? 0);
if (!$parent_appointment_id) {
    sendJSON(['success' => false, 'message' => 'parent_appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
    }
    $doctor_id = (int) $doctor['id'];

    $stmt = $db->prepare("
        SELECT id, appointment_number, appointment_date, appointment_time, status, reason_for_visit
          FROM appointments
         WHERE parent_appointment_id = :pid
           AND is_followup = 1
           AND doctor_id = :did
           AND status IN ('scheduled','checked_in','in_progress')
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([':pid' => $parent_appointment_id, ':did' => $doctor_id]);
    sendJSON(['success' => true, 'followup' => $stmt->fetch() ?: null]);
} catch (Exception $e) {
    error_log("doctor/get-followup error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load follow-up'], 500);
}
```

- [ ] **Step 3: Lint**

```bash
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/doctor/schedule-followup.php
php -l /Applications/XAMPP/xamppfiles/htdocs/meditrack/api/doctor/get-followup.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual verification**

1. Pick a `completed` appointment (from a known doctor's records). Note the parent appointment id and the patient's id.
2. POST a follow-up:
   ```bash
   curl -X POST -H "Content-Type: application/json" -b "PHPSESSID=<doctor>" \
     -d '{"parent_appointment_id":<PARENT_ID>,"appointment_date":"2026-06-15","appointment_time":"10:30","reason_for_visit":"Follow-up: BP review"}' \
     http://localhost/meditrack/api/doctor/schedule-followup.php
   ```
   Expected: `{"success":true,"mode":"created","appointment_id":N,...}`. Verify in MySQL: a new `appointments` row with `is_followup=1`, `parent_appointment_id=<PARENT_ID>`, `status='scheduled'`. A `qr_tokens` row exists for the new appointment.
3. Re-POST with a different time on the same date — expected: `mode:"updated"`, same row, time changed, qr_tokens row replaced.
4. POST a date in the past → expected: 400.
5. POST with a slot already taken by an unrelated appointment → expected: 409.
6. GET `api/doctor/get-followup.php?parent_appointment_id=<PARENT_ID>` → expected: returns the active follow-up row.
7. As the patient, GET `api/patient/get-appointments.php` → the follow-up appointment appears in the list with `is_followup=1`.
8. Cancel the follow-up via the patient cancel flow (>2h ahead) → DB row goes `cancelled`. Re-POST schedule-followup with a new date → expected: `mode:"created"` (a new follow-up row, since the old one is no longer active).

- [ ] **Step 5: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add api/doctor/schedule-followup.php api/doctor/get-followup.php
git commit -m "feat(followup): doctor schedule + get follow-up endpoints (with QR token reissue)"
```

---

## Task 6: Doctor UI — Follow-Up Card

**Files:**
- Modify: `pages/doctor-dashboard.html`
- Modify: `js/doctor-dashboard.js`

The existing form has a bare `Follow-up Date` input. Replace it with a richer card that drives `schedule-followup.php`.

- [ ] **Step 1: Replace the Follow-up block in `doctor-dashboard.html`**

Find the existing block that contains `id="rec-followup"` (the `Follow-up Date` input). Replace the entire `<!-- ─ Follow-up Date ─ -->` section with:

```html
                    <!-- ─ Schedule follow-up (optional) ─ -->
                    <div class="rounded-2xl border border-gray-100 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center space-x-2"
                             style="background:linear-gradient(135deg,#F0FDF4,#F7FFFA)">
                            <div class="w-6 h-6 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                                <i class="fas fa-calendar-plus text-emerald-600 text-xs"></i>
                            </div>
                            <span class="text-xs font-bold text-[#083344] uppercase tracking-wide">Schedule Follow-up</span>
                            <label class="ml-auto inline-flex items-center gap-2 text-xs">
                                <input type="checkbox" id="fu-enable" class="form-checkbox">
                                <span class="text-slate-600 normal-case">Schedule a follow-up appointment</span>
                            </label>
                        </div>
                        <div id="fu-body" class="hidden p-4 bg-white space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Date</span>
                                    <input type="date" id="fu-date" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Time</span>
                                    <input type="time" id="fu-time" step="900" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </label>
                                <label class="block sm:col-span-1">
                                    <span class="text-xs font-semibold text-slate-600">Reason (optional)</span>
                                    <input id="fu-reason" placeholder="(auto-filled if blank)" class="mt-1 w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </label>
                            </div>
                            <div class="flex justify-end gap-2 items-center">
                                <span id="fu-status" class="text-xs text-slate-400 mr-auto"></span>
                                <button type="button" id="fu-save-btn" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                                    Save follow-up
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Hidden compatibility input — older save-medical-record paths still expect rec-followup. -->
                    <input type="hidden" id="rec-followup" value="">
```

The hidden `rec-followup` input is preserved for backward compat with existing JS that reads `document.getElementById('rec-followup').value`. The new card writes the date there as a fallback.

- [ ] **Step 2: Wire the JS**

Append to `js/doctor-dashboard.js`:

```javascript
// ─── Follow-up scheduling (Batch C3) ────────────────────────────
function bindFollowupCard() {
    const enable = document.getElementById('fu-enable');
    const body   = document.getElementById('fu-body');
    if (!enable || !body) return;
    enable.addEventListener('change', () => body.classList.toggle('hidden', !enable.checked));
    document.getElementById('fu-save-btn').addEventListener('click', saveFollowup);
}

async function loadFollowupForAppointment(appointment_id) {
    const enable = document.getElementById('fu-enable');
    const body   = document.getElementById('fu-body');
    const dateEl = document.getElementById('fu-date');
    const timeEl = document.getElementById('fu-time');
    const reasonEl = document.getElementById('fu-reason');
    const statusEl = document.getElementById('fu-status');
    const hidden = document.getElementById('rec-followup');
    if (!enable || !body) return;
    // Reset
    enable.checked = false;
    body.classList.add('hidden');
    dateEl.value = '';
    timeEl.value = '';
    reasonEl.value = '';
    statusEl.textContent = '';
    if (hidden) hidden.value = '';

    try {
        const data = await apiRequest('/doctor/get-followup.php?parent_appointment_id=' + appointment_id);
        if (data && data.success && data.followup) {
            const f = data.followup;
            enable.checked = true;
            body.classList.remove('hidden');
            dateEl.value = f.appointment_date || '';
            timeEl.value = (f.appointment_time || '').substring(0, 5);
            reasonEl.value = f.reason_for_visit || '';
            statusEl.textContent = `Existing follow-up #${f.appointment_number} (${f.status})`;
            if (hidden) hidden.value = f.appointment_date || '';
        }
    } catch (_) {}
}

async function saveFollowup() {
    if (!currentAppointment) return;
    const date = document.getElementById('fu-date').value;
    const time = document.getElementById('fu-time').value;
    const reason = document.getElementById('fu-reason').value.trim();
    const statusEl = document.getElementById('fu-status');
    if (!date || !time) {
        Swal.fire('Required', 'Pick both a date and a time.', 'warning');
        return;
    }
    statusEl.textContent = 'Saving…';
    const data = await apiRequest('/doctor/schedule-followup.php', {
        method: 'POST',
        body: JSON.stringify({
            parent_appointment_id: currentAppointment.id,
            appointment_date: date,
            appointment_time: time,
            reason_for_visit: reason
        })
    });
    if (!data || !data.success) {
        statusEl.textContent = '';
        Swal.fire('Error', data?.message || 'Failed to save follow-up', 'error');
        return;
    }
    statusEl.textContent = `Follow-up #${data.appointment_number} ${data.mode} for ${date} ${time}`;
    const hidden = document.getElementById('rec-followup');
    if (hidden) hidden.value = date;
}

window.addEventListener('DOMContentLoaded', bindFollowupCard);
```

- [ ] **Step 3: Trigger `loadFollowupForAppointment` when the drawer opens**

Find `function openRecordModal(appointment) {`. Just before the `modal.classList.remove('hidden');` line (you should already have inserted `loadReferralForAppointment` there in Task 4), add another sibling line:

```javascript
    if (appointment.id) loadFollowupForAppointment(appointment.id);
```

- [ ] **Step 4: Manual verification**

1. As doctor, open a `completed` appointment's drawer → "Schedule Follow-up" card visible (toggle off).
2. Toggle on → date+time+reason fields revealed.
3. Pick a date next week and a time → click "Save follow-up". Expected: status line updates to "Follow-up #APT-... created".
4. In MySQL: `SELECT id, parent_appointment_id, is_followup, status, appointment_date, appointment_time FROM appointments WHERE parent_appointment_id = <PARENT_ID>;` → one row with the new values. `SELECT * FROM qr_tokens WHERE appointment_id = <follow-up-id>` → one row.
5. Close drawer; re-open same appointment → card pre-fills with the saved values; status shows "Existing follow-up".
6. Change time; save → status line says "updated"; qr_tokens row reissued.
7. As the patient (different browser session), log into patient dashboard → the follow-up appointment appears in the upcoming list. (Patient-side pill is added in Task 7.)
8. Patient cancels the follow-up → reload doctor dashboard, open parent → the card is empty again (no active follow-up).
9. Save a new date → expected: `mode:"created"`.

- [ ] **Step 5: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add pages/doctor-dashboard.html js/doctor-dashboard.js
git commit -m "feat(followup): doctor schedule-follow-up card replaces bare date field"
```

---

## Task 7: Patient Dashboard — Pills & Print Buttons

**Files:**
- Modify: `pages/patient-dashboard.html`

(May also touch `js/patient-dashboard.js` if the patient appointment renderer lives there — inspect first and follow the existing pattern.)

- [ ] **Step 1: Inspect the patient appointment renderer**

```bash
grep -ln "appointment_number\|reason_for_visit\|status_pill\|loadAppointments" /Applications/XAMPP/xamppfiles/htdocs/meditrack/pages/patient-dashboard.html /Applications/XAMPP/xamppfiles/htdocs/meditrack/js/patient-dashboard.js 2>/dev/null
```

Find the function/section that renders an appointment row (likely `renderAppointment(appt)` or inline template literals). Note where it builds the row's HTML.

- [ ] **Step 2: Add a "Follow-up" pill when `is_followup=1`**

Inside the appointment row template, near the existing status badge, add a conditional pill:

```javascript
${appt.is_followup ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-semibold uppercase tracking-wide">Follow-up</span>' : ''}
```

If the renderer is server-side PHP (not JS), the equivalent inside the row markup is:

```php
<?php if (!empty($appt['is_followup'])): ?>
  <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-semibold uppercase">Follow-up</span>
<?php endif; ?>
```

- [ ] **Step 3: Add a "Print referral" button on past completed appointments**

Inside the past/completed appointment row template, add a conditional button. Patients can already access referrals via `api/staff/referral.php`, which permits the owning patient. Pseudo-code:

```javascript
${appt.status === 'completed'
  ? `<a target="_blank" href="print-referral.html?appointment_id=${appt.id}"
        class="ml-2 px-2 py-1 rounded-lg border border-slate-200 text-slate-600 text-[10px] font-semibold hover:bg-slate-50">
       Print referral
     </a>` : ''}
```

A more refined version would only show the button when a referral actually exists. The simplest implementation: always show the button on `completed` appointments; if no referral exists, the print page renders "Referral not found." That UX is acceptable for v1 (avoids an extra API roundtrip just to gate visibility).

- [ ] **Step 4: Confirm `is_followup` is in the patient's appointments response**

The patient endpoint `api/patient/get-appointments.php` queries `appointments` directly. Open it and verify the SELECT pulls `is_followup`. If it doesn't, add it:

```php
SELECT a.id, a.appointment_number, ..., a.is_followup, ...
```

(The DB column was added in Task 1; the API just needs to surface it.)

- [ ] **Step 5: Manual verification**

1. As doctor, schedule a follow-up for a patient (Task 6).
2. Log in as that patient → upcoming appointments list shows the follow-up with the green "Follow-up" pill next to the status badge.
3. Past completed appointments show a "Print referral" button → click → opens `print-referral.html?appointment_id=N` in a new tab.
4. If a referral was issued (Task 4), the letter renders. If not, it shows "Referral not found."
5. Cross-check: another patient's appointment is NOT visible in this patient's list.

- [ ] **Step 6: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
git add pages/patient-dashboard.html js/patient-dashboard.js api/patient/get-appointments.php
git commit -m "feat(patient): follow-up pill + print-referral button on past appointments"
```

(Adjust `git add` paths to whatever you actually modified; `js/patient-dashboard.js` and the API may not need changes if they already cover these cases.)

---

## End-to-End Verification

After all 7 tasks, walk through the full doctor referral + follow-up flow:

1. **Doctor** opens a `completed` appointment's medical record drawer.
2. **Refer to specialist**: toggle on → choose ENT → reason "ear pain" → urgency "routine" → save → click "Print referral" → letter renders with letterhead, recipient ("Attending ENT Specialist"), reason, urgency pill, doctor signature.
3. **Schedule follow-up**: toggle on → next week, 10:30, reason "BP recheck" → save. New appointment row created. QR token generated. Patient receives in-app + email confirmation.
4. **Patient** logs in → upcoming list shows the follow-up with green "Follow-up" pill. Past appointment row shows "Print referral" button → click → letter renders.
5. **Doctor** re-opens the parent appointment → both cards (referral + follow-up) pre-fill from existing rows; status lines show "on file" / "existing follow-up".
6. **Patient cancels** the follow-up >2h ahead → DB row goes `cancelled`.
7. **Doctor** re-opens parent → follow-up card is back to empty (the cancelled row is filtered out by the active-status check). Saves a new date → `mode:"created"`.
8. **Doctor** edits the referral specialty to Cardiology → save → re-print → letter shows the updated specialty. Only one referral row exists in DB (UNIQUE constraint on appointment_id).

If all eight steps pass, C3 is complete.

---

## Self-Review Checklist (against spec)

- [x] **Spec Feature 1 — referrals:** Tasks 1 (table), 2 (3 endpoints), 3 (print page), 4 (doctor UI).
- [x] **Specialty dropdown including ENT/Cardio/OB-GYN/Pedia/Surgery/Derma/Ortho/Ophthalmology/Neurology/Other:** Task 4 step 1 (dropdown options) + Task 2 step 1 (`$ALLOWED_SPECIALTIES`).
- [x] **Urgency routine|urgent|emergency:** Task 1 (ENUM) + Task 2 step 1 (`$ALLOWED_URGENCY`) + Task 4 (UI dropdown).
- [x] **One referral per appointment:** Task 1 `UNIQUE KEY uniq_referral_appointment`.
- [x] **Spec Feature 2 — follow-up auto-booking:** Tasks 1 (columns), 5 (endpoints), 6 (doctor UI), 7 (patient pill).
- [x] **Schedule must be in the future:** Task 5 step 1 (`$appointment_date <= date('Y-m-d')` rejects today and earlier).
- [x] **Slot lock:** Task 5 step 1 (`SELECT … FOR UPDATE`).
- [x] **QR token (re)issue on schedule + reschedule:** Task 5 step 1 (`generateQRCode` after delete on update).
- [x] **Active vs. cancelled-only follow-ups: update active in place; create new if only cancelled exist:** Task 5 step 1 (the `existing` query filters by status).
- [x] **Spec Feature 3 — quick re-print from patient history:** Task 7.
- [x] **Backend auth for `staff/referral.php` (doctor/staff/admin/owning patient):** Task 2 step 3 authorization block.
- [x] **Manual verification:** present in Tasks 1, 2, 3, 4, 5, 6, 7.

## Open Questions

None.
