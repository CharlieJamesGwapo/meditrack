# Batch C1 — Staff Role, Vitals, Medical Certificate (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a real `staff` user role with their own dashboard; move vital-sign capture from the doctor's medical-record form into a staff step that runs between QR check-in and the doctor consultation; let staff issue a printable medical certificate.

**Architecture:** Layered on top of the existing PHP/PDO/XAMPP codebase. New staff-only endpoints under `api/staff/`, mirroring the patterns in `api/doctor/` and `api/patient/`. Vitals are stored in the existing `triage_assessments` table (extended with `appointment_id`, `height_cm`, `oxygen_saturation`); the doctor's `medical_records.vital_signs` JSON column becomes legacy-read-only. Medical certificates live in a new `medical_certificates` table and render through `pages/print-certificate.html` using the existing letterhead pattern from `print-record.html`.

**Tech Stack:** PHP 8 + PDO + MySQL/MariaDB (XAMPP), vanilla JS + Tailwind CDN frontend, SweetAlert2 modals, FontAwesome icons. No build step. The project has no automated test framework — verification is **manual in a browser via XAMPP**, matching the convention used by Batch A.

**Spec:** `docs/superpowers/specs/2026-05-06-batch-c1-staff-role-vitals-cert-design.md`

---

## File Structure

**New files:**

| Path | Responsibility |
|---|---|
| `database/migrations/2026-05-06-batch-c1.sql` | One-shot migration: extend `users.role` enum, create `staff_profiles` and `medical_certificates`, alter `triage_assessments` |
| `api/admin/add-staff.php` | Admin-only: create a `staff` user + `staff_profiles` row |
| `api/admin/get-staff.php` | Admin-only: list staff users for the management section |
| `api/admin/update-staff-status.php` | Admin-only: activate/deactivate a staff user |
| `api/staff/queue.php` | Staff: list today's `checked_in` appointments awaiting vitals |
| `api/staff/save-vitals.php` | Staff (or doctor fallback): upsert vitals into `triage_assessments`, advance status to `in_progress` |
| `api/staff/get-vitals.php` | Staff (or doctor): fetch vitals for prefill |
| `api/staff/issue-certificate.php` | Staff: upsert into `medical_certificates` |
| `api/staff/certificate.php` | Staff/admin/doctor: read a single cert + joined data for printing |
| `api/staff/certificates.php` | Staff/admin: list certs (date-range) for the certificates tab |
| `pages/staff-dashboard.html` | Staff main UI — three tabs: Scan QR, Vitals queue, Certificates |
| `pages/print-certificate.html` | Print-only cert layout; reads `?appointment_id=N` |

**Modified files:**

| Path | Change |
|---|---|
| `database/schema.sql` | Reflect role enum + new tables + altered `triage_assessments` (so a fresh install matches) |
| `pages/login.html` | Extend the role→page redirect map to include `staff: 'staff-dashboard.html'` (two locations) |
| `pages/admin-dashboard.html` | Add "Staff" management section (list, add, deactivate) |
| `pages/doctor-dashboard.html` | Add read-only **Vital Signs** card on top of the medical-record form; surface chief complaint read-only; add fallback "Record vitals" button when empty |
| `api/doctor/save-medical-record.php` | Drop `vital_signs` and `chief_complaint` from the writeable column list |
| `api/doctor/get-appointments.php` | Left-join `triage_assessments` so each appointment row carries `vitals` + `chief_complaint` |

---

## Conventions

- **No automated tests.** Each task ends with concrete **Manual verification** steps performed in a local XAMPP browser session. This matches the Batch A pattern and the project's existing testing convention.
- **Commit after each task** with a focused conventional-commit message. Don't batch tasks into one commit.
- **PHP role check** uses the existing `hasRole($role)` helper in `config/config.php`. Staff endpoints permit `staff` only; the vitals save endpoint also permits `doctor` (fallback per spec Feature 2).
- **Auth response shape:** all `api/*.php` endpoints return `{ success: bool, message: string, ...payload }` and call `sendJSON(...)`.
- **Database connection:** every endpoint starts with `(new Database())->getConnection()`.
- **Activity logging:** writes call `logActivity($db, $userId, $username, $role, $action, $module, $record_id, $description)` after the DB write commits.

---

## Task 1: Database Migration

**Files:**
- Create: `database/migrations/2026-05-06-batch-c1.sql`
- Modify: `database/schema.sql`

- [ ] **Step 1: Create the migration file**

```sql
-- database/migrations/2026-05-06-batch-c1.sql
-- Batch C1 — staff role, vitals capture extension, medical certificates.
-- Idempotent: re-running on a partially-migrated DB should not error.

-- 1. Extend the users.role ENUM
ALTER TABLE users
  MODIFY COLUMN role ENUM('patient','doctor','admin','staff') NOT NULL;

-- 2. Staff profile table
CREATE TABLE IF NOT EXISTS staff_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Extend triage_assessments
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'appointment_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN appointment_id INT NULL AFTER patient_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'height_cm'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN height_cm INT NULL AFTER weight',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'oxygen_saturation'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN oxygen_saturation TINYINT UNSIGNED NULL AFTER height_cm',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND index_name = 'uniq_triage_appointment'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE triage_assessments ADD UNIQUE KEY uniq_triage_appointment (appointment_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND constraint_name = 'fk_triage_appointment'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE triage_assessments ADD CONSTRAINT fk_triage_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Medical certificates
CREATE TABLE IF NOT EXISTS medical_certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  issued_by_user_id INT NOT NULL,
  diagnosis VARCHAR(500) NOT NULL,
  rest_period_start DATE NOT NULL,
  rest_period_end DATE NOT NULL,
  rest_days INT NOT NULL,
  notes TEXT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cert_appointment (appointment_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Update `database/schema.sql`** to reflect the new shape

Open `database/schema.sql` and apply these edits:

1. Change line 29 from `role ENUM('patient', 'doctor', 'admin') NOT NULL,` to `role ENUM('patient', 'doctor', 'admin', 'staff') NOT NULL,`.
2. After the `activity_logs` block (around line 178), append the `staff_profiles` and `medical_certificates` table definitions from the migration above (without the conditional `IF NOT EXISTS` plumbing — schema.sql is the from-scratch source of truth).
3. The existing `triage_assessments` definition lives in `database/create_triage_table.sql`, not `schema.sql`. Update **that** file: in the `CREATE TABLE triage_assessments` block, add `appointment_id INT NULL,` (after `patient_id`), `height_cm INT NULL,` (after `weight`), `oxygen_saturation TINYINT UNSIGNED NULL,` (after `height_cm`), `FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,`, and `UNIQUE KEY uniq_triage_appointment (appointment_id)`.

- [ ] **Step 3: Run the migration locally**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < database/migrations/2026-05-06-batch-c1.sql
```

(If your local DB user/name differs, adjust accordingly. If the project doesn't have `meditrack` as the local DB name, check `env.php` and `config/database.php` for the correct one.)

Expected: zero errors, exit code 0.

- [ ] **Step 4: Verify migrated schema**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "
  SHOW COLUMNS FROM users LIKE 'role';
  DESC triage_assessments;
  DESC staff_profiles;
  DESC medical_certificates;
"
```

Expected:
- `users.role` is `enum('patient','doctor','admin','staff')`
- `triage_assessments` has `appointment_id`, `height_cm`, `oxygen_saturation` columns
- `staff_profiles` and `medical_certificates` exist with the columns from Step 1.

- [ ] **Step 5: Re-run the migration** (idempotency check)

Run the same `mysql < ...` command again. Expected: still zero errors. The conditional `PREPARE/EXECUTE` blocks should detect existing columns/indexes and skip them.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026-05-06-batch-c1.sql database/schema.sql database/create_triage_table.sql
git commit -m "feat(db): add staff role + medical_certificates + extended triage_assessments (Batch C1)"
```

---

## Task 2: Login Redirect for Staff Role

**Files:**
- Modify: `pages/login.html` (lines 407 and 495)

- [ ] **Step 1: Update both redirect maps**

Open `pages/login.html`. Find the two occurrences of:

```javascript
var map = { patient: 'patient-dashboard.html', doctor: 'doctor-dashboard.html', admin: 'admin-dashboard.html' };
```

Replace each with:

```javascript
var map = { patient: 'patient-dashboard.html', doctor: 'doctor-dashboard.html', admin: 'admin-dashboard.html', staff: 'staff-dashboard.html' };
```

- [ ] **Step 2: Manual verification (login routing only — staff-dashboard doesn't exist yet)**

Since the staff-dashboard page is created in Task 5, we'll verify routing now using a temporary file:

```bash
touch /Applications/XAMPP/xamppfiles/htdocs/meditrack/pages/staff-dashboard.html
echo "<h1>Staff dashboard placeholder</h1>" > /Applications/XAMPP/xamppfiles/htdocs/meditrack/pages/staff-dashboard.html
```

Manually:
1. Open MySQL and create a test staff user:
   ```sql
   INSERT INTO users (email, username, password_hash, role, status)
   VALUES ('staff@meditrack.com', 'staff', '$2y$10$TwQZx/2vkenWMPl8tS1ieeV5eje0SE9N5ew2sVNV50Rn3Ur519Z6u', 'staff', 'active');
   -- password: admin123 (reusing the seeded admin hash for convenience)
   INSERT INTO staff_profiles (user_id, full_name) VALUES (LAST_INSERT_ID(), 'Test Staff');
   ```
2. Browse to `http://localhost/meditrack/pages/login.html`, log in with `staff@meditrack.com` / `admin123`.
3. Expected: redirected to `staff-dashboard.html` (the placeholder you just created).
4. Delete the placeholder so Task 5 can create it cleanly:
   ```bash
   rm /Applications/XAMPP/xamppfiles/htdocs/meditrack/pages/staff-dashboard.html
   ```

- [ ] **Step 3: Commit**

```bash
git add pages/login.html
git commit -m "feat(auth): route staff role to staff-dashboard"
```

---

## Task 3: Admin Endpoint — Add Staff

**Files:**
- Create: `api/admin/add-staff.php`

- [ ] **Step 1: Implement the endpoint**

Create `api/admin/add-staff.php`:

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);

$full_name      = sanitizeInput($input['full_name'] ?? '');
$email          = sanitizeInput($input['email'] ?? '');
$username       = sanitizeInput($input['username'] ?? '');
$password       = $input['password'] ?? '';
$contact_number = sanitizeInput($input['contact_number'] ?? '');

if (empty($full_name) || empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Full name, email, and password are required'], 400);
}
if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}
if (!filter_var(html_entity_decode($email), FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email address'], 400);
}

if (empty($username)) {
    $username = strtolower(explode('@', html_entity_decode($email))[0]);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => html_entity_decode($email)]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'A user with this email already exists'], 409);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        $username = $username . '_' . rand(100, 999);
    }

    $db->beginTransaction();

    $password_hash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $stmt = $db->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (:email, :username, :hash, 'staff', 'active')");
    $stmt->execute([
        ':email'    => html_entity_decode($email),
        ':username' => $username,
        ':hash'     => $password_hash
    ]);
    $user_id = (int) $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, full_name, contact_number) VALUES (:uid, :name, :contact)");
    $stmt->execute([
        ':uid'     => $user_id,
        ':name'    => $full_name,
        ':contact' => $contact_number ?: null
    ]);
    $staff_id = (int) $db->lastInsertId();

    $db->commit();

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'CREATE', 'Staff', $staff_id, "Added staff: $full_name ($email)");

    sendJSON([
        'success'  => true,
        'message'  => 'Staff member added successfully',
        'staff_id' => $staff_id,
        'user_id'  => $user_id,
        'username' => $username,
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("add-staff error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to add staff member. Please try again.'], 500);
}
```

- [ ] **Step 2: Manual verification with curl**

Log in as admin in a browser first to obtain a session cookie, then export `PHPSESSID` from devtools and:

```bash
curl -X POST http://localhost/meditrack/api/admin/add-staff.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=<your-session-id>" \
  -d '{"full_name":"Nurse Alice","email":"alice@meditrack.com","password":"alice123","contact_number":"09171234567"}'
```

Expected: HTTP 201 and `{"success":true,...}`. Verify in MySQL:

```sql
SELECT u.id, u.email, u.role, u.status, s.full_name, s.contact_number
  FROM users u JOIN staff_profiles s ON s.user_id = u.id
 WHERE u.email = 'alice@meditrack.com';
```

Expected: one row, role = `staff`.

Negative cases:
- Missing password → HTTP 400.
- Duplicate email → HTTP 409.
- Calling as non-admin (log in as patient or hit without cookie) → HTTP 401.

- [ ] **Step 3: Commit**

```bash
git add api/admin/add-staff.php
git commit -m "feat(admin): add-staff endpoint creates staff user + profile"
```

---

## Task 4: Admin Endpoints — List & Update Staff Status

**Files:**
- Create: `api/admin/get-staff.php`
- Create: `api/admin/update-staff-status.php`

- [ ] **Step 1: Implement `get-staff.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("
        SELECT u.id AS user_id, u.email, u.username, u.status, u.last_login, u.created_at,
               s.id AS staff_id, s.full_name, s.contact_number
          FROM users u
          JOIN staff_profiles s ON s.user_id = u.id
         WHERE u.role = 'staff'
         ORDER BY u.created_at DESC
    ");
    sendJSON(['success' => true, 'staff' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("get-staff error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load staff list'], 500);
}
```

- [ ] **Step 2: Implement `update-staff-status.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input   = json_decode(file_get_contents('php://input'), true);
$user_id = (int) ($input['user_id'] ?? 0);
$status  = sanitizeInput($input['status'] ?? '');

if (!$user_id || !in_array($status, ['active', 'inactive'], true)) {
    sendJSON(['success' => false, 'message' => 'user_id and status (active|inactive) are required'], 400);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $u = $stmt->fetch();
    if (!$u || $u['role'] !== 'staff') {
        sendJSON(['success' => false, 'message' => 'Staff user not found'], 404);
    }

    $db->prepare("UPDATE users SET status = :s WHERE id = :uid")
       ->execute([':s' => $status, ':uid' => $user_id]);

    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Staff', $user_id, "Set staff user $user_id status=$status");

    sendJSON(['success' => true, 'message' => "Staff status updated to $status"]);
} catch (Exception $e) {
    error_log("update-staff-status error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update staff status'], 500);
}
```

- [ ] **Step 3: Manual verification**

```bash
curl -b "PHPSESSID=<admin-session>" http://localhost/meditrack/api/admin/get-staff.php
```

Expected: list including the Alice row from Task 3.

```bash
curl -X POST -b "PHPSESSID=<admin-session>" -H "Content-Type: application/json" \
  -d '{"user_id":<alice-user-id>,"status":"inactive"}' \
  http://localhost/meditrack/api/admin/update-staff-status.php
```

Expected: `success:true`. Re-call `get-staff.php` and confirm Alice's `status` is `inactive`. Then flip it back to `active`.

- [ ] **Step 4: Commit**

```bash
git add api/admin/get-staff.php api/admin/update-staff-status.php
git commit -m "feat(admin): list staff + update staff status endpoints"
```

---

## Task 5: Admin UI — Staff Management Section

**Files:**
- Modify: `pages/admin-dashboard.html`

- [ ] **Step 1: Inspect the existing Doctors section**

Open `pages/admin-dashboard.html` and locate the existing "Doctors" management section (search for `add-doctor` or `getDoctors`). Note the table structure, the "Add doctor" modal, and the JS pattern that renders the list.

- [ ] **Step 2: Add a "Staff" sidebar nav entry**

Find the sidebar nav `<a>`/button list. After the "Doctors" entry, add:

```html
<button data-section="staff" class="nav-link">
  <i class="fa-solid fa-user-nurse"></i><span>Staff</span>
</button>
```

(Match the exact class names and `data-section` attribute pattern used by the existing Doctors entry in this file.)

- [ ] **Step 3: Add the staff section markup**

After the `#section-doctors` block, add:

```html
<section id="section-staff" class="section hidden">
  <div class="section-header">
    <h2>Staff</h2>
    <button id="btn-add-staff" class="btn btn-primary">
      <i class="fa-solid fa-user-plus"></i> Add Staff
    </button>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Username</th><th>Contact</th><th>Status</th><th>Last Login</th><th></th></tr>
    </thead>
    <tbody id="staff-tbody"><tr><td colspan="7">Loading…</td></tr></tbody>
  </table>
</section>
```

- [ ] **Step 4: Add the "Add Staff" modal**

Place after the existing "Add Doctor" modal block:

```html
<div id="modal-add-staff" class="modal hidden">
  <div class="modal-card">
    <h3>Add Staff Member</h3>
    <form id="form-add-staff">
      <label>Full name <input name="full_name" required></label>
      <label>Email <input type="email" name="email" required></label>
      <label>Username <span class="hint">(optional, auto-generated)</span> <input name="username"></label>
      <label>Contact number <input name="contact_number"></label>
      <label>Password <input type="password" name="password" minlength="6" required></label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Add Staff</button>
      </div>
    </form>
  </div>
</div>
```

- [ ] **Step 5: Wire JS — load list, add staff, toggle status**

Inside the dashboard's main `<script>`, after the Doctors block, add:

```javascript
async function loadStaff() {
  const tbody = document.getElementById('staff-tbody');
  tbody.innerHTML = '<tr><td colspan="7">Loading…</td></tr>';
  try {
    const res = await fetch('../api/admin/get-staff.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    if (!data.staff.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#888">No staff yet — add one to start.</td></tr>';
      return;
    }
    tbody.innerHTML = data.staff.map(s => `
      <tr>
        <td>${escapeHtml(s.full_name)}</td>
        <td>${escapeHtml(s.email)}</td>
        <td>${escapeHtml(s.username)}</td>
        <td>${escapeHtml(s.contact_number || '—')}</td>
        <td><span class="status-pill status-${s.status}">${s.status}</span></td>
        <td>${s.last_login ? new Date(s.last_login).toLocaleString() : '—'}</td>
        <td>
          <button class="btn btn-sm" data-toggle-staff="${s.user_id}" data-current="${s.status}">
            ${s.status === 'active' ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
    tbody.querySelectorAll('[data-toggle-staff]').forEach(b =>
      b.addEventListener('click', () => toggleStaffStatus(+b.dataset.toggleStaff, b.dataset.current))
    );
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="color:#c00">Failed to load: ${escapeHtml(e.message)}</td></tr>`;
  }
}

async function toggleStaffStatus(user_id, current) {
  const next = current === 'active' ? 'inactive' : 'active';
  const res = await fetch('../api/admin/update-staff-status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id, status: next }),
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (data.success) loadStaff();
  else Swal.fire('Error', data.message, 'error');
}

document.getElementById('btn-add-staff').addEventListener('click', () =>
  document.getElementById('modal-add-staff').classList.remove('hidden')
);
document.querySelectorAll('#modal-add-staff [data-close]').forEach(b =>
  b.addEventListener('click', () => document.getElementById('modal-add-staff').classList.add('hidden'))
);
document.getElementById('form-add-staff').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const body = Object.fromEntries(fd.entries());
  const res = await fetch('../api/admin/add-staff.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (!data.success) { Swal.fire('Error', data.message, 'error'); return; }
  document.getElementById('modal-add-staff').classList.add('hidden');
  ev.target.reset();
  Swal.fire('Added', `Staff member created (username: ${data.username}).`, 'success');
  loadStaff();
});
```

Add this hook to whichever `showSection('staff')` (or equivalent) function the dashboard already uses for tab switching:

```javascript
if (sectionId === 'staff') loadStaff();
```

(If the dashboard uses a different mechanism for lazy-loading sections, follow that pattern instead — match what `loadDoctors` does.)

- [ ] **Step 6: Manual verification**

1. Log in as admin (`admin@meditrack.com` / `admin123`).
2. Click **Staff** in the sidebar — list loads (Alice from Task 3 should appear if she still exists).
3. Click **Add Staff** → fill form (e.g., Bob, `bob@meditrack.com`, password `bob1234`) → submit. Expected: success modal, list refreshes, Bob appears.
4. Click **Deactivate** on Bob → row updates to `inactive`. Click **Activate** → reverts.
5. Open a private window, browse to login, attempt to log in as Bob while inactive → expected: existing login flow rejects inactive users (verify against current behavior; if it doesn't, log a follow-up but don't fix here — out of scope).

- [ ] **Step 7: Commit**

```bash
git add pages/admin-dashboard.html
git commit -m "feat(admin): staff management section in admin dashboard"
```

---

## Task 6: Staff Dashboard Scaffold

**Files:**
- Create: `pages/staff-dashboard.html`

- [ ] **Step 1: Build the page skeleton**

Create `pages/staff-dashboard.html` mirroring the visual style of `doctor-dashboard.html` (sidebar + topbar + tabs). Borrow the existing CSS approach and asset includes. Skeleton:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard — Consultation OPD</title>
  <link rel="icon" type="image/png" href="../assets/images/medicare.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <div class="dashboard-shell">
    <aside class="sidebar">
      <div class="brand"><img src="../assets/images/medicare.png" alt=""><span>Staff</span></div>
      <nav>
        <button data-tab="scan" class="nav-link active"><i class="fa-solid fa-qrcode"></i><span>Scan QR</span></button>
        <button data-tab="vitals" class="nav-link"><i class="fa-solid fa-heart-pulse"></i><span>Vitals Queue</span></button>
        <button data-tab="certs" class="nav-link"><i class="fa-solid fa-file-medical"></i><span>Certificates</span></button>
      </nav>
      <button id="logout-btn" class="logout"><i class="fa-solid fa-right-from-bracket"></i><span>Log out</span></button>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1 id="page-title">Scan QR</h1>
        <div id="staff-name-chip" class="user-chip"></div>
      </header>

      <section id="tab-scan" class="tab"><!-- Task 7 fills this --></section>
      <section id="tab-vitals" class="tab hidden"><!-- Task 9 fills this --></section>
      <section id="tab-certs" class="tab hidden"><!-- Task 12 fills this --></section>
    </main>
  </div>

  <script>
    (async function guard() {
      const res = await fetch('../api/auth/check-session.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success || data.user.role !== 'staff') {
        window.location.replace('login.html');
        return;
      }
      document.getElementById('staff-name-chip').textContent = data.user.full_name || data.user.username;
    })();

    document.querySelectorAll('.nav-link[data-tab]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-link[data-tab]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab').forEach(t => t.classList.add('hidden'));
        document.getElementById('tab-' + tab).classList.remove('hidden');
        const titles = { scan: 'Scan QR', vitals: 'Vitals Queue', certs: 'Certificates' };
        document.getElementById('page-title').textContent = titles[tab];
        if (tab === 'vitals' && typeof loadVitalsQueue === 'function') loadVitalsQueue();
        if (tab === 'certs' && typeof loadCertList === 'function') loadCertList();
      });
    });

    document.getElementById('logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth/logout.php', { method: 'POST', credentials: 'same-origin' });
      window.location.replace('login.html');
    });

    function escapeHtml(s) {
      return String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    window.escapeHtml = escapeHtml;
  </script>
</body>
</html>
```

If `assets/css/dashboard.css` does not exist, inspect what `doctor-dashboard.html` uses for styling and copy the relevant style block inline (top-level grid + sidebar + nav-link + tab + table styles). Match the visual language, not the exact file structure.

- [ ] **Step 2: Manual verification**

1. Log out, log in as Alice (the staff user from Task 3, password `alice123`).
2. Expected: lands on `staff-dashboard.html` with three tabs: Scan QR (active), Vitals Queue, Certificates.
3. Tab switching works visually; the page title and active highlight update.
4. Logout → back to login screen.
5. Log in as a non-staff user (admin) → manually browse to `/pages/staff-dashboard.html` → expected: redirected to `login.html` because the guard checks `role === 'staff'`.

- [ ] **Step 3: Commit**

```bash
git add pages/staff-dashboard.html
git commit -m "feat(staff): dashboard scaffold with role guard and tab nav"
```

---

## Task 7: Staff QR Scan Tab — Reuse Scanner

**Files:**
- Modify: `pages/staff-dashboard.html` (fill in `#tab-scan`)

The existing `pages/qr-checkin.html` and `api/appointments/checkin.php` already implement scanning + check-in for any logged-in user. We embed the scanner into the staff dashboard rather than redirecting.

- [ ] **Step 1: Inspect the existing scanner**

Open `pages/qr-checkin.html` and find:
- The HTML5 QR scanner library include (likely `html5-qrcode`).
- The `<div id="qr-reader">` element and the JS that initializes it.
- The success callback that POSTs to `api/appointments/checkin.php` with `token_hash`.

- [ ] **Step 2: Inline the scanner into `#tab-scan`**

Replace the `<section id="tab-scan" class="tab">` block in `staff-dashboard.html` with:

```html
<section id="tab-scan" class="tab">
  <div class="card">
    <div class="card-header">
      <h2>Scan Patient QR</h2>
      <p class="muted">Point the camera at the patient's QR code to check them in. They will move into the Vitals Queue.</p>
    </div>
    <div id="qr-reader" style="width: 100%; max-width: 480px; margin: 16px auto;"></div>
    <div id="manual-entry" class="manual-entry">
      <details>
        <summary>Camera not working? Enter token manually.</summary>
        <input type="text" id="manual-token" placeholder="Token hash" class="input">
        <button id="manual-submit" class="btn btn-primary">Check in</button>
      </details>
    </div>
    <div id="scan-result" class="scan-result hidden"></div>
  </div>
</section>

<script src="https://unpkg.com/html5-qrcode" defer></script>
```

(Verify the script src matches whatever `qr-checkin.html` already uses — copy that exact line.)

- [ ] **Step 3: Add the scanner JS at the bottom of the page's main `<script>`**

```javascript
let staffScanner = null;
async function startStaffScanner() {
  if (staffScanner) return;
  staffScanner = new Html5Qrcode("qr-reader");
  try {
    await staffScanner.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: { width: 260, height: 260 } },
      onStaffScanSuccess,
      () => {} // ignore decode errors
    );
  } catch (e) {
    console.error(e);
    document.getElementById('manual-entry').open = true;
  }
}

async function onStaffScanSuccess(decodedText) {
  if (staffScanner) { try { await staffScanner.stop(); } catch (_) {} staffScanner = null; }
  let token_hash = decodedText.trim();
  // QR payload may be a JSON string or just the hash, depending on QRCodeGenerator output
  try { const parsed = JSON.parse(token_hash); token_hash = parsed.token_hash || parsed.hash || token_hash; } catch (_) {}
  await checkInToken(token_hash);
  setTimeout(startStaffScanner, 2500);
}

async function checkInToken(token_hash) {
  const result = document.getElementById('scan-result');
  result.classList.remove('hidden', 'success', 'error');
  result.textContent = 'Checking in…';
  try {
    const res = await fetch('../api/appointments/checkin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token_hash }),
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (!data.success) {
      result.classList.add('error');
      result.textContent = '✗ ' + data.message;
      return;
    }
    result.classList.add('success');
    const a = data.appointment;
    result.innerHTML = `✓ Checked in: <strong>${escapeHtml(a.patient_name)}</strong> · ${a.appointment_time} · #${a.appointment_number}`;
  } catch (e) {
    result.classList.add('error');
    result.textContent = '✗ Network error: ' + e.message;
  }
}

document.getElementById('manual-submit').addEventListener('click', () => {
  const t = document.getElementById('manual-token').value.trim();
  if (t) checkInToken(t);
});

window.addEventListener('load', startStaffScanner);
```

(If the `Html5Qrcode` library is loaded elsewhere in the project, match that. The defer + load listener avoids a race where the lib hasn't loaded by the time we initialize.)

- [ ] **Step 4: Manual verification**

1. Have an existing patient with a `scheduled` appointment for today (book one if needed via the patient flow).
2. Log in as Alice → Scan QR tab → grant camera permission.
3. Display the patient's QR (download/print from the patient dashboard or open `qr-booking.html` in another window).
4. Scan → expected: success message; appointment status in DB now `checked_in`; `appointments.checked_in_at` set.
5. Re-scan the same QR → expected: error message ("Appointment cannot be checked in (status: checked_in)").
6. Try the manual-token fallback with the same `token_hash` value.

- [ ] **Step 5: Commit**

```bash
git add pages/staff-dashboard.html
git commit -m "feat(staff): QR scan tab with check-in + manual fallback"
```

---

## Task 8: Staff Vitals API

**Files:**
- Create: `api/staff/queue.php`
- Create: `api/staff/get-vitals.php`
- Create: `api/staff/save-vitals.php`

- [ ] **Step 1: Create `api/staff/queue.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('staff')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT a.id AS appointment_id, a.appointment_number, a.appointment_time, a.checked_in_at,
               p.id AS patient_id, p.full_name AS patient_name, p.date_of_birth, p.gender,
               t.id AS triage_id
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
     LEFT JOIN triage_assessments t ON t.appointment_id = a.id
         WHERE a.appointment_date = CURDATE()
           AND a.status = 'checked_in'
         ORDER BY a.checked_in_at ASC
    ");
    $stmt->execute();
    sendJSON(['success' => true, 'queue' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("staff/queue error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load queue'], 500);
}
```

- [ ] **Step 2: Create `api/staff/get-vitals.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT id, appointment_id, patient_id, chief_complaint, blood_pressure, temperature,
               heart_rate, weight, height_cm, oxygen_saturation, priority_level, notes,
               recorded_by, recorded_at, updated_at
          FROM triage_assessments
         WHERE appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    sendJSON(['success' => true, 'vitals' => $row ?: null]);
} catch (Exception $e) {
    error_log("staff/get-vitals error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load vitals'], 500);
}
```

- [ ] **Step 3: Create `api/staff/save-vitals.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !(hasRole('staff') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input          = json_decode(file_get_contents('php://input'), true);
$appointment_id = (int) ($input['appointment_id'] ?? 0);

if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

$chief_complaint   = sanitizeInput($input['chief_complaint'] ?? '');
$blood_pressure    = sanitizeInput($input['blood_pressure'] ?? '');
$temperature       = $input['temperature'] !== '' && isset($input['temperature']) ? floatval($input['temperature']) : null;
$heart_rate        = $input['heart_rate'] !== '' && isset($input['heart_rate']) ? intval($input['heart_rate']) : null;
$weight            = $input['weight'] !== '' && isset($input['weight']) ? floatval($input['weight']) : null;
$height_cm         = $input['height_cm'] !== '' && isset($input['height_cm']) ? intval($input['height_cm']) : null;
$oxygen_saturation = $input['oxygen_saturation'] !== '' && isset($input['oxygen_saturation']) ? intval($input['oxygen_saturation']) : null;
$notes             = sanitizeInput($input['notes'] ?? '');

if (empty($chief_complaint) || empty($blood_pressure) || $weight === null || $height_cm === null) {
    sendJSON(['success' => false, 'message' => 'Chief complaint, blood pressure, weight, and height are required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    // Verify appointment is checked_in or in_progress; pull patient_id
    $stmt = $db->prepare("SELECT id, patient_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if (!in_array($appt['status'], ['checked_in', 'in_progress'], true)) {
        sendJSON(['success' => false, 'message' => 'Patient must be checked in before recording vitals'], 400);
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO triage_assessments
          (appointment_id, patient_id, chief_complaint, blood_pressure, temperature, heart_rate,
           weight, height_cm, oxygen_saturation, notes, recorded_by)
        VALUES
          (:aid, :pid, :cc, :bp, :temp, :hr, :w, :h, :o2, :notes, :uid)
        ON DUPLICATE KEY UPDATE
          chief_complaint   = VALUES(chief_complaint),
          blood_pressure    = VALUES(blood_pressure),
          temperature       = VALUES(temperature),
          heart_rate        = VALUES(heart_rate),
          weight            = VALUES(weight),
          height_cm         = VALUES(height_cm),
          oxygen_saturation = VALUES(oxygen_saturation),
          notes             = VALUES(notes),
          recorded_by       = VALUES(recorded_by)
    ");
    $stmt->execute([
        ':aid'   => $appointment_id,
        ':pid'   => $appt['patient_id'],
        ':cc'    => $chief_complaint,
        ':bp'    => $blood_pressure,
        ':temp'  => $temperature,
        ':hr'    => $heart_rate,
        ':w'     => $weight,
        ':h'     => $height_cm,
        ':o2'    => $oxygen_saturation,
        ':notes' => $notes ?: null,
        ':uid'   => $userId,
    ]);

    if ($appt['status'] === 'checked_in') {
        $db->prepare("UPDATE appointments SET status = 'in_progress' WHERE id = :aid")
           ->execute([':aid' => $appointment_id]);
    }

    $db->commit();

    logActivity($db, $userId, $_SESSION['username'] ?? '', getCurrentUserRole() ?? 'staff', 'CREATE', 'Triage', $appointment_id, "Vitals recorded for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Vitals saved']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("staff/save-vitals error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to save vitals'], 500);
}
```

- [ ] **Step 4: Manual verification**

1. Log in as Alice. Have at least one appointment in `checked_in` status (use Task 7's flow or manually `UPDATE appointments SET status='checked_in', checked_in_at=NOW() WHERE id=N`).
2. `curl -b "PHPSESSID=<alice-session>" http://localhost/meditrack/api/staff/queue.php` → expected: array containing the checked-in appointment.
3. `curl -b ... -X POST -H "Content-Type: application/json" -d '{"appointment_id":N,"chief_complaint":"Headache","blood_pressure":"120/80","weight":65.5,"height_cm":168,"temperature":37.1,"heart_rate":78,"oxygen_saturation":98,"notes":"alert"}' .../save-vitals.php` → expected: success.
4. In MySQL: `SELECT * FROM triage_assessments WHERE appointment_id=N;` → row exists with all fields. `SELECT status FROM appointments WHERE id=N;` → `in_progress`.
5. Re-POST the same payload → expected: success, no duplicate row (UPDATE path), `status` stays `in_progress`.
6. `curl .../get-vitals.php?appointment_id=N` → returns the stored values.
7. Send a save-vitals request for an appointment in status `scheduled` → expected: 400.
8. Send save-vitals as a patient (not staff/doctor) → expected: 401.

- [ ] **Step 5: Commit**

```bash
git add api/staff/queue.php api/staff/get-vitals.php api/staff/save-vitals.php
git commit -m "feat(staff): vitals queue + save/get vitals endpoints"
```

---

## Task 9: Staff Vitals Tab UI

**Files:**
- Modify: `pages/staff-dashboard.html` (fill in `#tab-vitals`)

- [ ] **Step 1: Add the vitals tab markup**

Replace `<section id="tab-vitals" class="tab hidden">` with:

```html
<section id="tab-vitals" class="tab hidden">
  <div class="card">
    <div class="card-header">
      <h2>Today's Queue</h2>
      <button id="refresh-queue" class="btn btn-ghost"><i class="fa-solid fa-rotate"></i> Refresh</button>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>Time</th><th>Patient</th><th>Appointment #</th><th>Vitals</th><th></th></tr>
      </thead>
      <tbody id="queue-tbody"><tr><td colspan="5">Loading…</td></tr></tbody>
    </table>
  </div>
</section>

<div id="modal-vitals" class="modal hidden">
  <div class="modal-card">
    <h3>Record Vitals — <span id="vitals-patient-name"></span></h3>
    <form id="form-vitals">
      <input type="hidden" name="appointment_id" id="vitals-appt-id">
      <label>Chief complaint <textarea name="chief_complaint" required rows="2"></textarea></label>
      <div class="grid-2">
        <label>Blood pressure <input name="blood_pressure" placeholder="120/80" required></label>
        <label>Temperature (°C) <input name="temperature" type="number" step="0.1"></label>
        <label>Heart rate (bpm) <input name="heart_rate" type="number" step="1"></label>
        <label>O₂ saturation (%) <input name="oxygen_saturation" type="number" step="1" min="0" max="100"></label>
        <label>Weight (kg) <input name="weight" type="number" step="0.1" required></label>
        <label>Height (cm) <input name="height_cm" type="number" step="1" required></label>
      </div>
      <label>Notes <textarea name="notes" rows="2"></textarea></label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save vitals</button>
      </div>
    </form>
  </div>
</div>
```

- [ ] **Step 2: Add the JS for queue + form**

Add to the dashboard's main `<script>`:

```javascript
async function loadVitalsQueue() {
  const tbody = document.getElementById('queue-tbody');
  tbody.innerHTML = '<tr><td colspan="5">Loading…</td></tr>';
  try {
    const res = await fetch('../api/staff/queue.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    if (!data.queue.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#888">No patients waiting. Scan a QR to check someone in.</td></tr>';
      return;
    }
    tbody.innerHTML = data.queue.map(q => `
      <tr>
        <td>${q.appointment_time}</td>
        <td>${escapeHtml(q.patient_name)}</td>
        <td>#${escapeHtml(q.appointment_number)}</td>
        <td>${q.triage_id ? '<span class="status-pill status-active">Recorded</span>' : '<span class="muted">Pending</span>'}</td>
        <td>
          <button class="btn btn-sm" data-vitals-aid="${q.appointment_id}" data-name="${escapeHtml(q.patient_name)}">
            ${q.triage_id ? 'Edit' : 'Record'} vitals
          </button>
        </td>
      </tr>
    `).join('');
    tbody.querySelectorAll('[data-vitals-aid]').forEach(b =>
      b.addEventListener('click', () => openVitalsModal(+b.dataset.vitalsAid, b.dataset.name))
    );
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" style="color:#c00">Failed to load: ${escapeHtml(e.message)}</td></tr>`;
  }
}

async function openVitalsModal(appointment_id, patient_name) {
  const form = document.getElementById('form-vitals');
  form.reset();
  document.getElementById('vitals-appt-id').value = appointment_id;
  document.getElementById('vitals-patient-name').textContent = patient_name;
  // Prefill if vitals already exist
  try {
    const res = await fetch('../api/staff/get-vitals.php?appointment_id=' + appointment_id, { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success && data.vitals) {
      const v = data.vitals;
      form.chief_complaint.value   = v.chief_complaint   ?? '';
      form.blood_pressure.value    = v.blood_pressure    ?? '';
      form.temperature.value       = v.temperature       ?? '';
      form.heart_rate.value        = v.heart_rate        ?? '';
      form.oxygen_saturation.value = v.oxygen_saturation ?? '';
      form.weight.value            = v.weight            ?? '';
      form.height_cm.value         = v.height_cm         ?? '';
      form.notes.value             = v.notes             ?? '';
    }
  } catch (_) {}
  document.getElementById('modal-vitals').classList.remove('hidden');
}

document.querySelectorAll('#modal-vitals [data-close]').forEach(b =>
  b.addEventListener('click', () => document.getElementById('modal-vitals').classList.add('hidden'))
);

document.getElementById('form-vitals').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const body = Object.fromEntries(fd.entries());
  // Coerce numerics
  ['temperature', 'heart_rate', 'oxygen_saturation', 'weight', 'height_cm'].forEach(k => {
    if (body[k] === '') delete body[k];
  });
  body.appointment_id = +body.appointment_id;
  const res = await fetch('../api/staff/save-vitals.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (!data.success) { Swal.fire('Error', data.message, 'error'); return; }
  document.getElementById('modal-vitals').classList.add('hidden');
  Swal.fire('Saved', 'Vitals recorded. Patient is now ready for the doctor.', 'success');
  loadVitalsQueue();
});

document.getElementById('refresh-queue').addEventListener('click', loadVitalsQueue);
```

- [ ] **Step 3: Manual verification**

1. Log in as Alice. Switch to **Vitals Queue** tab. Today's `checked_in` appointment from Task 7's verification appears.
2. Click **Record vitals** → modal opens with empty fields and the patient's name in the heading.
3. Fill the form → submit. Expected: success toast; modal closes; row's "Vitals" cell shows **Recorded**; appointment's status moved to `in_progress` (verify in DB or via re-fetch — once `in_progress`, queue.php no longer returns it, so the row may disappear on refresh, which is correct).
4. Manually `UPDATE appointments SET status='checked_in' WHERE id=N` to put the patient back in the queue, then click **Edit vitals** → modal pre-fills with the saved values.
5. Submit again with different values → expected: row updated, no duplicate.

- [ ] **Step 4: Commit**

```bash
git add pages/staff-dashboard.html
git commit -m "feat(staff): vitals queue + record/edit modal"
```

---

## Task 10: Doctor — Read Vitals from Triage

**Files:**
- Modify: `api/doctor/get-appointments.php`
- Modify: `pages/doctor-dashboard.html`

- [ ] **Step 1: Inspect `api/doctor/get-appointments.php`**

Open the file. Identify the SQL `SELECT` that returns appointments. It almost certainly joins `appointments`, `patients`, and possibly `medical_records`. We extend it with a `LEFT JOIN triage_assessments`.

- [ ] **Step 2: Extend the query**

Add to the SELECT list:

```
t.chief_complaint AS triage_chief_complaint,
t.blood_pressure  AS vital_bp,
t.temperature     AS vital_temp,
t.heart_rate      AS vital_hr,
t.weight          AS vital_weight,
t.height_cm       AS vital_height,
t.oxygen_saturation AS vital_o2,
t.notes           AS triage_notes,
t.recorded_at     AS vitals_recorded_at,
```

Add to the FROM/JOIN:

```sql
LEFT JOIN triage_assessments t ON t.appointment_id = a.id
```

When emitting the response, build a nested `vitals` object on each row:

```php
$row['vitals'] = $row['vital_bp'] === null ? null : [
    'chief_complaint'    => $row['triage_chief_complaint'],
    'blood_pressure'     => $row['vital_bp'],
    'temperature'        => $row['vital_temp'],
    'heart_rate'         => $row['vital_hr'],
    'weight'             => $row['vital_weight'],
    'height_cm'          => $row['vital_height'],
    'oxygen_saturation'  => $row['vital_o2'],
    'notes'              => $row['triage_notes'],
    'recorded_at'        => $row['vitals_recorded_at'],
];
foreach (['triage_chief_complaint','vital_bp','vital_temp','vital_hr','vital_weight','vital_height','vital_o2','triage_notes','vitals_recorded_at'] as $k) {
    unset($row[$k]);
}
```

(Apply this transformation inside the loop that builds the response array.)

- [ ] **Step 3: Add the read-only Vital Signs card to the doctor's medical-record drawer**

In `pages/doctor-dashboard.html`, locate the medical record form (search for `chief_complaint`, `diagnosis`, or `save-medical-record`). Above the first input, insert:

```html
<div class="vitals-card" id="vitals-readout">
  <h4><i class="fa-solid fa-heart-pulse"></i> Vital Signs</h4>
  <div id="vitals-content" class="muted">Loading…</div>
</div>
```

- [ ] **Step 4: Render vitals when the drawer opens**

Inside the JS that opens the medical-record drawer for an appointment (search for the function that populates the form with `appt.chief_complaint`, `appt.diagnosis`, etc.), add at the start:

```javascript
const vitalsContent = document.getElementById('vitals-content');
if (appt.vitals) {
  const v = appt.vitals;
  vitalsContent.innerHTML = `
    <div class="vitals-grid">
      <div><span class="label">Complaint</span><span>${escapeHtml(v.chief_complaint || '—')}</span></div>
      <div><span class="label">BP</span><span>${escapeHtml(v.blood_pressure || '—')}</span></div>
      <div><span class="label">Temp</span><span>${v.temperature ?? '—'} °C</span></div>
      <div><span class="label">HR</span><span>${v.heart_rate ?? '—'} bpm</span></div>
      <div><span class="label">SpO₂</span><span>${v.oxygen_saturation ?? '—'} %</span></div>
      <div><span class="label">Weight</span><span>${v.weight ?? '—'} kg</span></div>
      <div><span class="label">Height</span><span>${v.height_cm ?? '—'} cm</span></div>
    </div>
    <div class="muted small">Recorded ${new Date(v.recorded_at).toLocaleString()}</div>
  `;
} else {
  vitalsContent.innerHTML = `
    <div class="vitals-empty">
      <p>Vitals not recorded yet.</p>
      <button id="doctor-record-vitals" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-heart-pulse"></i> Record vitals
      </button>
    </div>
  `;
  document.getElementById('doctor-record-vitals').addEventListener('click', () => openDoctorVitalsModal(appt.id, appt.patient_name));
}
```

- [ ] **Step 5: Implement the doctor fallback vitals modal**

The doctor can call the same `api/staff/save-vitals.php` endpoint (it accepts `doctor` per Task 8). Add this modal HTML once near the bottom of `pages/doctor-dashboard.html` (just above `</body>`):

```html
<div id="modal-doctor-vitals" class="modal hidden">
  <div class="modal-card">
    <h3>Record Vitals — <span id="doctor-vitals-patient-name"></span></h3>
    <form id="form-doctor-vitals">
      <input type="hidden" name="appointment_id" id="doctor-vitals-appt-id">
      <label>Chief complaint <textarea name="chief_complaint" required rows="2"></textarea></label>
      <div class="grid-2">
        <label>Blood pressure <input name="blood_pressure" placeholder="120/80" required></label>
        <label>Temperature (°C) <input name="temperature" type="number" step="0.1"></label>
        <label>Heart rate (bpm) <input name="heart_rate" type="number" step="1"></label>
        <label>O₂ saturation (%) <input name="oxygen_saturation" type="number" step="1" min="0" max="100"></label>
        <label>Weight (kg) <input name="weight" type="number" step="0.1" required></label>
        <label>Height (cm) <input name="height_cm" type="number" step="1" required></label>
      </div>
      <label>Notes <textarea name="notes" rows="2"></textarea></label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save vitals</button>
      </div>
    </form>
  </div>
</div>
```

Add this JS to the doctor dashboard's main `<script>`:

```javascript
async function openDoctorVitalsModal(appointment_id, patient_name) {
  const form = document.getElementById('form-doctor-vitals');
  form.reset();
  document.getElementById('doctor-vitals-appt-id').value = appointment_id;
  document.getElementById('doctor-vitals-patient-name').textContent = patient_name;
  try {
    const res = await fetch('../api/staff/get-vitals.php?appointment_id=' + appointment_id, { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success && data.vitals) {
      const v = data.vitals;
      form.chief_complaint.value   = v.chief_complaint   ?? '';
      form.blood_pressure.value    = v.blood_pressure    ?? '';
      form.temperature.value       = v.temperature       ?? '';
      form.heart_rate.value        = v.heart_rate        ?? '';
      form.oxygen_saturation.value = v.oxygen_saturation ?? '';
      form.weight.value            = v.weight            ?? '';
      form.height_cm.value         = v.height_cm         ?? '';
      form.notes.value             = v.notes             ?? '';
    }
  } catch (_) {}
  document.getElementById('modal-doctor-vitals').classList.remove('hidden');
}

document.querySelectorAll('#modal-doctor-vitals [data-close]').forEach(b =>
  b.addEventListener('click', () => document.getElementById('modal-doctor-vitals').classList.add('hidden'))
);

document.getElementById('form-doctor-vitals').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const body = Object.fromEntries(fd.entries());
  ['temperature', 'heart_rate', 'oxygen_saturation', 'weight', 'height_cm'].forEach(k => {
    if (body[k] === '') delete body[k];
  });
  body.appointment_id = +body.appointment_id;
  const res = await fetch('../api/staff/save-vitals.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (!data.success) { Swal.fire('Error', data.message, 'error'); return; }
  document.getElementById('modal-doctor-vitals').classList.add('hidden');
  Swal.fire('Saved', 'Vitals recorded.', 'success');
  // Re-open the appointment drawer so the vitals card refreshes from the server.
  if (typeof loadDoctorAppointments === 'function') loadDoctorAppointments();
  if (typeof openAppointmentDrawer === 'function') openAppointmentDrawer(body.appointment_id);
});
```

If the doctor dashboard's appointments-loading function has a different name, replace `loadDoctorAppointments` and `openAppointmentDrawer` with the actual names from the existing file. The pattern is: refresh the source list, then re-open the drawer for the current appointment so the vitals card shows the new values.

- [ ] **Step 6: Add minimal CSS** (inline `<style>` or in the doctor dashboard's existing style block)

```css
.vitals-card { background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:12px 16px; margin:0 0 16px; }
.vitals-card h4 { margin:0 0 8px; font-size:14px; color:#0f172a; }
.vitals-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:8px 16px; }
.vitals-grid .label { display:block; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.vitals-grid > div > span:not(.label) { font-weight:600; color:#0f172a; }
.vitals-empty p { margin:0 0 8px; color:#64748b; }
.muted.small { margin-top:8px; font-size:11px; }
```

- [ ] **Step 7: Manual verification**

1. Have an `in_progress` appointment with vitals recorded by Alice.
2. Log in as the doctor, open the appointment drawer. Expected: vitals card shows recorded values + "Recorded {timestamp}".
3. Have a `checked_in` appointment with **no** vitals recorded. Open it (the doctor can still see it in the queue). Expected: empty-state with "Record vitals" button. Click → modal opens → fill + save → vitals card refreshes with the values.
4. Verify in MySQL: the doctor's save wrote to `triage_assessments` (recorded_by = doctor's user id), not to `medical_records.vital_signs`.

- [ ] **Step 8: Commit**

```bash
git add api/doctor/get-appointments.php pages/doctor-dashboard.html
git commit -m "feat(doctor): read-only vitals card with staff-flow fallback"
```

---

## Task 11: Doctor — Drop Vitals & Chief Complaint from Save

**Files:**
- Modify: `api/doctor/save-medical-record.php`
- Modify: `pages/doctor-dashboard.html` (medical record form fields)

- [ ] **Step 1: Update `save-medical-record.php`**

Open the file. Apply two changes:

1. **Drop `chief_complaint` from input/binding.** Remove the `$chief_complaint = sanitizeInput($input['chief_complaint'] ?? '');` line (line ~45 in the current file). In the SQL, drop the `chief_complaint` column from the INSERT and from the `ON DUPLICATE KEY UPDATE` list. Drop the `:cc` binding.

2. **Drop the `vital_signs` JSON build** (lines ~53–61). Drop `vital_signs` from the INSERT column list and from the ON DUPLICATE update. Drop the `:vs` binding.

Resulting SQL:

```sql
INSERT INTO medical_records
    (appointment_id, patient_id, doctor_id, symptoms, diagnosis, prescription, lab_tests_ordered, notes, follow_up_date)
VALUES
    (:aid, :pid, :did, :sym, :diag, :rx, :lab, :notes, :fud)
ON DUPLICATE KEY UPDATE
    symptoms           = VALUES(symptoms),
    diagnosis          = VALUES(diagnosis),
    prescription       = VALUES(prescription),
    lab_tests_ordered  = VALUES(lab_tests_ordered),
    notes              = VALUES(notes),
    follow_up_date     = VALUES(follow_up_date)
```

Resulting `execute(...)` array — drop `:cc` and `:vs`:

```php
[
    ':aid'   => $appointment_id,
    ':pid'   => $patient_id,
    ':did'   => $doctor_id,
    ':sym'   => $symptoms,
    ':diag'  => $diagnosis,
    ':rx'    => $prescription,
    ':lab'   => $lab_tests_ordered,
    ':notes' => $notes,
    ':fud'   => $follow_up_date
]
```

If the request body still contains `chief_complaint` or `vital_signs` (older client), they are silently ignored — do not reject.

- [ ] **Step 2: Remove vitals + chief-complaint inputs from the doctor's form**

In `pages/doctor-dashboard.html`, locate the medical record form. Remove:
- The `chief_complaint` `<textarea>` / `<input>` element.
- All vitals inputs: `bp`, `temperature`, `heart_rate`, `weight`, `height` fields.

Adjust labels/spacing so the form starts directly with **Symptoms** (the first remaining field). The vitals card from Task 10 sits **above** the form and shows chief complaint as part of its readout.

- [ ] **Step 3: Manual verification**

1. As doctor, open an `in_progress` appointment with vitals recorded.
2. The medical-record form should start with **Symptoms** (no chief complaint or vitals inputs).
3. Fill diagnosis + prescription + symptoms → save. Expected: success.
4. Verify in MySQL: `SELECT chief_complaint, vital_signs FROM medical_records WHERE appointment_id=N;` → both are `NULL` for new rows.
5. The triage row for this appointment is unchanged (`SELECT chief_complaint FROM triage_assessments WHERE appointment_id=N;` still has the staff-entered value).
6. Status moves to `completed` as before.

- [ ] **Step 4: Commit**

```bash
git add api/doctor/save-medical-record.php pages/doctor-dashboard.html
git commit -m "refactor(doctor): drop vitals + chief_complaint from save form (sourced from triage)"
```

---

## Task 12: Medical Certificate API

**Files:**
- Create: `api/staff/issue-certificate.php`
- Create: `api/staff/certificate.php`
- Create: `api/staff/certificates.php`

- [ ] **Step 1: Create `api/staff/issue-certificate.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('staff')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input              = json_decode(file_get_contents('php://input'), true);
$appointment_id     = (int) ($input['appointment_id'] ?? 0);
$diagnosis          = sanitizeInput($input['diagnosis'] ?? '');
$rest_period_start  = sanitizeInput($input['rest_period_start'] ?? '');
$rest_period_end    = sanitizeInput($input['rest_period_end'] ?? '');
$rest_days_input    = $input['rest_days'] ?? null;
$notes              = sanitizeInput($input['notes'] ?? '');

if (!$appointment_id || empty($diagnosis) || empty($rest_period_start) || empty($rest_period_end)) {
    sendJSON(['success' => false, 'message' => 'appointment_id, diagnosis, rest_period_start, rest_period_end are required'], 400);
}

$startTs = strtotime($rest_period_start);
$endTs   = strtotime($rest_period_end);
if ($startTs === false || $endTs === false || $endTs < $startTs) {
    sendJSON(['success' => false, 'message' => 'Invalid rest period (end must be on or after start)'], 400);
}

$computedDays = (int) floor(($endTs - $startTs) / 86400) + 1;
$rest_days    = is_numeric($rest_days_input) && (int) $rest_days_input > 0 ? (int) $rest_days_input : $computedDays;

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id, patient_id, doctor_id, status FROM appointments WHERE id = :aid LIMIT 1");
    $stmt->execute([':aid' => $appointment_id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }
    if ($appt['status'] !== 'completed') {
        sendJSON(['success' => false, 'message' => 'Doctor must complete the medical record before a certificate can be issued'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO medical_certificates
          (appointment_id, patient_id, doctor_id, issued_by_user_id, diagnosis, rest_period_start, rest_period_end, rest_days, notes)
        VALUES
          (:aid, :pid, :did, :uid, :diag, :rs, :re, :rd, :notes)
        ON DUPLICATE KEY UPDATE
          diagnosis         = VALUES(diagnosis),
          rest_period_start = VALUES(rest_period_start),
          rest_period_end   = VALUES(rest_period_end),
          rest_days         = VALUES(rest_days),
          notes             = VALUES(notes),
          issued_by_user_id = VALUES(issued_by_user_id),
          issued_at         = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':aid'   => $appointment_id,
        ':pid'   => $appt['patient_id'],
        ':did'   => $appt['doctor_id'],
        ':uid'   => $userId,
        ':diag'  => $diagnosis,
        ':rs'    => $rest_period_start,
        ':re'    => $rest_period_end,
        ':rd'    => $rest_days,
        ':notes' => $notes ?: null,
    ]);

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'staff', 'CREATE', 'MedicalCertificates', $appointment_id, "Cert issued for appointment #$appointment_id");

    sendJSON(['success' => true, 'message' => 'Certificate issued', 'rest_days' => $rest_days]);
} catch (Exception $e) {
    error_log("staff/issue-certificate error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to issue certificate'], 500);
}
```

- [ ] **Step 2: Create `api/staff/certificate.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('admin') || hasRole('doctor'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$appointment_id = (int) ($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    sendJSON(['success' => false, 'message' => 'appointment_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT mc.*,
               p.full_name AS patient_name, p.date_of_birth, p.gender, p.address, p.contact_number AS patient_contact,
               d.full_name AS doctor_name, d.license_number, d.specialization,
               u.username AS issued_by_username,
               sp.full_name AS issued_by_full_name,
               a.appointment_number, a.appointment_date
          FROM medical_certificates mc
          JOIN patients p ON p.id = mc.patient_id
          JOIN doctors d ON d.id = mc.doctor_id
          JOIN users u ON u.id = mc.issued_by_user_id
     LEFT JOIN staff_profiles sp ON sp.user_id = u.id
          JOIN appointments a ON a.id = mc.appointment_id
         WHERE mc.appointment_id = :aid
         LIMIT 1
    ");
    $stmt->execute([':aid' => $appointment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        sendJSON(['success' => false, 'message' => 'Certificate not found'], 404);
    }
    sendJSON(['success' => true, 'certificate' => $row]);
} catch (Exception $e) {
    error_log("staff/certificate error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load certificate'], 500);
}
```

- [ ] **Step 3: Create `api/staff/certificates.php`**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('staff') || hasRole('admin'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$from = sanitizeInput($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$to   = sanitizeInput($_GET['to']   ?? date('Y-m-d'));

try {
    $db = (new Database())->getConnection();

    // Eligible-but-not-yet-certified appointments: status=completed, no cert row.
    $stmt = $db->prepare("
        SELECT a.id AS appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               p.full_name AS patient_name,
               d.full_name AS doctor_name,
               mc.id AS cert_id, mc.diagnosis AS cert_diagnosis, mc.rest_days AS cert_rest_days,
               mc.issued_at AS cert_issued_at,
               mr.diagnosis AS record_diagnosis
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          JOIN doctors d ON d.id = a.doctor_id
     LEFT JOIN medical_certificates mc ON mc.appointment_id = a.id
     LEFT JOIN medical_records mr ON mr.appointment_id = a.id
         WHERE a.status = 'completed'
           AND a.appointment_date BETWEEN :from AND :to
         ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    sendJSON(['success' => true, 'rows' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log("staff/certificates error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load certificates'], 500);
}
```

- [ ] **Step 4: Manual verification**

1. Have a `completed` appointment (use existing flow: scheduled → checked_in → in_progress → doctor saves record → completed). Note its id.
2. As Alice (staff), POST to issue-certificate:
   ```bash
   curl -X POST -b "PHPSESSID=<alice>" -H "Content-Type: application/json" \
     -d '{"appointment_id":N,"diagnosis":"URTI","rest_period_start":"2026-05-06","rest_period_end":"2026-05-09"}' \
     http://localhost/meditrack/api/staff/issue-certificate.php
   ```
   Expected: success, `rest_days: 4`.
3. Re-POST with `rest_period_end: '2026-05-08'` → expected: success, `rest_days: 3`. DB row updated, not duplicated.
4. POST with end < start → expected: 400.
5. POST against an `in_progress` appointment → expected: 400.
6. GET `certificate.php?appointment_id=N` → returns the row with joined patient/doctor/issuer data.
7. GET `certificates.php` → returns list including this appointment.

- [ ] **Step 5: Commit**

```bash
git add api/staff/issue-certificate.php api/staff/certificate.php api/staff/certificates.php
git commit -m "feat(staff): medical certificate issue/read/list endpoints"
```

---

## Task 13: Cert UI — Staff Tab + Print Page

**Files:**
- Create: `pages/print-certificate.html`
- Modify: `pages/staff-dashboard.html` (fill in `#tab-certs`)

- [ ] **Step 1: Build `pages/print-certificate.html`**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Medical Certificate</title>
  <link rel="icon" type="image/png" href="../assets/images/medicare.png">
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: 'Times New Roman', serif; color:#111; max-width: 720px; margin:0 auto; padding:24px; }
    .letterhead { display:flex; align-items:center; gap:16px; border-bottom:2px solid #0f172a; padding-bottom:12px; }
    .letterhead img { width:64px; height:64px; }
    .letterhead h1 { margin:0; font-size:20px; }
    .letterhead p { margin:2px 0; font-size:12px; color:#475569; }
    h2.title { text-align:center; letter-spacing:2px; margin:32px 0 24px; }
    .body p { line-height:1.7; margin:8px 0; text-align:justify; }
    .signatures { display:grid; grid-template-columns:1fr 1fr; gap:48px; margin-top:64px; }
    .sig { text-align:center; }
    .sig-line { border-top:1px solid #111; margin-top:48px; padding-top:4px; font-size:13px; }
    .meta { margin-top:24px; font-size:11px; color:#64748b; }
    .actions { text-align:center; margin:24px 0; }
    .actions button { padding:8px 16px; border:1px solid #0f172a; background:#0f172a; color:#fff; border-radius:6px; cursor:pointer; }
    @media print { .actions { display:none; } }
  </style>
</head>
<body>
  <div class="actions">
    <button onclick="window.print()"><i></i>Print</button>
    <button onclick="history.back()" style="background:#fff;color:#0f172a;margin-left:8px">Back</button>
  </div>

  <div class="letterhead">
    <img src="../assets/images/medical_certifcate_logo.jpg" alt="Clinic logo">
    <div>
      <h1>Internal Medicine OPD</h1>
      <p>Consultation Clinic · Medical Certificate</p>
    </div>
  </div>

  <h2 class="title">MEDICAL CERTIFICATE</h2>

  <div class="body" id="cert-body">
    Loading…
  </div>

  <p class="meta" id="cert-meta"></p>

  <script>
    function escapeHtml(s) {
      return String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function ageFromDob(dob) {
      if (!dob) return '';
      const d = new Date(dob), now = new Date();
      let a = now.getFullYear() - d.getFullYear();
      if (now.getMonth() < d.getMonth() || (now.getMonth() === d.getMonth() && now.getDate() < d.getDate())) a--;
      return a;
    }
    (async function () {
      const params = new URLSearchParams(location.search);
      const aid = params.get('appointment_id');
      if (!aid) { document.getElementById('cert-body').textContent = 'Missing appointment_id.'; return; }
      const res = await fetch('../api/staff/certificate.php?appointment_id=' + aid, { credentials: 'same-origin' });
      const data = await res.json();
      const body = document.getElementById('cert-body');
      if (!data.success) { body.textContent = data.message; return; }
      const c = data.certificate;
      const age = ageFromDob(c.date_of_birth);
      const issuer = c.issued_by_full_name || c.issued_by_username;
      body.innerHTML = `
        <p>To Whom It May Concern,</p>
        <p>This is to certify that <strong>${escapeHtml(c.patient_name)}</strong>${age ? `, ${age} years old` : ''}${c.gender ? `, ${escapeHtml(c.gender)}` : ''}, was examined and diagnosed with <strong>${escapeHtml(c.diagnosis)}</strong> on ${new Date(c.appointment_date).toLocaleDateString()}.</p>
        <p>The patient is advised to rest from <strong>${new Date(c.rest_period_start).toLocaleDateString()}</strong> to <strong>${new Date(c.rest_period_end).toLocaleDateString()}</strong>, a total of <strong>${c.rest_days}</strong> day(s).</p>
        ${c.notes ? `<p>${escapeHtml(c.notes)}</p>` : ''}
        <p>Issued upon the request of the patient for whatever legal purpose it may serve.</p>
        <div class="signatures">
          <div class="sig"><div class="sig-line">${escapeHtml(c.doctor_name)}, M.D.</div><div>License: ${escapeHtml(c.license_number || '—')}</div></div>
          <div class="sig"><div class="sig-line">${escapeHtml(issuer)}</div><div>Issuing Staff</div></div>
        </div>
      `;
      document.getElementById('cert-meta').textContent =
        `Certificate #${c.id} · Appointment #${c.appointment_number} · Issued ${new Date(c.issued_at).toLocaleString()}`;
    })();
  </script>
</body>
</html>
```

- [ ] **Step 2: Fill in the staff Certificates tab**

In `pages/staff-dashboard.html`, replace `<section id="tab-certs" class="tab hidden">` with:

```html
<section id="tab-certs" class="tab hidden">
  <div class="card">
    <div class="card-header">
      <h2>Medical Certificates</h2>
      <div class="filters">
        <label>From <input type="date" id="cert-from"></label>
        <label>To <input type="date" id="cert-to"></label>
        <button id="cert-refresh" class="btn btn-ghost"><i class="fa-solid fa-rotate"></i></button>
      </div>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Patient</th><th>Appointment #</th><th>Doctor</th><th>Status</th><th></th></tr>
      </thead>
      <tbody id="cert-tbody"><tr><td colspan="6">Loading…</td></tr></tbody>
    </table>
  </div>
</section>

<div id="modal-cert" class="modal hidden">
  <div class="modal-card">
    <h3>Issue Medical Certificate — <span id="cert-patient-name"></span></h3>
    <form id="form-cert">
      <input type="hidden" name="appointment_id" id="cert-appt-id">
      <label>Diagnosis <textarea name="diagnosis" required rows="2"></textarea></label>
      <div class="grid-2">
        <label>Rest period start <input type="date" name="rest_period_start" required></label>
        <label>Rest period end <input type="date" name="rest_period_end" required></label>
      </div>
      <label>Rest days <span class="hint">(auto-computed; editable)</span> <input type="number" name="rest_days" min="0" step="1"></label>
      <label>Notes <textarea name="notes" rows="2"></textarea></label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Issue certificate</button>
      </div>
    </form>
  </div>
</div>
```

- [ ] **Step 3: Add the JS for the cert tab**

```javascript
async function loadCertList() {
  const tbody = document.getElementById('cert-tbody');
  tbody.innerHTML = '<tr><td colspan="6">Loading…</td></tr>';
  const from = document.getElementById('cert-from').value;
  const to   = document.getElementById('cert-to').value;
  const qs   = new URLSearchParams();
  if (from) qs.set('from', from);
  if (to)   qs.set('to', to);
  try {
    const res = await fetch('../api/staff/certificates.php?' + qs.toString(), { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    if (!data.rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#888">No completed appointments in this date range.</td></tr>';
      return;
    }
    tbody.innerHTML = data.rows.map(r => {
      const issued = !!r.cert_id;
      return `
        <tr>
          <td>${new Date(r.appointment_date).toLocaleDateString()} ${r.appointment_time}</td>
          <td>${escapeHtml(r.patient_name)}</td>
          <td>#${escapeHtml(r.appointment_number)}</td>
          <td>${escapeHtml(r.doctor_name)}</td>
          <td>${issued ? '<span class="status-pill status-active">Issued</span>' : '<span class="muted">Not issued</span>'}</td>
          <td>
            <button class="btn btn-sm" data-issue-aid="${r.appointment_id}" data-name="${escapeHtml(r.patient_name)}" data-diag="${escapeHtml(r.cert_diagnosis || r.record_diagnosis || '')}">${issued ? 'Edit' : 'Issue'}</button>
            ${issued ? `<a class="btn btn-sm btn-ghost" target="_blank" href="print-certificate.html?appointment_id=${r.appointment_id}">Print</a>` : ''}
          </td>
        </tr>
      `;
    }).join('');
    tbody.querySelectorAll('[data-issue-aid]').forEach(b =>
      b.addEventListener('click', () => openCertModal(+b.dataset.issueAid, b.dataset.name, b.dataset.diag))
    );
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="6" style="color:#c00">Failed to load: ${escapeHtml(e.message)}</td></tr>`;
  }
}

function openCertModal(appointment_id, patient_name, prefilled_diagnosis) {
  const form = document.getElementById('form-cert');
  form.reset();
  document.getElementById('cert-appt-id').value = appointment_id;
  document.getElementById('cert-patient-name').textContent = patient_name;
  form.diagnosis.value = prefilled_diagnosis || '';
  // Default rest period: today → today+1
  const today = new Date().toISOString().slice(0, 10);
  const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  form.rest_period_start.value = today;
  form.rest_period_end.value   = tomorrow;
  form.rest_days.value         = 2;
  document.getElementById('modal-cert').classList.remove('hidden');
}

document.querySelectorAll('#modal-cert [data-close]').forEach(b =>
  b.addEventListener('click', () => document.getElementById('modal-cert').classList.add('hidden'))
);

document.getElementById('form-cert').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const body = Object.fromEntries(fd.entries());
  body.appointment_id = +body.appointment_id;
  if (body.rest_days === '') delete body.rest_days; else body.rest_days = +body.rest_days;
  const res = await fetch('../api/staff/issue-certificate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (!data.success) { Swal.fire('Error', data.message, 'error'); return; }
  document.getElementById('modal-cert').classList.add('hidden');
  Swal.fire({
    title: 'Certificate ready',
    text: 'Open the print view now?',
    icon: 'success',
    showCancelButton: true,
    confirmButtonText: 'Print',
    cancelButtonText: 'Later'
  }).then(r => {
    if (r.isConfirmed) window.open('print-certificate.html?appointment_id=' + body.appointment_id, '_blank');
  });
  loadCertList();
});

// Date filters auto-reload
document.getElementById('cert-from').addEventListener('change', loadCertList);
document.getElementById('cert-to').addEventListener('change', loadCertList);
document.getElementById('cert-refresh').addEventListener('click', loadCertList);
```

- [ ] **Step 4: Manual verification**

1. As Alice, switch to **Certificates** tab. List populates with completed appointments from the last 30 days.
2. Click **Issue** on one → modal opens; diagnosis pre-filled from the doctor's record. Adjust rest period → submit.
3. SweetAlert offers to print → click **Print** → `print-certificate.html?appointment_id=N` opens in a new tab. Letterhead, patient name, diagnosis, rest period, doctor signature, issuer name all render. Browser print preview matches.
4. Back in the cert list, the row now shows **Issued** + a **Print** button. Click the print button → same printable cert.
5. Click **Edit** on an issued cert → modal pre-fills via the `cert_diagnosis` value; submit a change → cert row updated, list refreshes.
6. As a non-staff user, manually browse to `print-certificate.html?appointment_id=N`. Doctor and admin can read it (per `certificate.php` auth). Patient cannot via this endpoint for v1 (out of scope).

- [ ] **Step 5: Commit**

```bash
git add pages/print-certificate.html pages/staff-dashboard.html
git commit -m "feat(staff): certificate issuance UI + printable certificate page"
```

---

## End-to-End Verification

After completing all 13 tasks, perform a full workflow walkthrough as a final smoke test:

1. **Patient** books an appointment via the existing flow. QR code generated.
2. **Staff** (Alice) logs in → Scan QR tab → scans the patient's QR → check-in confirmed.
3. **Staff** Vitals Queue → Record vitals (BP, weight, height, …) → save. Patient moves to `in_progress`.
4. **Doctor** logs in → opens the patient's appointment → sees vitals card with all values + chief complaint read-only. Doctor records symptoms / diagnosis / prescription / follow-up date → saves. Patient moves to `completed`.
5. **Patient** returns to **staff** asking for a cert. Alice → Certificates tab → finds the row → Issue → diagnosis pre-filled from doctor's note → adjusts rest period → submit → opens print-certificate.html → prints.
6. **Patient** dashboard shows the appointment as completed. (Patient-side cert visibility is out of scope for C1.)

If all six steps pass without manual DB intervention, C1 is complete.

---

## Self-Review Checklist (against spec)

- [x] **Spec Feature 1 — `staff` role:** Tasks 1, 2, 3, 4, 5 (migration, login redirect, add-staff, list/status, admin UI).
- [x] **Spec Feature 2 — Vitals capture:** Tasks 1 (alter triage_assessments), 6 (dashboard scaffold), 7 (QR scan), 8 (vitals API), 9 (vitals UI).
- [x] **Spec Feature 3 — Medical certificate:** Tasks 1 (table), 12 (API), 13 (UI + print page).
- [x] **Spec Feature 4 — Doctor form cleanup:** Tasks 10 (read vitals + UI card), 11 (drop vitals + chief_complaint write).
- [x] **Doctor fallback** when vitals missing: Task 10 step 5.
- [x] **No automated tests** — verification is manual XAMPP per project convention.
- [x] **Idempotent migration:** Task 1 step 5.
- [x] **Cert per appointment uniqueness:** enforced by UNIQUE KEY in Task 1; verified in Task 12 step 4.

## Open Questions

None.
