# Batch A — Appointment Lifecycle & Notifications

**Date:** 2026-04-14
**Status:** Approved (pending user review of written spec)
**Scope:** Patient cancellation rules, doctor emergency day-cancel, email notifications (Gmail SMTP), in-app notifications, weekly booking view.

## Goals

1. Patients have clear, safe self-cancellation with a reasonable cutoff.
2. Doctors can cancel a full day of appointments during emergencies, and affected patients are notified automatically.
3. Restore email notifications via Gmail SMTP (PHPMailer already in the project).
4. Give patients, doctors, and admins a persistent in-app notification inbox.
5. Doctors and admins get a weekly (Mon–Sun) schedule view alongside the existing "today" view.

## Non-Goals

- Auto-rescheduling patients to alternative days (patients rebook themselves).
- SMS notifications.
- 24-hour reminder cron job (deferred — only add if hosting supports cron).
- Multi-doctor conflict resolution beyond what exists today.

## Feature 1 — Patient Cancellation Rules

### Requirements

- A patient may cancel an appointment only while its status is `scheduled` **and** the current time is at least **2 hours before** `appointment_date + appointment_time`.
- The cancel action requires a confirmation dialog: *"Cancel this appointment? / Keep it"*.
- After the 2-hour cutoff, the cancel button is disabled and the UI shows: *"Cancellation window closed — please contact the clinic."*
- Booking confirmation UI and confirmation email display: *"You can cancel up to 2 hours before your scheduled time."*

### Backend

- `api/patient/cancel-appointment.php` adds cutoff enforcement:
  - Compute `appointment_datetime = appointment_date + appointment_time`.
  - Reject with HTTP 400 and a message when `NOW() > appointment_datetime - INTERVAL 2 HOUR`.
  - On success, store `cancelled_by = 'patient'`, `cancelled_at = NOW()`, `cancel_reason = NULL` (or optional free-text from the UI, not required for v1).
  - Enqueue email + in-app notification to the patient confirming cancellation.

### Frontend

- Patient dashboard appointment list: compute cutoff client-side to enable/disable the Cancel button and render the closed-window message. Backend remains source of truth.
- Confirmation dialog implemented with existing modal pattern (no new dependency).

## Feature 2 — Doctor Emergency Day-Cancel

### Requirements

- New action on the doctor dashboard: **"Mark day unavailable"**.
- Inputs: date (required, must be today or future), reason (required, short text e.g., "Emergency", "Sick leave").
- Confirmation dialog shows: *"This will cancel N scheduled appointments on {date}. Patients will be notified by email. Continue?"*
- On confirm:
  - All appointments on that date with status `scheduled` become `cancelled`, with `cancelled_by = 'doctor'` (or `'admin'` if admin initiated), `cancelled_at = NOW()`, `cancel_reason = <reason>`.
  - Each affected patient receives an in-app notification and an email.
- Admin can perform the same action from the admin dashboard on behalf of any doctor.
- Idempotency: if re-run for the same day, already-cancelled appointments are skipped.

### Backend

- New endpoint: `api/doctor/cancel-day.php` (POST). Auth: doctor or admin.
- Body: `{ date: 'YYYY-MM-DD', reason: '...', doctor_id?: N (admin only) }`.
- Transactional update over all matching appointments; collect IDs for notification fan-out.
- After commit, send notifications (email + in-app). Email sending must not block the HTTP response if it fails — log and continue.

### Frontend

- Doctor dashboard: button in the weekly/today views → modal with date picker and reason textarea → confirm.
- Admin dashboard: same modal, with an additional doctor selector.

## Feature 3 — Email Notifications (Gmail SMTP)

### Requirements

- Use PHPMailer (already installed per `install-phpmailer.bat`) with Gmail SMTP.
- SMTP credentials read from environment / `config/config.php`: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM_NAME`, `SMTP_FROM_EMAIL`.
- Emails are sent (best-effort, non-blocking for HTTP response) on:
  1. Booking confirmation (to patient).
  2. Patient self-cancellation confirmation (to patient).
  3. Doctor day-cancel (to each affected patient).
- Each email uses a shared HTML template wrapper with clinic branding (header/footer) and a content block.

### Implementation

- New file: `utils/mailer.php` exposing:
  - `send_mail($to, $subject, $html_body): bool` — low-level wrapper.
  - `send_booking_confirmation($patient, $appointment): bool`
  - `send_cancellation_confirmation($patient, $appointment): bool`
  - `send_day_cancelled($patient, $appointment, $reason): bool`
- Failures are logged via `error_log`; callers do not throw.
- A simple template helper renders variables into an HTML layout stored at `utils/email-templates/base.html`.

## Feature 4 — In-App Notifications

### Requirements

- Persistent inbox per user with unread badge.
- Bell icon in patient, doctor, and admin dashboards shows the unread count.
- Clicking the bell opens a dropdown listing recent notifications (most recent 20). Clicking a notification marks it read and, if `link` is set, navigates to it.
- "Mark all as read" control in the dropdown.

### Data Model

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

`type` values for v1: `booking_confirmed`, `appointment_cancelled_by_patient`, `appointment_cancelled_by_doctor`, `day_cancelled`.

### API

- `GET  api/notifications/list.php` → `{ notifications: [...], unread_count: N }` (last 20, newest first).
- `POST api/notifications/mark-read.php` body `{ id: N }` or `{ all: true }`.
- Auth: any logged-in user, scoped to their own `user_id`.

### Helper

- `utils/notifications.php` exposing `notify($db, $user_id, $type, $title, $message, $link = null)`. Called from booking, cancel, and day-cancel flows alongside email sending.

## Feature 5 — Weekly Booking View

### Requirements

- New tab on doctor dashboard and admin dashboard: **"Weekly Schedule"**.
- Shows a Mon–Sun grid for a selected week, with rows per time slot (pulled from `doctor_schedules.slot_duration`).
- Each cell shows the patient name and a status color:
  - `scheduled` — blue
  - `checked_in` — amber
  - `in_progress` — purple
  - `completed` — green
  - `cancelled` / `no_show` — grey
- Week navigation: ◀ prev / "This week" / next ▶.
- Clicking a cell opens the existing appointment details modal.
- Admin view includes a doctor selector at the top (default: all doctors, or first doctor).

### Backend

- New endpoint: `api/doctor/get-weekly-appointments.php` with params `week_start=YYYY-MM-DD`, optional `doctor_id` (admin only).
- Returns appointments for the 7-day range, joined with patient names.

### Frontend

- New component block inside `pages/doctor-dashboard.html` and `pages/admin-dashboard.html`, reusing the current dashboard's table/modal styling.

## Database Changes Summary

```sql
ALTER TABLE appointments
  ADD COLUMN cancelled_by ENUM('patient','doctor','admin','system') NULL AFTER cancelled_at,
  ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by;

CREATE TABLE notifications ( ... );  -- see Feature 4
```

Migration file: `database/migrations/2026-04-14-batch-a.sql`.

## Error Handling & Edge Cases

- **Cancel past cutoff:** backend 400 with explicit message; frontend disables the button independently.
- **Day-cancel with no matching appointments:** succeed as a no-op, return `cancelled_count: 0`.
- **Email failures:** logged, never block the API response or throw to the user.
- **Notification fan-out on day-cancel:** single transaction for DB updates; notifications/emails sent after commit in a loop. Partial failures are logged.
- **Concurrent cancel (patient + doctor day-cancel):** both write `status='cancelled'`; last-write wins is acceptable for v1. Patient who already cancelled does not receive a duplicate email.
- **Timezone:** all comparisons use server local time (PHP `NOW()` / MySQL `NOW()`), matching existing project convention.

## Testing

- Manual verification in XAMPP:
  - Cancel >2h before → allowed; <2h → blocked with message.
  - Doctor day-cancel with multiple scheduled patients → all cancelled, all notified, email received at test address.
  - Weekly view across week boundaries (Sun→Mon).
  - Bell icon unread count updates after new notification.
- SMTP verified against a Gmail account using an app password.

## File/Module Impact

- **New:** `api/doctor/cancel-day.php`, `api/doctor/get-weekly-appointments.php`, `api/notifications/list.php`, `api/notifications/mark-read.php`, `utils/mailer.php`, `utils/notifications.php`, `utils/email-templates/base.html`, `database/migrations/2026-04-14-batch-a.sql`.
- **Modified:** `api/patient/cancel-appointment.php` (cutoff + notifications), `api/patient/book-appointment.php` (send confirmation), `pages/patient-dashboard.html` (cancel UX + bell), `pages/doctor-dashboard.html` (day-cancel + weekly view + bell), `pages/admin-dashboard.html` (day-cancel + weekly view + bell), `config/config.php` (SMTP env), `database/schema.sql` (reference schema update).

## Open Questions

None. All decisions made during brainstorming and captured above.
