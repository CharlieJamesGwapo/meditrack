# Batch B — UI Fixes (Register / Admin Sidebar / Doctor Scanner / Doctor Mobile / Landing Hero / QR Download / Dr. Prefix)

**Date:** 2026-04-29
**Branch:** feature/batch-a-appointments-notifications (work continues here, or a new branch if preferred)
**Scope:** Seven independent, frontend-only UI fixes reported via screenshots.

## User-provided inputs

Two values must be supplied by the user before implementation runs. These are the only blockers.

- **`<HOSPITAL_NAME>`** — the real hospital name shown in the landing hero (e.g. "St. John Bautista District Hospital"). Replaces the existing eyebrow line "INTERNAL MEDICINE OPD MANAGEMENT SYSTEM" in the hero badge, or appears as a new heading line above the system name. *Pending — user has deferred this decision; implementation must not proceed for Fix 5 without it.*
- **`<HOSPITAL_PHOTO_PATH>`** — path to the building photo to use as the hero background. Recommended location: `assets/images/hospital.jpg`. The user must save the photo at this path (or tell the implementer the actual path) before implementation runs.

## Problem summary

Seven small but visible issues in the current build:

1. **Register page** — On laptop/desktop view the *Password* and *Confirm Password* fields look unequal because the password-strength bar makes the Password column taller than the Confirm Password column. On mobile the grid collapses to one column, so the issue is invisible there.
2. **Admin sidebar** — The label "Doctor Schedule" should read "OPD Schedule" so it matches the clinic's terminology.
3. **Doctor dashboard** — The QR scanner button is missing whenever the doctor has no appointments scheduled for today. The scan modal and check-in flow still work, but there is no UI entry point to open them.
4. **Doctor dashboard mobile** — On phone, the entire left sidebar is hidden and the doctor's profile info (name, specialization, on-duty status) is not surfaced anywhere. Only a tiny brand icon and the page title are visible at the top, plus a 3-button bottom nav. The doctor can navigate but cannot see who they are logged in as.
5. **Landing page hero** — The hero currently uses an abstract cyan/teal gradient. It should use a photo of the actual hospital as the background and display the real hospital name, so visitors recognise the place they need to go.
6. **QR download** — When a patient downloads the appointment QR, the saved file is just the bare QR pixels with no surrounding context. The user wants the downloaded image to match what is shown on screen — appointment number, doctor name and date/time above the QR, and the "Show this QR code at the clinic" hint below — so the patient (or whoever they share it with) can identify the appointment at a glance.
7. **"Dr. Dr." duplicated prefix** — The QR modal's appointment line reads "Dr. Dr. Maria Santos" because the JS hardcodes a `Dr. ` prefix in front of `appt.doctor_name`, but the DB already stores the title in the name. Drop the hardcoded prefix so the title appears once.

## Architecture

Pure frontend changes. No backend, database, or authentication changes. No new dependencies.

Files touched:

- `pages/register.html` — CSS only.
- `pages/admin-dashboard.html` — text rename only.
- `pages/doctor-dashboard.html` — add one button in the header (Fix 3) + relax mobile-hiding on the existing avatar chip (Fix 4).
- `js/doctor-dashboard.js` — no code changes required (existing `openQRScanner` accepts `null`; existing `performCheckIn` is appointment-agnostic).
- `index.html` — hero CSS + hero markup (Fix 5).
- `assets/images/hospital.jpg` — new image asset (user-provided photo).
- `js/patient-dashboard.js` — replace `downloadQR()` body with a composited canvas that renders header text + QR + footer text (Fix 6); remove the hardcoded `Dr. ` prefix on line 833 (Fix 7).

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

## Fix 4 — Doctor dashboard: surface doctor profile chip on mobile

### Why the sidebar appears missing on phone

The doctor dashboard has two parallel navigation systems:

- A desktop left **sidebar** (`<aside class="sidebar hidden md:flex …">`) that contains the IM-OPD logo, a profile card with avatar/name/specialization/"On duty" pill, and the navigation list. It is hidden below the `md` breakpoint via `hidden md:flex` and an explicit `display: none !important` in the page's mobile media query.
- A mobile **bottom nav** (`#mobile-bottom-nav`, `md:hidden`) with three buttons — Today / All / Profile — that calls the same `switchTab()` actions as the sidebar.

So mobile navigation already works. What is missing on mobile is the **doctor identification chip** (avatar + name + role). It exists in the header markup but is wrapped in `hidden sm:flex` (entire chip hidden below `sm`) and `hidden md:block` (text hidden below `md`), so on a phone the only thing the doctor sees in the header is a tiny brand icon and the page title.

### Change

Make the existing header avatar chip visible on mobile. No new components.

- In `pages/doctor-dashboard.html`, change the avatar-chip wrapper class from `hidden sm:flex` to `flex`. The chip will then render at every breakpoint.
- Inside that chip, change the inner text wrapper class from `hidden md:block` to `block`. The doctor's name and role will then render at every breakpoint.
- Tighten the chip's padding/spacing on small viewports so the row does not overflow alongside the new "Scan QR" button (Fix 3), the bell, and the logout button. Concretely: keep the chip but allow a slightly smaller `px` and tighter `space-x` below `sm` if needed. The implementer should resize the viewport to 360px and adjust only if the row wraps or clips.
- The "On duty" pill from the sidebar profile card is **out of scope** for this fix. The user did not call it out specifically, and adding it to the chip would crowd the mobile header. It can be revisited later.

### What stays the same

- The desktop sidebar markup, profile card, and nav list are unchanged.
- The bottom mobile nav is unchanged.
- The data populating `#header-avatar`, `#header-name`, and `#header-role` is unchanged.
- The hardcoded `header-role` text "Doctor" in the markup is unchanged. (The dynamic value, if any, is filled in by the existing JS.)

## Fix 5 — Landing page: hospital photo + real hospital name in hero

### Why the change

The current hero is a stylised cyan/teal gradient with a generic "INTERNAL MEDICINE OPD MANAGEMENT SYSTEM" eyebrow. Patients arriving at the site cannot immediately recognise it as the hospital they need to physically visit. The user wants the hero grounded in the real-world location: actual building photo as the background, real hospital name as the headline.

### Change

Two edits to `index.html`:

**a) Hero background.** Replace the gradient-only `.hero-gradient` background with a layered background that combines the hospital photo with a darker overlay, so:

- The photo reads as the dominant visual element.
- White text on top stays legible against the brightest parts of the photo (bright sky, light walls).

Approach: layer a semi-opaque dark teal gradient *over* the photo using the standard CSS pattern `background: linear-gradient(<overlay>), url('<HOSPITAL_PHOTO_PATH>') center/cover no-repeat;`. A starting overlay of `linear-gradient(135deg, rgba(8,51,68,0.72) 0%, rgba(8,145,178,0.55) 55%, rgba(34,211,238,0.40) 100%)` keeps the existing hero's colour story while letting the photo show through. The implementer should preview the result and adjust opacity values if text contrast falls below WCAG AA on any region of the photo.

**b) Hero headline + eyebrow.**

- Replace the eyebrow text "Internal Medicine OPD Management System" with the real hospital name `<HOSPITAL_NAME>`. The eyebrow's pill styling (border + chip background) is preserved.
- The existing large headline ("Internal Medicine / OPD Management System") stays — it now reads as the system tagline beneath the hospital name, which is the desired visual hierarchy.

### Photo asset

- Path: `assets/images/hospital.jpg` (user must save the photo here before implementation runs; the spec's `<HOSPITAL_PHOTO_PATH>` placeholder resolves to this).
- Format: JPG (smaller than PNG for photos, no transparency needed).
- Recommended dimensions: at least 1920×1080 so the hero stays crisp on large desktop displays. The provided photo appears to be wider than tall (landscape) which matches the hero aspect.
- The image must be added to git and committed as part of the implementation; it is not auto-generated.

### What stays the same

- The hero's grain texture overlay (`.grain` class) and the floating background blobs in the absolute-positioned div are unchanged — they layer on top of the new photo background and provide texture.
- The CTAs (Register Now, Log In) and their hrefs are unchanged.
- The system name typography ("Internal Medicine / OPD Management System") in the large headline is unchanged.
- All sections below the hero (How It Works, Features, etc.) are unchanged.

## Fix 6 — QR download: composite header + QR + footer into one image

### Why the bare QR is not enough

`downloadQR()` in `js/patient-dashboard.js` calls `canvas.toDataURL('image/png')` on the bare QR canvas (260×260 px) and saves it as `QR-Appointment-${id}.png`. The screen above the canvas — appointment number, doctor name + date/time, and the clinic check-in hint — never makes it into the file. Patients lose all context about *which* appointment the QR is for once it leaves the dashboard.

### Change

Rewrite `downloadQR()` to render a new offscreen canvas that mirrors the on-screen card. Pure Canvas 2D API; no new libraries.

Layout (top to bottom, all centred horizontally):

1. White background.
2. Eyebrow line: small grey uppercase "APPOINTMENT: APT-…" — pulled from `qrApptNumber`'s text content (or rebuilt from `appt.appointment_number`).
3. Title line: bold dark "Doctor name • Date • Time" — pulled from `qrApptInfo`'s text content (after Fix 7 removes the duplicate prefix). The text is whatever is already on screen.
4. The existing 260×260 QR canvas, drawn via `ctx.drawImage(sourceCanvas, x, y)`.
5. Footer line: small grey "Show this QR code at the clinic for check-in" — copied from the static markup.

Sizing:

- Canvas width: 520 px (gives the QR a margin on each side and lets the title wrap if very long).
- Canvas height: derived from text + QR + footer + padding (~640–680 px). Implementer measures using `ctx.measureText()` for the longest title line.
- Use `font-family: 'Outfit', system-ui, sans-serif` for headings and `'DM Sans', system-ui, sans-serif` for the body, with a `system-ui` fallback in case the page fonts are not yet loaded by the time download runs.

Download flow stays the same: build a `data:` URL via `toDataURL('image/png')`, set it as a temporary `<a>` href, click. Filename pattern unchanged: `QR-Appointment-${currentQRApptId}.png`.

### Fallback

If for some reason the QR is rendered to the `<img>` element (server-provided base64) and the canvas is hidden — keep the existing `img.src` fallback at the end of `downloadQR()` as a no-frills download. Compositing only runs when the canvas path is in use; this matches the current branching logic.

### What stays the same

- The on-screen modal — `qrCanvas`, `qrImage`, `qrApptNumber`, `qrApptInfo`, all surrounding markup — is unchanged.
- The QR generation and `regenerateQR()` flow are unchanged.
- The download button itself, its handler binding, and its file name are unchanged.

## Fix 7 — Remove the hardcoded "Dr. " prefix in QR modal info

### Why "Dr. Dr." appears

`js/patient-dashboard.js` line 833 builds the title string as:

```js
`Dr. ${appt.doctor_name || ''} • …`
```

The screenshot shows `doctor_name` is already stored as "Dr. Maria Santos" in the database. Concatenating "Dr. " in front yields "Dr. Dr. Maria Santos".

### Change

Drop the hardcoded prefix. The string becomes:

```js
`${appt.doctor_name || ''} • …`
```

This matches the convention used elsewhere in the project where the title is part of the stored name. After this change, the modal reads "Dr. Maria Santos • Apr 22, 2026 • 8:00 AM", and the downloaded image (Fix 6) inherits the corrected string automatically because it sources the title from the same field.

### What stays the same

- The DB schema and stored values are unchanged. No migration.
- No other display location is touched in this fix; if other pages have the same doubled prefix, they will be addressed in a future batch when surfaced. The `Dr. Dr. Maria Santos` text in the doctor dashboard sidebar (sidebar-name) is a separate location — it is NOT in scope for this fix unless the user reports it. Keep this fix scoped to the QR modal info line in patient-dashboard.js only.

## Cross-cutting concerns

### Error handling

All seven fixes rely on existing error paths:

- Register CSS: no runtime behaviour added; nothing to fail.
- Admin sidebar rename: text only; no runtime behaviour changed.
- Doctor dashboard scanner: the scanner already handles "no camera" (falls back to manual token input), "invalid QR" (SweetAlert warning), and "check-in failed" (SweetAlert error). The new global button reuses these paths verbatim.
- Doctor mobile chip: no runtime behaviour added; the chip uses existing JS-populated ids.
- Landing hero: background image fallback — if the photo fails to load (404, broken file), the underlying overlay gradient still renders and the hero remains usable; CSS handles this implicitly because the gradient is layered on top of the URL.
- QR download composite: if `ctx.measureText()` or `drawImage` throws (extremely unlikely on modern browsers), fall through to the existing `img.src` fallback path that downloads the bare image. SweetAlert error message remains for the "no QR to download" case.
- Dr. prefix: pure string concatenation change; nothing to fail.

### Testing

Manual visual verification (no automated tests):

- **Register:** Open `pages/register.html` in a laptop viewport (≥1024px). Confirm the Password column and Confirm Password column align at the bottom. Type a password and watch the strength bar render — row height must not jump. Resize down to a mobile viewport (<768px) and confirm the stacked layout is unchanged.
- **Admin sidebar:** Open `pages/admin-dashboard.html`. Confirm "OPD Schedule" appears in the sidebar, in the tab heading, and in any breadcrumb / page title. Click the sidebar item and confirm the schedule tab still loads.
- **Doctor dashboard scanner:** Log in as a doctor with no appointments today. Confirm the global "Scan QR" button is visible in the header. Click it — the QR modal must open. Test both flows: (a) scan a valid patient QR, confirm SweetAlert success and the appointment moves to checked-in state; (b) paste an invalid token in the manual field, confirm SweetAlert error.
- **Doctor mobile chip:** Resize to 360px or load on a real phone. Confirm the avatar circle and the doctor's name + role render in the header. Confirm the row does not wrap or overflow with all four right-side elements present (Scan QR icon, bell, chip, logout). Resize back up to desktop and confirm the chip still renders correctly there.
- **Landing hero:** Open `index.html` on desktop. Confirm the hospital photo is visible behind the dark teal overlay; the eyebrow shows the real hospital name; the headline, sub-text, and CTAs are still legible. Resize to mobile (375px) and confirm the photo still covers the hero, no awkward cropping cuts off important elements (e.g. the building entrance), and text remains readable.
- **QR download:** Open `pages/patient-dashboard.html` as a logged-in patient with at least one appointment. Open the QR modal, then click Download. Open the downloaded `QR-Appointment-<id>.png` in a viewer and confirm: the appointment number is shown above the QR, the doctor name + date + time line follows, the QR is the same one as on screen, and the "Show this QR code at the clinic for check-in" footer is at the bottom. Verify both desktop and mobile flows.
- **Dr. prefix:** In the same QR modal, confirm the title line reads "Dr. Maria Santos • …" (one "Dr.", not two). Confirm the downloaded PNG (Fix 6) shows the same single-prefix line.

### Out of scope

- No changes to the QR check-in backend (`/api/appointments/checkin.php`).
- No changes to the admin's underlying schedule data model or routes.
- No changes to other dashboards (patient, receptionist) or other pages.
- No restyle of unrelated form fields, no other admin-sidebar relabelling.
- No new mobile hamburger menu or drawer for the doctor dashboard. Surfacing the chip is a smaller, lower-risk fix.
- No "On duty" pill on the doctor mobile header.
- No restyling of the landing page sections below the hero.
- No changes to the favicon, logo, or other site-wide branding outside the hero.
- No new client-side image library (no html2canvas, no dom-to-image). The composited QR download uses only the Canvas 2D API.
- No data fix or migration for doctor names. The "Dr." prefix fix is display-only.
- No update to the doctor dashboard sidebar's `sidebar-name` if it has the same doubled prefix. This batch handles the QR modal occurrence only.

## Risks

- **Helper-row height drift.** The shared `min-height` value for `.str-wrap` and `.match-hint` is sized to today's rendered match-hint line. If the strength bar is later thickened (more segments / taller segments) or the match-hint font-size changes, the rows could go uneven again. Mitigation: add a CSS comment beside the rule explaining that both selectors must stay in sync, and that the value must be at least as tall as the largest of the two helpers.
- **Sidebar id coupling.** If the rename accidentally changes a tab id or data attribute, the schedule tab will fail to load. Mitigation: rename only the text content inside elements, never element ids or attributes.
- **Header layout overflow on mobile.** Adding a button to the doctor dashboard header *and* surfacing the avatar chip could crowd the line on small screens. Mitigation: use the existing responsive flex/wrap pattern; verify on a 360px viewport; if necessary, drop the chip's text on the very smallest viewports while keeping the avatar circle visible.
- **Hospital photo load failure or large file.** If the photo is missing, the page still renders (the overlay gradient covers the hero). If the photo is too large (multiple MB), the hero will be slow to paint on mobile. Mitigation: implementer should compress the JPG to a sensible size (target ≤ 400 KB) before committing, and verify the file is referenced with the correct path at the configured `APP_URL` deployment.
- **Hospital name unspecified.** Fix 5 cannot proceed without `<HOSPITAL_NAME>`. Mitigation: implementation plan must block on this input and refuse to write a placeholder string into `index.html`.
- **Composited canvas font availability.** Custom web fonts (Outfit, DM Sans) may not be loaded yet when `downloadQR()` runs, especially on first paint. Mitigation: the font stack falls back to `system-ui, sans-serif` so the text always renders. The downloaded image may use system fonts on first download — acceptable.
- **Stripping "Dr." breaks doctors stored without a title.** If any doctor name in the DB lacks the "Dr." prefix, removing the hardcoded prefix means their name appears without a title in the QR modal. Mitigation: the user has confirmed (via the screenshot showing "Dr. Dr. …") that names are stored with the title. If a counter-example is found later, the fix is a one-line conditional `(/^Dr\.?\s/i.test(name) ? name : 'Dr. ' + name)`. Out of scope for this batch.
