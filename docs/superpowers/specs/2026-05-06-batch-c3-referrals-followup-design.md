# Batch C3 — Specialist Referrals & Follow-Up Auto-Booking

**Date:** 2026-05-06
**Status:** Draft (pending user review)
**Scope:** Add doctor-side flows to refer patients to an external specialist (with a printable referral letter) and to schedule a follow-up appointment that lands directly on the patient's calendar.

This is the third of three sub-specs under **Batch C — System Workflow Alignment**. It does not depend on Batch C1 or C2.

## Goals

1. Replace the current "doctor mentions a referral in notes" practice with a structured referral that produces a printable letter.
2. Replace the current "doctor types a follow_up_date and the patient is expected to remember to book it" practice with a tentative appointment that the patient receives in their dashboard.
3. Keep both flows entirely on the doctor's medical-record screen — no new top-level navigation.

## Non-Goals

- An internal directory of specialists (referrals are external — printable letter goes to the patient).
- Routing referrals to specific named specialists in the system.
- Automatic email of the referral letter (print-only for v1, matching the cert flow).
- Multi-step follow-ups (only one follow-up per appointment; if the doctor needs more, they edit the existing one or schedule a new one after the follow-up visit).

## Feature 1 — Specialist Referrals

### Requirements

On the doctor's medical-record screen, a new **Refer to specialist** card appears alongside Diagnosis / Prescription. Inputs:

- **Specialty** — required dropdown:
  - ENT (Ear, Nose, Throat)
  - Cardiology
  - OB-GYN
  - Pediatrics
  - General Surgery
  - Dermatology
  - Orthopedics
  - Ophthalmology
  - Neurology
  - Other (free-text input appears when chosen)
- **Reason for referral** — required text area (justification, observed symptoms).
- **Suggested specialist name** — optional text (e.g., "Dr. dela Cruz at St. Lukes").
- **Urgency** — optional radio: routine | urgent | emergency (default: routine).

On save, a row is written to a new `referrals` table. A "Print referral letter" button opens `pages/print-referral.html` which renders the printable letter using the existing letterhead.

The doctor can issue at most **one referral per appointment** for v1. Editing an existing referral overwrites the row.

### Data Model

```sql
CREATE TABLE referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  referring_doctor_id INT NOT NULL,
  specialty VARCHAR(100) NOT NULL,
  specialty_other VARCHAR(100) NULL,         -- free text when specialty='Other'
  suggested_specialist VARCHAR(150) NULL,
  reason TEXT NOT NULL,
  urgency ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_referral_appointment (appointment_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (referring_doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### API

- `POST api/doctor/save-referral.php` — upsert keyed by `appointment_id`. Validates that the doctor owns the appointment and that the appointment status is `in_progress` or `completed`.
- `GET api/doctor/get-referral.php?appointment_id=N` — returns the referral if any (used to prefill the form on revisit).
- `GET api/staff/referral.php?appointment_id=N` — staff-readable for printing (admin and staff can also print). Same shape as the doctor endpoint.

### Frontend

- `pages/doctor-dashboard.html`: the medical-record drawer adds a "Refer to specialist" card with a checkbox **"This visit needs a referral"** that reveals the form. Pre-filled if a referral already exists for this appointment.
- New: `pages/print-referral.html` — printable, single-page, embedded styles, letterhead; placeholders filled by `api/doctor/get-referral.php` (or `api/staff/referral.php` if accessed from outside the doctor role). Includes a "To: {Specialty} Specialist" line, the reason, urgency, and signature block.

## Feature 2 — Follow-Up Appointment Auto-Booking

### Requirements

On the doctor's medical-record screen, a new **Schedule follow-up** card replaces the current bare `follow_up_date` field. Inputs:

- **Follow-up date** — required (must be ≥ today + 1 day).
- **Time slot** — required dropdown of the same doctor's available slots on the chosen date (reuses `api/patient/get-available-slots.php` semantics, scoped to that doctor).
- **Reason** — optional, defaulted to *"Follow-up of {original chief complaint}"*.

On save:

1. A new row is inserted into `appointments` with:
   - `appointment_number` generated as usual.
   - `patient_id`, `doctor_id` copied from the originating appointment.
   - `appointment_date`, `appointment_time` from the form.
   - `status = 'scheduled'`.
   - `reason_for_visit` = the input above.
   - `parent_appointment_id` = original appointment id.
   - `is_followup = 1`.
2. `medical_records.follow_up_date` is also written for backward compatibility.
3. A QR token is generated for the new appointment, same as a normal booking.
4. A `notifications` row of type `followup_scheduled` is written to the patient's user inbox; an email confirmation is sent (reuses Batch A's mailer).

The patient sees the appointment in their dashboard and can:

- **Keep** it (no action required).
- **Move** it (cancel + rebook a new slot, normal flow).
- **Cancel** it (normal flow, subject to the 2-hour cutoff once it's near).

The doctor can edit the follow-up before the patient interacts with it (re-save the form with a different date/time → existing follow-up appointment is updated; QR token reissued).

### Data Model

```sql
ALTER TABLE appointments
  ADD COLUMN parent_appointment_id INT NULL AFTER doctor_id,
  ADD COLUMN is_followup TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_appointment_id,
  ADD CONSTRAINT fk_parent_appointment
    FOREIGN KEY (parent_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  ADD INDEX idx_parent_appointment (parent_appointment_id);
```

### API

- `POST api/doctor/schedule-followup.php` — creates or updates the follow-up appointment for the given parent appointment. Body:
  ```json
  {
    "parent_appointment_id": 123,
    "appointment_date": "2026-06-15",
    "appointment_time": "10:30",
    "reason_for_visit": "..."
  }
  ```
  - If an **active** follow-up already exists for the parent (status in `scheduled`, `checked_in`, `in_progress`), update its date/time/reason in place and reissue the QR token.
  - If only **non-active** follow-ups exist for the parent (e.g., previously cancelled), create a new follow-up appointment row.
  - Returns `{ appointment_id, appointment_number, qr_payload, mode: 'created' | 'updated' }`.
- `GET api/doctor/get-followup.php?parent_appointment_id=N` — returns the existing follow-up if any.
- The patient's existing `api/patient/get-appointments.php` returns follow-up appointments transparently — they look like any other booking, plus a `is_followup: 1` flag the dashboard can use to add a small badge.

### Frontend

- `pages/doctor-dashboard.html`: replace the existing date-only follow-up field with the new card containing date + slot picker + reason. The card hides slot dropdown until a date is picked.
- `pages/patient-dashboard.html`: render a small "Follow-up" pill on appointments where `is_followup = 1`.

## Feature 3 — Quick Re-Print From Patient History

### Requirements

When the patient (or staff/admin) views a past appointment, they can re-print the referral letter from any device, the same way `print-record.html` already works for medical records.

### Frontend

- `pages/patient-dashboard.html` past-appointment detail view: if a referral exists for the appointment, show a "Print referral" button that opens `pages/print-referral.html?appointment_id=N`.

(There is no API change here — the existing `api/staff/referral.php` is the read endpoint; admin and the owning patient are also authorized.)

### Backend Auth Adjustment

`api/staff/referral.php` accepts the following authorized callers:

- The doctor who issued the referral.
- Any user with role `staff` or `admin`.
- The patient whose appointment the referral belongs to (allowing self re-print).

## Database Changes Summary

```sql
-- 2026-05-06-batch-c3.sql
CREATE TABLE referrals ( ... );                  -- see Feature 1
ALTER TABLE appointments ADD parent_appointment_id ..., ADD is_followup ...;  -- see Feature 2
```

`database/schema.sql` is updated to reflect the new table and altered appointments shape.

## Error Handling & Edge Cases

- **Doctor schedules a follow-up on a fully booked day:** server returns 400 with the same "no slot available" message used by patient booking; doctor picks another time.
- **Follow-up date in the past:** rejected with 400 client-side and server-side.
- **Doctor edits an existing follow-up after the patient has already cancelled it:** the cancelled row is left alone; saving creates a new follow-up row tied to the same parent. (The unique constraint is on `appointment_id`, not on parent — multiple non-active children are allowed.)
- **Referral on a no-show / cancelled appointment:** rejected — appointment status must be `in_progress` or `completed`.
- **Other-specialty free text empty when specialty='Other':** rejected with 400.
- **Print referral when no referral exists:** the print page shows *"No referral has been issued for this appointment."* and offers a back link.
- **Patient deletes account:** cascades to referrals and follow-up appointments via the existing FK chain.

## Testing

Manual verification in XAMPP:

1. Doctor opens an in-progress appointment → checks "This visit needs a referral" → fills in ENT + reason → saves → row in `referrals`. Click "Print referral letter" → letter renders with letterhead and patient/doctor details.
2. Doctor edits the referral (changes specialty to Cardiology) → row updated, not duplicated.
3. Doctor schedules a follow-up for next week at 10:00 → new appointment row appears with `is_followup=1`, `parent_appointment_id` set to the current appointment, QR token generated.
4. Patient logs in → sees the follow-up in their dashboard with a "Follow-up" pill; receives the in-app + email notification.
5. Patient cancels the follow-up (>2h ahead) → normal cancel path; original parent appointment is unaffected.
6. Doctor re-schedules a follow-up for a different time → existing follow-up appointment is updated, QR token reissued; patient sees the new time.
7. Past appointment view: patient clicks "Print referral" → letter renders.

## File / Module Impact

- **New:**
  - `api/doctor/save-referral.php`
  - `api/doctor/get-referral.php`
  - `api/doctor/schedule-followup.php`
  - `api/doctor/get-followup.php`
  - `api/staff/referral.php`
  - `pages/print-referral.html`
  - `database/migrations/2026-05-06-batch-c3.sql`
- **Modified:**
  - `pages/doctor-dashboard.html` — referral card + revamped follow-up card
  - `pages/patient-dashboard.html` — follow-up pill, print-referral button
  - `database/schema.sql` — reflect referrals table + appointments alter

## Open Questions

None. All decisions resolved during brainstorming.
