# Batch C2 — Cancellation Broadcast & Self-Reschedule

**Date:** 2026-05-06
**Status:** Draft (pending user review)
**Scope:** When a patient cancels their own appointment, notify a bounded set of other patients who could plausibly take the freed slot, and let them rebook the slot in one click.

This is the second of three sub-specs under **Batch C — System Workflow Alignment**. It depends on Batch A's notifications inbox and email pipeline but does not depend on Batch C1.

## Goals

1. Surface freed appointment slots to patients who could realistically take them, without flooding every account with notifications.
2. Make rebooking the freed slot a one-tap operation from the notification.
3. Preserve existing Batch A behavior: doctor day-cancel and patient cancel-confirmation emails are unchanged; only an additional broadcast is layered on top of patient cancellations.
4. Audit who was notified per broadcast for debugging and rate-limit hygiene.

## Non-Goals

- Holding or reserving the freed slot for any specific recipient.
- A waitlist UX where patients explicitly opt in to notifications for a date.
- Broadcast on doctor day-cancel (those affected patients are already notified individually by Batch A).
- SMS, push notifications.
- Configurable per-patient broadcast preferences.

## Feature 1 — Broadcast on Patient Cancellation

### Requirements

When `api/patient/cancel-appointment.php` succeeds:

1. Identify the **freed slot**: `(doctor_id, appointment_date, appointment_time)` of the cancelled appointment.
2. Build the **recipient set** ("same-day candidates"):
   - All `users.role = 'patient'` accounts with `users.status = 'active'`,
   - excluding the cancelling patient themselves,
   - excluding any patient who **already has an appointment** with status in (`scheduled`, `checked_in`, `in_progress`) on the cancellation's `appointment_date`,
   - capped at the **20 most recently active** accounts (most recent `users.last_login`, NULLs last).
3. Send each recipient a single in-app notification of type `slot_available`, with title and message such as:
   - **Title:** *"Open slot — {appointment_date} {appointment_time}"*
   - **Message:** *"A {appointment_time} slot just opened up on {appointment_date}. Tap to book it before someone else does."*
   - **Link:** `qr-booking.html?date=YYYY-MM-DD&doctor=N&time=HH:MM` (the booking page deep-link, see Feature 2).
4. Email is **not** sent for these broadcasts (would be too noisy; in-app + bell badge is enough).
5. Each recipient receives at most **one** broadcast per (cancelled_appointment_id), enforced by the `cancel_broadcasts` table.

### Why "same-day candidates"

A patient who already has an appointment that day doesn't need the slot. A patient who has never logged in is unlikely to act on the notification. The 20-most-recently-active cap keeps the broadcast useful for an active small clinic and keeps the noise floor low — well below the volume that would make the bell icon meaningless.

### Data Model

```sql
CREATE TABLE cancel_broadcasts (
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

The `UNIQUE` index makes broadcasts idempotent — if `cancel-appointment.php` is retried after a partial failure, recipients aren't double-pinged.

### Backend

- New helper: `utils/CancelBroadcaster.php` exposing:
  ```php
  CancelBroadcaster::broadcast(PDO $db, int $cancelledAppointmentId, array $appt): int
  ```
  - Computes the recipient set (single SQL with the exclusions and 20-row cap).
  - Inside a transaction: for each recipient, `INSERT IGNORE` into `cancel_broadcasts`, create a `notifications` row via `Notifier::notify`, and update the broadcast row with the resulting `notification_id`.
  - Returns count of recipients notified.
- `api/patient/cancel-appointment.php` calls `CancelBroadcaster::broadcast(...)` after the existing cancellation update commits and after the cancelling patient's own confirmation notification is queued. Failures inside the broadcaster are logged via `error_log` and never block the HTTP response — the patient's cancel still succeeds.
- New configuration constant in `config/config.php`:
  ```php
  define('CANCEL_BROADCAST_LIMIT', 20);
  ```

### Frontend

No frontend changes for the broadcast itself — the existing notification bell (Batch A) renders `slot_available` notifications like any other type, with the deep-link from `notifications.link` driving the click target.

## Feature 2 — Booking Page Deep-Link

### Requirements

`pages/qr-booking.html` accepts URL parameters:

- `date=YYYY-MM-DD` — pre-selects the date in the date picker.
- `doctor=N` — pre-selects the doctor (only meaningful once multi-doctor support arrives; today only Dr. Santos exists).
- `time=HH:MM` — pre-selects the time slot if available.

When the page loads with these parameters:

1. The booking form opens with the date and time pre-filled.
2. If the slot is no longer available (someone else booked first), show: *"That slot was just taken — pick another one below."* with the picker still focused on the same date.
3. If the slot is still available, the patient confirms and books normally.

### Backend

- `api/patient/get-available-slots.php` is unchanged. The deep-link relies on the existing slot-availability check at booking time.

### Frontend

- `pages/qr-booking.html` parses query params on load and pre-populates the date/time selector. If the time slot isn't in the available list at fetch time, it displays the "slot taken" notice.

## Feature 3 — Rate-Limit Hygiene

### Requirements

To prevent a single patient who repeatedly cancels from spamming everyone:

- A `notifications` row for `type = 'slot_available'` is **not** sent if the same `recipient_user_id` already has more than **3 unread** `slot_available` notifications in the last 24 hours.
- Implemented in `CancelBroadcaster::broadcast` as part of the recipient-set query.

### Why

Without a guard, a flaky-internet patient cancelling-and-rebooking three times in an hour could send the same recipient three pings, two of which are stale. Three unread is a comfortable ceiling — it lets a clinic bursty-cancel through morning churn without making the bell badge useless.

## Database Changes Summary

```sql
-- 2026-05-06-batch-c2.sql
CREATE TABLE cancel_broadcasts ( ... );          -- see Feature 1
```

`database/schema.sql` is updated to include the new table.

The `notifications` table itself is unchanged — `slot_available` is a new value of the existing `type` column.

## Error Handling & Edge Cases

- **Cancellation outside the cutoff** (Batch A blocks this): broadcast is never reached because the cancel itself fails.
- **Zero recipients** (no eligible patients): broadcast is a no-op; nothing is written to `cancel_broadcasts`. `cancel-appointment.php` returns success regardless.
- **Broadcaster failure** after cancellation succeeds: logged and swallowed; patient still gets their own cancellation confirmation.
- **Two patients click the deep-link near-simultaneously**: existing booking concurrency wins (whoever's `INSERT` lands first); the loser sees the "slot taken" notice on the booking page.
- **Cancelled appointment was for today, < 2h before**: cannot happen — Batch A's cutoff prevents it; the broadcast is therefore always for slots ≥ 2h in the future.
- **Patient deletes account**: `cancel_broadcasts.recipient_user_id` cascades to delete the audit rows; notifications they already received are also cascaded by the existing FK.

## Testing

Manual verification in XAMPP:

1. Seed three patient accounts; one books an appointment for tomorrow at 10:00, another already has tomorrow at 11:00, a third has nothing.
2. The booker cancels their 10:00 appointment.
3. Patient with the 11:00 booking does **not** receive a `slot_available` notification.
4. Patient with no appointment **does** receive it; bell badge increments; clicking the notification opens `qr-booking.html?date=...&time=10:00` with fields pre-filled.
5. They confirm — booking succeeds; appointment row created normally.
6. The same booker re-cancels a different appointment — `cancel_broadcasts` shows two distinct rows, no duplicates per (cancellation, recipient).
7. Stress: cancel 5 appointments in a row from the same booker — recipients with already-3-unread broadcasts in last 24h stop receiving them.

## File / Module Impact

- **New:**
  - `utils/CancelBroadcaster.php`
  - `database/migrations/2026-05-06-batch-c2.sql`
- **Modified:**
  - `api/patient/cancel-appointment.php` — append broadcast call after successful cancel
  - `pages/qr-booking.html` — read `date`, `doctor`, `time` query params; render "slot taken" notice when applicable
  - `config/config.php` — add `CANCEL_BROADCAST_LIMIT`
  - `database/schema.sql` — reflect new table

## Open Questions

None. All decisions resolved during brainstorming.
