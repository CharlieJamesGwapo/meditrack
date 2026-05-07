# Batch C1 — Staff Role, Vitals Capture, Medical Certificate

**Date:** 2026-05-06
**Status:** Draft (pending user review)
**Scope:** Add a dedicated `staff` user role; move vitals capture from the doctor's form into a staff-owned step that runs between QR check-in and the doctor consultation; introduce a staff-issued medical certificate.

This is the first of three sub-specs under **Batch C — System Workflow Alignment**, written to bring the codebase in line with the documented clinic workflow (patient → staff vitals → doctor → staff cert).

## Goals

1. Introduce a real `staff` role distinct from `admin` and `doctor`, so the people who triage and issue certificates have their own login and dashboard.
2. Move vital-sign capture out of the doctor's medical-record form into a staff step that runs immediately after QR check-in.
3. Make vitals visible to the doctor (read-only) before the consultation.
4. Allow staff to issue a printable medical certificate for any completed appointment.
5. Keep the doctor's medical-record form focused on clinical content (chief complaint, symptoms, diagnosis, prescription, lab tests, notes, follow-up).

## Non-Goals

- Replacing the existing `admin` role or migrating any admin-only features.
- E-signed or digitally distributed certificates (print-only for v1).
- Specialist referrals (covered by Batch C3).
- Cancellation broadcast (covered by Batch C2).
- Auto-creating follow-up appointments (covered by Batch C3).
- Multi-clinic / multi-letterhead support.

## Feature 1 — Add `staff` Role

### Requirements

- `users.role` ENUM extended to `'patient', 'doctor', 'admin', 'staff'`.
- Login flow recognizes `staff` and routes to a new `pages/staff-dashboard.html`.
- `hasRole('staff')` works the same as the existing role helpers in `config/config.php`.
- Admin can create a staff account from the admin dashboard (same UX as doctor creation, minus license/specialization fields).
- Activity logging treats `staff` as a first-class `user_role` value, same as the others.

### Backend

- `database/migrations/2026-05-06-batch-c1.sql` runs `ALTER TABLE users MODIFY COLUMN role ENUM('patient','doctor','admin','staff') NOT NULL;`.
- New endpoint `api/admin/add-staff.php` mirrors `add-doctor.php` but inserts only into `users` (no `staff_profiles` table needed for v1; full name is stored in `users.username` display already, plus a small profile column set, see below).
- Add a lightweight `staff_profiles` table for full name and contact, since `users` only carries email/username:
  ```sql
  CREATE TABLE staff_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );
  ```
- Login handler (`api/auth/login.php`) returns the `role` as before; frontend reads it and redirects accordingly.

### Frontend

- New page `pages/staff-dashboard.html` (see Feature 2 and Feature 3 for content).
- `pages/login.html` redirect map: `staff → staff-dashboard.html`.
- `pages/admin-dashboard.html` adds a "Staff" management section alongside "Doctors": list, add, deactivate.

## Feature 2 — Staff Vitals Capture

### Requirements

- Staff scans the patient's QR (reusing existing scanner in `qr-checkin.html` logic) to perform check-in.
- After check-in, the staff dashboard shows a queue of `checked_in` appointments awaiting vitals.
- Staff opens a vitals form for one patient at a time and records:
  - Blood pressure (systolic / diastolic, free-text in `"120/80"` format for v1)
  - Height (cm, integer)
  - Weight (kg, one decimal)
  - Temperature (°C, one decimal) — optional
  - Heart rate (bpm) — optional
  - Oxygen saturation (%) — optional
  - Notes (free text) — optional
- On save, the appointment moves to `in_progress` (staff has finished prep; doctor will pick it up) and a row is written to `triage_assessments`.
- Doctor's appointment screen displays vitals read-only at the top of the medical-record form.
- If vitals are missing when the doctor opens the record, the doctor sees: *"Vitals not recorded yet."* with a "Record vitals" button that opens the same form (doctor fallback). Writes go through the same endpoint and look identical in the database.

### Data Model

Extend the existing `triage_assessments` table:

```sql
ALTER TABLE triage_assessments
  ADD COLUMN appointment_id INT NULL AFTER patient_id,
  ADD COLUMN height_cm INT NULL AFTER weight,
  ADD COLUMN oxygen_saturation TINYINT UNSIGNED NULL AFTER height_cm,
  ADD CONSTRAINT fk_triage_appointment
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  ADD UNIQUE KEY uniq_triage_appointment (appointment_id);
```

`appointment_id` is `NULL`-able only to preserve any existing seeded rows; new writes always set it. The `UNIQUE` index keeps one vitals row per appointment.

The `chief_complaint` and `priority_level` columns already on `triage_assessments` continue to exist and are reused — staff captures the chief complaint here too (no longer captured first by the doctor).

The `medical_records.vital_signs` JSON column is **not dropped** (it has live data per the seed file and possibly production rows). It becomes read-only legacy: existing rows are still displayed for historical visits, but new writes from `save-medical-record.php` no longer populate it (see Feature 4).

### API

- New: `POST api/staff/save-vitals.php`
  - Auth: `staff` or `doctor`.
  - Body:
    ```json
    {
      "appointment_id": 123,
      "chief_complaint": "Headache",
      "blood_pressure": "120/80",
      "height_cm": 168,
      "weight": 65.2,
      "temperature": 37.1,
      "heart_rate": 78,
      "oxygen_saturation": 98,
      "notes": ""
    }
    ```
  - Behavior: upsert into `triage_assessments` keyed by `appointment_id`. Set appointment status to `in_progress` if currently `checked_in`. Idempotent on re-save.
- New: `GET api/staff/queue.php` — returns checked-in appointments for today awaiting vitals (status = `checked_in`).
- New: `GET api/staff/get-vitals.php?appointment_id=N` — fetch existing vitals for prefill.
- Doctor side: `api/doctor/get-appointments.php` is extended to include `vitals` (the `triage_assessments` row joined in) for each appointment in its response.

### Frontend

- New: `pages/staff-dashboard.html` with three tabs:
  1. **Scan QR** — reuses existing scanner component to check patients in.
  2. **Vitals queue** — list of `checked_in` patients today; clicking opens the vitals form modal.
  3. **Certificates** — see Feature 3.
- `pages/doctor-dashboard.html`: medical-record form gains a top read-only **Vital Signs** card showing all fields, with the "Record vitals" fallback button when empty.

## Feature 3 — Staff-Issued Medical Certificate

### Requirements

- Staff issues a certificate for any appointment whose status is `completed` (i.e., the doctor has finished and saved the medical record).
- Certificate inputs:
  - Diagnosis (pre-filled from `medical_records.diagnosis`, editable)
  - Rest period start (date, default = appointment date)
  - Rest period end (date)
  - Rest days (auto-computed from start/end; editable; integer ≥ 0)
  - Notes (free text, optional)
- On submit, a row is written to a new `medical_certificates` table and a printable view opens.
- The printed cert uses the existing letterhead from `pages/print-record.html` and the `medical_certifcate_logo.jpg` asset.
- The cert displays: clinic letterhead; patient full name, age (computed from DOB), sex; diagnosis; rest period and rest days; doctor's name + license; issuing staff name; date issued; signature lines.
- Staff can re-print an existing cert (same row, no new insert) from the certificates tab.

### Data Model

```sql
CREATE TABLE medical_certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  issued_by_user_id INT NOT NULL,        -- staff user id
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

`UNIQUE` on `appointment_id` enforces one cert per visit; updates overwrite via a separate "edit cert" flow if needed (out of scope for v1 — staff who fat-fingers a cert simply re-issues by editing).

### API

- `POST api/staff/issue-certificate.php` — creates or updates the cert row for `appointment_id`.
- `GET api/staff/certificate.php?appointment_id=N` — fetches cert + joined patient/doctor/appointment data for printing.
- `GET api/staff/certificates.php?from=YYYY-MM-DD&to=YYYY-MM-DD` — list view for the certificates tab.

### Frontend

- New: `pages/print-certificate.html` — print-only layout with letterhead, single-page, embedded styles.
- Staff dashboard certificates tab: searchable list of completed appointments (last 30 days by default) with two actions per row: **Issue cert** (opens form) or **Print** (opens print-certificate.html if cert already exists).

## Feature 4 — Doctor Form Cleanup

### Requirements

- Remove vitals fields from the doctor's medical-record save form. The doctor screen still **displays** vitals (from `triage_assessments`) read-only at the top, with the "Record vitals" fallback when empty.
- `api/doctor/save-medical-record.php` no longer accepts or writes `vital_signs`. The backend silently ignores any `vital_signs` field in the request body to keep older clients functional during deploy.
- `chief_complaint` capture is moved to the staff vitals form (since vitals + complaint are now collected at triage). The doctor's form still **displays** chief complaint read-only and may add to it via the symptoms or notes fields.
- All other fields on the doctor's form (`symptoms`, `diagnosis`, `prescription`, `lab_tests_ordered`, `notes`, `follow_up_date`) are unchanged.

### Backend

- `api/doctor/save-medical-record.php`: drop the `$vital_signs` JSON build and remove `vital_signs` from the INSERT/UPDATE column list. Also drop `chief_complaint` from the writeable fields (read-only from triage).
- `api/doctor/get-appointments.php`: include `vitals` and `chief_complaint` from the joined `triage_assessments` row.

### Frontend

- `pages/doctor-dashboard.html`: medical record form section refactored — vitals card on top (read-only), chief complaint shown read-only above symptoms input.

## Database Changes Summary

```sql
-- 2026-05-06-batch-c1.sql

ALTER TABLE users
  MODIFY COLUMN role ENUM('patient','doctor','admin','staff') NOT NULL;

CREATE TABLE staff_profiles ( ... );             -- see Feature 1
ALTER TABLE triage_assessments ... ;             -- see Feature 2
CREATE TABLE medical_certificates ( ... );       -- see Feature 3
```

`database/schema.sql` is updated to reflect the new shape so a fresh install matches.

## Error Handling & Edge Cases

- **Staff records vitals before patient is checked in:** reject with 400 — staff must scan QR (which sets `checked_in`) first.
- **Patient checks out without vitals:** doctor sees the empty card with the fallback button. The system does not block the doctor.
- **Cert issued before record saved:** rejected with 400 message: *"Doctor must complete the medical record before a certificate can be issued."*
- **Cert with end date < start date:** rejected with 400 client-side and server-side.
- **Rest days vs. computed range mismatch:** server recomputes `rest_days = DATEDIFF(end, start) + 1` if zero or negative; otherwise trusts the input.
- **Staff tries to access doctor APIs (or vice versa):** existing `hasRole` checks remain; staff only accesses `api/staff/*` endpoints; doctor fallback explicitly allows doctor on `save-vitals.php`.
- **Migration safety:** `triage_assessments.appointment_id` is added as `NULL`-able to keep any pre-existing seeded rows valid.
- **Idempotency:** vitals save and cert issue are upserts keyed on `appointment_id`; safe to retry.

## Testing

Manual verification in XAMPP:

1. Create a staff user via admin → log in → land on staff dashboard.
2. Patient books an appointment → staff scans QR → status → `checked_in`.
3. Staff records vitals → status → `in_progress`; row appears in `triage_assessments` with appointment_id set.
4. Doctor opens the appointment → vitals card shows the recorded values; chief complaint visible read-only.
5. Doctor completes medical record → status → `completed`; vital_signs in `medical_records` is NULL for new row (legacy rows untouched).
6. Staff issues cert → cert row written; print-certificate.html renders correctly with letterhead.
7. Staff re-prints the cert → same content, no duplicate row.
8. Doctor falls back to recording vitals when staff missed it → row appears in `triage_assessments` and counts as staff-recorded for downstream display.

## File / Module Impact

- **New:**
  - `pages/staff-dashboard.html`
  - `pages/print-certificate.html`
  - `api/staff/save-vitals.php`
  - `api/staff/get-vitals.php`
  - `api/staff/queue.php`
  - `api/staff/issue-certificate.php`
  - `api/staff/certificate.php`
  - `api/staff/certificates.php`
  - `api/admin/add-staff.php`
  - `database/migrations/2026-05-06-batch-c1.sql`
- **Modified:**
  - `database/schema.sql` (reflect role enum, new tables, alter)
  - `pages/login.html` (route `staff`)
  - `pages/admin-dashboard.html` (staff management section)
  - `pages/doctor-dashboard.html` (vitals card, drop vitals input)
  - `api/auth/login.php` (no logic change; verify staff role round-trips)
  - `api/doctor/save-medical-record.php` (drop vitals + chief_complaint write paths)
  - `api/doctor/get-appointments.php` (join triage_assessments)
- **Untouched:** existing patient flow, Batch A notification/cancellation flows, QR generation/verify primitives.

## Open Questions

None. All decisions resolved during brainstorming.
