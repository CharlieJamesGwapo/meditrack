# Batch B — UI Fixes (Register / Admin Sidebar / Doctor Scanner)

**Date:** 2026-04-29
**Branch:** feature/batch-a-appointments-notifications (work continues here, or a new branch if preferred)
**Scope:** Three independent, frontend-only UI fixes reported via screenshots.

## Problem summary

Three small but visible issues in the current build:

1. **Register page** — On laptop/desktop view the *Password* and *Confirm Password* fields look unequal because the password-strength bar makes the Password column taller than the Confirm Password column. On mobile the grid collapses to one column, so the issue is invisible there.
2. **Admin sidebar** — The label "Doctor Schedule" should read "OPD Schedule" so it matches the clinic's terminology.
3. **Doctor dashboard** — The QR scanner button is missing whenever the doctor has no appointments scheduled for today. The scan modal and check-in flow still work, but there is no UI entry point to open them.

## Architecture

Pure frontend changes. No backend, database, or authentication changes. No new dependencies.

Files touched:

- `pages/register.html` — CSS only.
- `pages/admin-dashboard.html` — text rename only.
- `pages/doctor-dashboard.html` — add one button in the header.
- `js/doctor-dashboard.js` — no code changes required (existing `openQRScanner` accepts `null`; existing `performCheckIn` is appointment-agnostic).

## Fix 1 — Register: equalize password row heights on desktop

### Why the rows look uneven

The form uses `display: grid; grid-template-columns: 1fr 1fr;` (selector `.form-grid`). Password and Confirm Password sit in columns 1 and 2 of the same grid row. Below the Password input is `.str-wrap`, a 4-segment strength bar with visible height. Below the Confirm Password input is `.match-hint`, a thin text node that is empty until the user types. The grid row takes the height of the taller column, so the Confirm Password column gains empty whitespace at the bottom — making the two columns look misaligned.

The widths are already equal (1fr 1fr); only the perceived height/balance is off.

### Change

Reserve equal vertical space below both inputs so the row stays steady in every state.

Measured heights of the existing helpers:

- `.str-wrap`: `margin-top: 6px` + segment `height: 3px` = **9px** total. Always present once the strength bar renders.
- `.match-hint`: `margin-top: 4px` + line-height ≈ **~20px** when text is shown. **0px** when empty (initial state, before the user types in Confirm Password).

The imbalance therefore flips: initially the Password column is ~9px taller (strength bar present, match-hint empty); after typing into Confirm Password, the Confirm column becomes ~11px taller (match-hint text exceeds strength bar).

Fix:

- Set the same `min-height` on both `.str-wrap` and `.match-hint`, sized to the larger of the two (~20px — the implementer should measure the rendered match-hint line in DevTools and use that value, rounded up).
- Add a CSS comment beside the rule noting that both selectors must stay in sync if either helper is restyled.

Mobile view (single column) is unaffected because each field already lives on its own row.

### Out of scope

No restyle of the strength bar itself; no change to the match-hint text or trigger logic.

## Fix 2 — Admin sidebar: rename "Doctor Schedule" to "OPD Schedule"

### Change

Replace the user-visible string "Doctor Schedule" with "OPD Schedule" in `pages/admin-dashboard.html`. Four occurrences identified during exploration (lines 445, 822, 829, 1030 — to be re-verified at implementation time):

- Sidebar nav label.
- Tab section comment header.
- Section heading inside the tab.
- Page title / breadcrumb area.

### What stays the same

- HTML element ids, data attributes, JavaScript tab keys, and any CSS classes are NOT renamed. Only the human-readable text changes. This avoids breaking any JS handlers that key off internal ids.
- No backend or database column is renamed.

### Verification

After the rename, opening the admin dashboard and clicking the renamed sidebar item must still load the same tab content (because the id is unchanged).

## Fix 3 — Doctor dashboard: add a global "Scan QR" button

### Why the scanner appears missing

`renderAppointmentRow` in `js/doctor-dashboard.js` injects a per-appointment "Scan QR" button only when `a.status === 'scheduled'` or `'confirmed'`. When today's appointment list is empty, no button is rendered anywhere on the page. The QR scanner modal (`#qr-modal`) and the underlying flow are intact — only the UI entry point is missing.

`openQRScanner(appointment)` simply assigns `currentAppointment = appointment` and opens the modal. The downstream `performCheckIn(tokenHash)` is appointment-agnostic — it resolves the appointment from the QR token server-side via `/appointments/checkin.php`. Therefore calling `openQRScanner(null)` from a global button is safe and produces a working check-in flow.

### Change

Add a permanent "Scan QR" button to the doctor dashboard header. Placement and behaviour:

- **Location:** right-side action cluster of the existing `<header>` (around `pages/doctor-dashboard.html` line 288), inserted immediately before `#bell-container` so it sits with the other primary actions (notifications bell, avatar chip, logout).
- **Styling:** cyan/teal solid using the same Tailwind classes as the existing per-row Scan QR button — `bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-xl text-xs font-semibold` (or the cyan equivalent if a darker accent is preferred), with `<i class="fas fa-qrcode"></i>` and the label "Scan QR".
- **Mobile responsiveness:** label hidden below `sm` (icon-only on small screens) using `hidden sm:inline` on the text span — same pattern as the Logout button next to it.
- **Click handler:** inline `onclick="openQRScanner(null)"`. No JavaScript signature changes; `currentAppointment = null` is the existing initial state.
- Always visible — independent of today's appointment list.

The per-appointment "Scan QR" buttons in today's list remain unchanged — they continue to provide a row-level shortcut when appointments are listed.

### What stays the same

- The QR scanner modal HTML, CSS, and JS are unchanged.
- The check-in API is unchanged.
- `currentAppointment` semantics are unchanged: it remains `null` until `openQRScanner` is called with an appointment, which is exactly the pre-existing behaviour for the initial page state.

## Cross-cutting concerns

### Error handling

All three fixes rely on existing error paths:

- Register CSS: no runtime behaviour added; nothing to fail.
- Admin sidebar rename: text only; no runtime behaviour changed.
- Doctor dashboard scanner: the scanner already handles "no camera" (falls back to manual token input), "invalid QR" (SweetAlert warning), and "check-in failed" (SweetAlert error). The new global button reuses these paths verbatim.

### Testing

Manual visual verification (no automated tests):

- **Register:** Open `pages/register.html` in a laptop viewport (≥1024px). Confirm the Password column and Confirm Password column align at the bottom. Type a password and watch the strength bar render — row height must not jump. Resize down to a mobile viewport (<768px) and confirm the stacked layout is unchanged.
- **Admin sidebar:** Open `pages/admin-dashboard.html`. Confirm "OPD Schedule" appears in the sidebar, in the tab heading, and in any breadcrumb / page title. Click the sidebar item and confirm the schedule tab still loads.
- **Doctor dashboard:** Log in as a doctor with no appointments today. Confirm the global "Scan QR" button is visible in the header. Click it — the QR modal must open. Test both flows: (a) scan a valid patient QR, confirm SweetAlert success and the appointment moves to checked-in state; (b) paste an invalid token in the manual field, confirm SweetAlert error.

### Out of scope

- No changes to the QR check-in backend (`/api/appointments/checkin.php`).
- No changes to the admin's underlying schedule data model or routes.
- No changes to other dashboards (patient, receptionist) or other pages.
- No restyle of unrelated form fields, no other admin-sidebar relabelling.

## Risks

- **Helper-row height drift.** The shared `min-height` value for `.str-wrap` and `.match-hint` is sized to today's rendered match-hint line. If the strength bar is later thickened (more segments / taller segments) or the match-hint font-size changes, the rows could go uneven again. Mitigation: add a CSS comment beside the rule explaining that both selectors must stay in sync, and that the value must be at least as tall as the largest of the two helpers.
- **Sidebar id coupling.** If the rename accidentally changes a tab id or data attribute, the schedule tab will fail to load. Mitigation: rename only the text content inside elements, never element ids or attributes.
- **Header layout overflow on mobile.** Adding a button to the doctor dashboard header could crowd the line on small screens. Mitigation: use the existing responsive flex/wrap pattern already on the page; verify on a 360px viewport.
