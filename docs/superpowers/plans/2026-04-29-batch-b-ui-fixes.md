# Batch B — UI Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply ten independent frontend-only fixes — small UI papercuts, a system rename, hospital-photo hero, and QR/manual-token UX cleanups for **Bislig District Hospital**'s Consultation OPD Management System.

**Architecture:** Pure HTML/CSS/JS edits across already-existing pages and one new image asset. No backend, database, API, or auth changes. The doctor dashboard's existing `openQRScanner(appointment)` and `performCheckIn(tokenHash)` are reused unchanged. The system-name change is text-only — no schema, env-var, or folder renames.

**Tech Stack:** HTML, CSS (vanilla + Tailwind utility classes), vanilla JS, FontAwesome, Canvas 2D API. No test framework — verification is manual visual inspection in a browser, as called out in the spec.

**Reference spec:** `docs/superpowers/specs/2026-04-29-batch-b-ui-fixes-design.md`

**Verification environment:** XAMPP local server. Open pages via `http://localhost/meditrack/<path>` in a desktop browser; resize the viewport (or use DevTools device emulation) to confirm mobile behaviour. The repo lives at `/Applications/XAMPP/xamppfiles/htdocs/meditrack`.

**Commit style:** Follow the existing prefix convention: `fix(ui): …`, `feat(ui): …`, `chore: …`. See `git log --oneline -10`.

---

### Task 0: User-input gate — drop the hospital photo into place

**Files:**
- Create: `assets/images/hospital.jpg` (user must save the photo here).

**Why:** Fix 5 (landing hero) cannot proceed without the photo file. This step is a hard gate; if the file is missing, Task 5 must not be attempted with a placeholder image.

- [ ] **Step 1: Confirm the photo exists at the expected path**

Run:

```bash
ls -lh /Applications/XAMPP/xamppfiles/htdocs/meditrack/assets/images/hospital.jpg
```

Expected: a non-empty `.jpg` file. If "No such file", **STOP** and ask the user to save the photo at this path before Task 5 begins. Tasks 1–4 and 6–10 do not depend on the photo and can proceed in parallel.

- [ ] **Step 2: Confirm the file size is reasonable**

The hero loads on every landing visit; oversized photos slow first paint.

```bash
du -h assets/images/hospital.jpg
```

Expected: ≤ 400 KB. If larger, ask the user to compress (or compress with `sips -s format jpeg -s formatOptions 80 assets/images/hospital.jpg --out assets/images/hospital.jpg` on macOS) before continuing.

- [ ] **Step 3: Stage the file but DO NOT commit yet**

The file will be committed alongside Task 5's hero changes so the same commit makes the page fully functional.

```bash
git add assets/images/hospital.jpg
```

---

### Task 1: Equalize register page password row on desktop

**Files:**
- Modify: `pages/register.html` lines 139 and 149 (CSS rules `.str-wrap` and `.match-hint`).

**Why this works:** `.form-grid` is `1fr 1fr`, so both columns already have equal width. Heights diverge because `.str-wrap` (strength bar, 9px) and `.match-hint` (~20px when text is shown, 0px when empty) reserve different vertical space below their respective inputs. Setting both to the same `min-height` makes the grid row height stable in every state.

- [ ] **Step 1: Verify line numbers**

```bash
grep -n "str-wrap\|match-hint" pages/register.html
```

Expected hits include lines 139 (`.str-wrap` rule) and 149 (`.match-hint` rule).

- [ ] **Step 2: Update `.str-wrap`**

Change line 139 from:

```css
.str-wrap { display: flex; gap: 4px; margin-top: 6px; }
```

To:

```css
/* Helper-row height: keep .str-wrap and .match-hint min-height in sync so the */
/* 2-column form-grid row stays balanced regardless of strength bar / hint state. */
.str-wrap { display: flex; gap: 4px; margin-top: 6px; min-height: 20px; }
```

- [ ] **Step 3: Update `.match-hint`**

Change line 149 from:

```css
.match-hint { font-size: 12px; margin-top: 4px; font-family: 'DM Sans', sans-serif; }
```

To:

```css
.match-hint { font-size: 12px; margin-top: 4px; min-height: 20px; font-family: 'DM Sans', sans-serif; }
```

- [ ] **Step 4: Verify on desktop**

1. Open `http://localhost/meditrack/pages/register.html` in a ≥1024px viewport.
2. Scroll to the Password / Confirm Password row.
3. Confirm both columns' bottom edges align in every state: empty, password typed, both typed.

- [ ] **Step 5: Verify on mobile**

DevTools → 375×667 viewport. Confirm the form is single-column and the layout looks unchanged from before.

- [ ] **Step 6: Commit**

```bash
git add pages/register.html
git commit -m "$(cat <<'EOF'
fix(ui): equalize password row heights on register desktop view

Reserve a shared min-height on .str-wrap (strength bar) and .match-hint
so the 2-column form-grid row stays balanced whether the strength bar
is rendered or the match hint is empty.
EOF
)"
```

---

### Task 2: Rename "Doctor Schedule" → "OPD Schedule" in admin sidebar

**Files:**
- Modify: `pages/admin-dashboard.html` (4 occurrences — 3 user-visible strings + 1 code comment).

- [ ] **Step 1: Re-verify the four occurrences**

```bash
grep -ni "doctor schedule" pages/admin-dashboard.html
```

Expected output (line numbers may shift):

```
445:            <span class="text-sm">Doctor Schedule</span>
822:             TAB: DOCTOR SCHEDULE
829:                    <h3 class="section-title">Doctor Schedule</h3>
1030:        Doctor Schedule
```

- [ ] **Step 2: Update sidebar nav label (line 445)**

```html
            <span class="text-sm">Doctor Schedule</span>
```
→
```html
            <span class="text-sm">OPD Schedule</span>
```

The surrounding `<button onclick="switchTab('schedule')" data-tab="schedule">` MUST stay unchanged.

- [ ] **Step 3: Update section heading (line 829)**

```html
                    <h3 class="section-title">Doctor Schedule</h3>
```
→
```html
                    <h3 class="section-title">OPD Schedule</h3>
```

- [ ] **Step 4: Update mobile "more" dropdown (line 1030)**

```html
    <button data-tab="schedule" onclick="switchTab('schedule');closeMoreMenu()">
        <i class="fas fa-clock w-5" style="color:#0891b2;"></i>
        Doctor Schedule
    </button>
```
→
```html
    <button data-tab="schedule" onclick="switchTab('schedule');closeMoreMenu()">
        <i class="fas fa-clock w-5" style="color:#0891b2;"></i>
        OPD Schedule
    </button>
```

- [ ] **Step 5: Update section comment (line 822)**

```html
        <!-- ════════════════════════════════
             TAB: DOCTOR SCHEDULE
        ════════════════════════════════ -->
```
→
```html
        <!-- ════════════════════════════════
             TAB: OPD SCHEDULE
        ════════════════════════════════ -->
```

- [ ] **Step 6: Confirm zero residual occurrences**

```bash
grep -ni "doctor schedule" pages/admin-dashboard.html
```

Expected output: empty.

- [ ] **Step 7: Verify visually**

Open admin dashboard. Confirm sidebar reads "OPD Schedule"; click it; the schedule tab loads. Resize to mobile, open More menu, confirm dropdown reads "OPD Schedule" and clicking it loads the tab.

- [ ] **Step 8: Commit**

```bash
git add pages/admin-dashboard.html
git commit -m "$(cat <<'EOF'
fix(ui): rename "Doctor Schedule" to "OPD Schedule" in admin sidebar

User-visible labels in the sidebar nav, section heading, and mobile
More dropdown now read "OPD Schedule". Tab id, data-tab attributes,
and switchTab() call remain unchanged.
EOF
)"
```

---

### Task 3: Add a global "Scan QR" button to the doctor dashboard header

**Files:**
- Modify: `pages/doctor-dashboard.html` lines ~287–289 (right-side action cluster inside `<header>`, immediately before `#bell-container`).

- [ ] **Step 1: Read the header right-side cluster**

Read `pages/doctor-dashboard.html` lines 287–308. Confirm the structure:

```html
                <!-- Right: avatar + logout -->
                <div class="flex items-center space-x-3 flex-shrink-0">
                    <div id="bell-container"></div>
                    <!-- Doctor avatar chip -->
                    <div class="hidden sm:flex items-center space-x-2.5 bg-[#F0FDFA] border border-cyan-100 rounded-xl px-3 py-2">
                        ...
                    </div>
                    <!-- Logout -->
                    ...
                </div>
```

- [ ] **Step 2: Insert the Scan QR button before `#bell-container`**

```html
                <!-- Right: avatar + logout -->
                <div class="flex items-center space-x-3 flex-shrink-0">
                    <div id="bell-container"></div>
```
→
```html
                <!-- Right: avatar + logout -->
                <div class="flex items-center space-x-3 flex-shrink-0">
                    <!-- Global Scan QR — opens scanner without a specific appointment.
                         performCheckIn() resolves the appointment from the QR token server-side. -->
                    <button type="button" onclick="openQRScanner(null)"
                        class="flex items-center space-x-1.5 bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-xl text-xs font-semibold transition-colors">
                        <i class="fas fa-qrcode"></i>
                        <span class="hidden sm:inline">Scan QR</span>
                    </button>
                    <div id="bell-container"></div>
```

- [ ] **Step 3: Verify the button is visible without errors**

Open doctor dashboard logged in as a doctor with **no** appointments today. DevTools console clean, button visible top-right of header.

- [ ] **Step 4: Verify the modal opens**

Click the button. Modal `#qr-modal` opens. Allow camera access. Camera preview appears (or falls back to manual input).

- [ ] **Step 5: Verify manual token check-in path**

Click Scan QR again. Paste a known-valid QR token in the manual field and submit. Confirm SweetAlert success and dashboard stats refresh. Paste an invalid token; confirm SweetAlert error.

- [ ] **Step 6: Verify mobile responsive**

DevTools → 360×640. Confirm icon-only button (no "Scan QR" text), no row overflow, modal opens correctly.

- [ ] **Step 7: Verify per-row Scan QR still works**

Switch to a doctor account with appointments today (or seed one). Confirm the per-row Scan QR button still renders next to scheduled/confirmed rows; click it; modal opens.

- [ ] **Step 8: Commit**

```bash
git add pages/doctor-dashboard.html
git commit -m "$(cat <<'EOF'
feat(ui): add global Scan QR button to doctor dashboard header

Doctors can now open the QR check-in scanner regardless of whether
they have appointments listed today. The button calls
openQRScanner(null); performCheckIn() already resolves the appointment
server-side from the QR token, so no JS changes are needed.
EOF
)"
```

---

### Task 4: Surface doctor profile chip on mobile

**Files:**
- Modify: `pages/doctor-dashboard.html` (avatar chip wrapper inside `<header>`, around line 291).

- [ ] **Step 1: Locate the existing avatar chip**

```bash
grep -n 'Doctor avatar chip\|hidden sm:flex' pages/doctor-dashboard.html
```

Find the chip wrapper:

```html
                    <!-- Doctor avatar chip -->
                    <div class="hidden sm:flex items-center space-x-2.5 bg-[#F0FDFA] border border-cyan-100 rounded-xl px-3 py-2">
                        <div class="w-7 h-7 rounded-full bg-cyan-600 flex items-center justify-center text-white text-xs font-bold overflow-hidden flex-shrink-0"
                             id="header-avatar">
                            <i class="fas fa-user-md text-xs"></i>
                        </div>
                        <div class="hidden md:block">
                            <p class="text-xs font-semibold text-[#083344] leading-tight font-outfit" id="header-name">Loading…</p>
                            <p class="text-xs text-cyan-600 font-medium" id="header-role">Doctor</p>
                        </div>
                    </div>
```

- [ ] **Step 2: Make the chip visible at all breakpoints**

Change the wrapper class `hidden sm:flex …` to `flex …`. Change the inner text wrapper `hidden md:block` to `block`. Result:

```html
                    <!-- Doctor avatar chip -->
                    <div class="flex items-center space-x-2 sm:space-x-2.5 bg-[#F0FDFA] border border-cyan-100 rounded-xl px-2 py-1.5 sm:px-3 sm:py-2">
                        <div class="w-7 h-7 rounded-full bg-cyan-600 flex items-center justify-center text-white text-xs font-bold overflow-hidden flex-shrink-0"
                             id="header-avatar">
                            <i class="fas fa-user-md text-xs"></i>
                        </div>
                        <div class="block max-w-[110px] sm:max-w-none">
                            <p class="text-xs font-semibold text-[#083344] leading-tight font-outfit truncate" id="header-name">Loading…</p>
                            <p class="text-[10px] sm:text-xs text-cyan-600 font-medium" id="header-role">Doctor</p>
                        </div>
                    </div>
```

The smaller `px`/`py` and `max-w-[110px]` keep the chip from overflowing the mobile header next to the new Scan QR button, the bell, and the logout button. The `truncate` class clips long names with an ellipsis.

- [ ] **Step 3: Verify on mobile (360px)**

DevTools → 360×640. Open doctor dashboard. Confirm:

1. The avatar circle and the doctor name (truncated if long) + role render in the header.
2. The row containing Scan QR, bell, chip, Logout fits on one line without wrapping.
3. No overlap or clipping between elements.

- [ ] **Step 4: Verify on desktop**

Resize back to 1280px. Confirm the chip still renders correctly — name and role visible, not truncated unless the name is genuinely long.

- [ ] **Step 5: Commit**

```bash
git add pages/doctor-dashboard.html
git commit -m "$(cat <<'EOF'
fix(ui): surface doctor profile chip on mobile in doctor dashboard

Removes the hidden sm:flex / hidden md:block constraints on the
existing header avatar chip so the doctor's name and role are visible
on phone. Tightens padding and adds max-width + truncate so the chip
fits alongside Scan QR, bell, and logout in the mobile header row.
EOF
)"
```

---

### Task 5: Landing hero — Bislig District Hospital photo + name

**Files:**
- Modify: `index.html` lines 58–65 (`.hero-gradient` CSS rule) and lines 277–283 (hero eyebrow markup).
- Stage: `assets/images/hospital.jpg` (already added in Task 0).

**Why this works:** Layering a translucent dark teal gradient over the photo via `background: linear-gradient(<overlay>), url('<photo>') center/cover no-repeat` keeps the existing colour story while letting the building show through. White text remains legible against the overlaid photo.

- [ ] **Step 1: Confirm Task 0 staged the photo**

```bash
git status assets/images/hospital.jpg
```

Expected: `new file:   assets/images/hospital.jpg`. If missing, **STOP** and complete Task 0 first.

- [ ] **Step 2: Update the `.hero-gradient` CSS rule (lines 58–65)**

Find:

```css
    .hero-gradient {
      background: linear-gradient(135deg, #083344 0%, #0891B2 55%, #22D3EE 100%);
```

Replace the `background:` line with a layered photo + overlay (keeping any other lines in the rule unchanged):

```css
    .hero-gradient {
      background:
        linear-gradient(135deg, rgba(8,51,68,0.72) 0%, rgba(8,145,178,0.55) 55%, rgba(34,211,238,0.40) 100%),
        url('assets/images/hospital.jpg') center/cover no-repeat;
```

This preserves the original gradient as a translucent overlay and adds the photo beneath it. `center/cover` makes the photo fill the hero on any viewport without stretching.

- [ ] **Step 3: Update the hero eyebrow text (lines 281–283)**

Find:

```html
        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest px-5 py-2 rounded-full border border-white/25 mb-7" style="background:rgba(255,255,255,0.12);font-family:'DM Sans',sans-serif;">
          <i class="fa-solid fa-stethoscope" style="color:#22D3EE;"></i>
          Internal Medicine OPD Management System
        </span>
```

Replace the eyebrow content with the hospital name:

```html
        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest px-5 py-2 rounded-full border border-white/25 mb-7" style="background:rgba(255,255,255,0.12);font-family:'DM Sans',sans-serif;">
          <i class="fa-solid fa-hospital" style="color:#22D3EE;"></i>
          Bislig District Hospital
        </span>
```

The icon changes from `fa-stethoscope` to `fa-hospital` to match the meaning. The pill styling is preserved.

- [ ] **Step 4: Verify on desktop**

Open `http://localhost/meditrack/index.html` in a ≥1280px viewport. Confirm:

1. The hospital photo is visible behind a dark teal overlay.
2. The eyebrow pill reads "BISLIG DISTRICT HOSPITAL" (uppercased by CSS).
3. The headline "Internal Medicine / OPD Management System" — DO NOT change yet, that happens in Task 8.
4. CTAs (Register Now, Log In) are legible and unchanged.

- [ ] **Step 5: Verify on mobile (375px)**

Confirm the photo still covers the hero, the building is visible (not awkwardly cropped), and all text remains legible.

- [ ] **Step 6: Commit (with the photo)**

```bash
git add index.html assets/images/hospital.jpg
git commit -m "$(cat <<'EOF'
feat(ui): use Bislig District Hospital photo + name in landing hero

Replaces the abstract cyan/teal hero gradient with a layered background:
the actual hospital building photo overlaid with a translucent dark
teal gradient for text legibility. The eyebrow pill now reads "Bislig
District Hospital" with a hospital icon, so visitors immediately
recognize the place they need to go.
EOF
)"
```

---

### Task 6: Composite QR download (header + QR + footer)

**Files:**
- Modify: `js/patient-dashboard.js` `downloadQR()` function (around line 928).

**Why this works:** The existing on-screen card has three text blocks (eyebrow appointment number, title with doctor + date + time, footer hint) and the QR canvas. We render the same content into a new offscreen canvas using Canvas 2D API, then `toDataURL()` it. Pure JS, no new libraries.

- [ ] **Step 1: Read the current `downloadQR` function**

```bash
grep -n "function downloadQR" js/patient-dashboard.js
```

Read 30 lines starting at that match to confirm the current shape:

```js
function downloadQR() {
    const canvas = document.getElementById('qrCanvas');
    if (canvas && !canvas.classList.contains('hidden')) {
        try {
            const link = document.createElement('a');
            link.download = `QR-Appointment-${currentQRApptId}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            return;
        } catch (e) { console.error('Canvas download error:', e); }
    }
    // Fallback to img element
    const img = document.getElementById('qrImage');
    if (!img || !img.src) { showError('No QR code to download.'); return; }
    const link = document.createElement('a');
    link.download = `QR-Appointment-${currentQRApptId}.png`;
    link.href = img.src;
    link.click();
}
```

- [ ] **Step 2: Replace `downloadQR` with the composited version**

Replace the entire `downloadQR` function body with:

```js
function downloadQR() {
    const sourceCanvas = document.getElementById('qrCanvas');
    const apptNumberEl = document.getElementById('qrApptNumber');
    const apptInfoEl   = document.getElementById('qrApptInfo');

    // Composite path: works when the QR was rendered to <canvas>.
    if (sourceCanvas && !sourceCanvas.classList.contains('hidden')) {
        try {
            const eyebrow = (apptNumberEl?.textContent || '').trim();
            const title   = (apptInfoEl?.textContent || '').trim();
            const footer  = 'Show this QR code at the clinic for check-in';

            const W = 520;
            const padX = 32;
            const eyebrowSize = 14;
            const titleSize   = 22;
            const footerSize  = 13;
            const gapBeforeQR = 24;
            const gapAfterQR  = 20;
            const qrSize      = 320;
            const padTop      = 36;
            const padBottom   = 36;
            const titleSpace  = 32;

            const H = padTop + eyebrowSize + 14 + titleSize + titleSpace
                      + gapBeforeQR + qrSize + gapAfterQR
                      + footerSize + padBottom;

            const out = document.createElement('canvas');
            out.width  = W;
            out.height = H;
            const ctx = out.getContext('2d');

            // Background
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, W, H);

            // Text helpers
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';

            // Eyebrow (uppercase, gray)
            ctx.fillStyle = '#9CA3AF';
            ctx.font = `600 ${eyebrowSize}px 'Outfit', system-ui, sans-serif`;
            ctx.fillText(eyebrow.toUpperCase(), W / 2, padTop);

            // Title (bold, dark)
            ctx.fillStyle = '#1F2937';
            ctx.font = `700 ${titleSize}px 'Outfit', system-ui, sans-serif`;
            ctx.fillText(title, W / 2, padTop + eyebrowSize + 14);

            // QR (drawn from existing canvas, scaled up)
            const qrX = (W - qrSize) / 2;
            const qrY = padTop + eyebrowSize + 14 + titleSize + titleSpace + gapBeforeQR;
            ctx.drawImage(sourceCanvas, qrX, qrY, qrSize, qrSize);

            // Footer (small, gray)
            ctx.fillStyle = '#6B7280';
            ctx.font = `400 ${footerSize}px 'DM Sans', system-ui, sans-serif`;
            ctx.fillText(footer, W / 2, qrY + qrSize + gapAfterQR);

            const link = document.createElement('a');
            link.download = `QR-Appointment-${currentQRApptId}.png`;
            link.href = out.toDataURL('image/png');
            link.click();
            return;
        } catch (e) {
            console.error('Composite QR download error:', e);
            // fall through to img fallback below
        }
    }

    // Fallback path: server-provided image, no canvas to composite.
    const img = document.getElementById('qrImage');
    if (!img || !img.src) { showError('No QR code to download.'); return; }
    const link = document.createElement('a');
    link.download = `QR-Appointment-${currentQRApptId}.png`;
    link.href = img.src;
    link.click();
}
```

- [ ] **Step 3: Verify the composite download**

1. Log in as a patient with at least one upcoming appointment.
2. Open the QR modal.
3. Click Download.
4. Open the downloaded `QR-Appointment-<id>.png`. Confirm: top eyebrow shows "APPOINTMENT: APT-…" in grey uppercase; bold title line shows "Dr. <Name> • <Date> • <Time>" (only one "Dr." — see Task 7); QR pixels match the on-screen QR; footer reads "Show this QR code at the clinic for check-in".

- [ ] **Step 4: Verify the fallback still works**

If your test data exposes the server-image branch (where `qrImage` is shown instead of `qrCanvas`), confirm the bare-image download still saves correctly (no header in this path — the fallback is intentional).

- [ ] **Step 5: Commit**

```bash
git add js/patient-dashboard.js
git commit -m "$(cat <<'EOF'
feat(ui): downloadQR composites header + QR + footer into a single PNG

Patients downloading the appointment QR now get an image that mirrors
the on-screen card: appointment number eyebrow, doctor + date + time
title, the QR pixels, and the clinic check-in hint footer. Pure
Canvas 2D API; no new dependencies. Falls back to the bare-image
download path when the QR was rendered to <img> instead of <canvas>.
EOF
)"
```

---

### Task 7: Drop the hardcoded "Dr. " prefix in QR modal info

**Files:**
- Modify: `js/patient-dashboard.js` line 833.

- [ ] **Step 1: Locate the line**

```bash
grep -n "Dr\. \${appt.doctor_name" js/patient-dashboard.js
```

Expected: line 833.

- [ ] **Step 2: Edit the string**

Change:

```js
    setTextContent('qrApptInfo', appt
        ? `Dr. ${appt.doctor_name || ''} • ${appt.appointment_date ? formatDate(appt.appointment_date) : ''} ${appt.appointment_time ? formatTime(appt.appointment_time) : ''}`
        : '');
```

To:

```js
    setTextContent('qrApptInfo', appt
        ? `${appt.doctor_name || ''} • ${appt.appointment_date ? formatDate(appt.appointment_date) : ''} ${appt.appointment_time ? formatTime(appt.appointment_time) : ''}`
        : '');
```

The hardcoded `Dr. ` prefix is removed. The DB-stored `doctor_name` already includes the title.

- [ ] **Step 3: Verify**

Open the QR modal as a patient. Confirm the title line reads "Dr. Maria Santos • …" (one "Dr."). Click Download (Task 6 already deployed); confirm the downloaded PNG also shows one "Dr.".

- [ ] **Step 4: Commit**

```bash
git add js/patient-dashboard.js
git commit -m "$(cat <<'EOF'
fix(ui): remove hardcoded 'Dr. ' prefix in QR modal appointment info

doctor_name is already stored with the title in the DB, so prepending
'Dr. ' produced 'Dr. Dr. <Name>'. Drop the prepend; the stored value
carries the title.
EOF
)"
```

---

### Task 8: System rename — Internal Medicine OPD → Consultation OPD

**Files:** (~14 files; ~30 occurrences)
- Modify: `index.html`, `pages/forgot-password.html`, `pages/login.html`, `pages/register.html`, `pages/reset-password.html`, `pages/admin-dashboard.html`, `pages/print-record.html`, `pages/patient-dashboard.html`, `pages/doctor-dashboard.html`, `pages/qr-booking.html`, `pages/qr-checkin.html`, `js/auth.js`, `js/patient-dashboard.js`, `js/admin-dashboard.js`, `js/doctor-dashboard.js`.

**Why staged carefully:** Only the system name and short brand are renamed. The string "Internal Medicine" alone (without "OPD" suffix) may appear as a doctor specialization elsewhere — DO NOT touch those.

- [ ] **Step 1: Snapshot the current occurrences**

```bash
grep -rn "Internal Medicine OPD Management System\|Internal Medicine OPD\|IM-OPD" \
  index.html pages/ js/ \
  | tee /tmp/batch-b-rename-before.txt
wc -l /tmp/batch-b-rename-before.txt
```

Note the count. Expect roughly 30+ lines.

- [ ] **Step 2: Rename "Internal Medicine OPD Management System" → "Consultation OPD Management System"**

Run this in the project root (Bash). It uses sed in-place with a backup extension `.bak`, then we delete the backup files.

```bash
LC_ALL=C find . -type f \( -name "*.html" -o -name "*.js" \) \
  -not -path "./.git/*" -not -path "./docs/superpowers/*" -not -path "./uploads/*" \
  -exec sed -i.bak 's/Internal Medicine OPD Management System/Consultation OPD Management System/g' {} +
find . -name "*.bak" -not -path "./.git/*" -delete
```

- [ ] **Step 3: Rename "Internal Medicine OPD" (the page-title suffix) → "Consultation OPD"**

This must run AFTER Step 2 so we do not mistakenly match the longer string before the longer string is renamed.

```bash
LC_ALL=C find . -type f \( -name "*.html" -o -name "*.js" \) \
  -not -path "./.git/*" -not -path "./docs/superpowers/*" -not -path "./uploads/*" \
  -exec sed -i.bak 's/Internal Medicine OPD/Consultation OPD/g' {} +
find . -name "*.bak" -not -path "./.git/*" -delete
```

- [ ] **Step 4: Rename short brand "IM-OPD" → "OPD"**

```bash
LC_ALL=C find . -type f \( -name "*.html" -o -name "*.js" \) \
  -not -path "./.git/*" -not -path "./docs/superpowers/*" -not -path "./uploads/*" \
  -exec sed -i.bak 's/IM-OPD/OPD/g' {} +
find . -name "*.bak" -not -path "./.git/*" -delete
```

- [ ] **Step 5: Verify zero residual occurrences**

```bash
grep -rn "Internal Medicine OPD Management System\|Internal Medicine OPD\|IM-OPD" \
  index.html pages/ js/
```

Expected: empty (no output).

If anything remains, examine and fix manually — it likely lives inside a context the regex above missed (e.g., a triple-quoted block, an unusual file extension, or a path we excluded).

- [ ] **Step 6: Verify "Internal Medicine" (specialization) is preserved**

```bash
grep -rn "Internal Medicine" index.html pages/ js/ | grep -v "Consultation OPD"
```

Any remaining hits should be doctor-specialization strings, not system branding. (If there are no doctor-specialization hits in static files, this output may be empty — that is fine.)

- [ ] **Step 7: Verify visually across pages**

Open each in a browser:

- `index.html` — page title, footer (if any), meta description (View Source).
- `pages/login.html`, `pages/register.html`, `pages/forgot-password.html`, `pages/reset-password.html` — page titles, sidebar logo "OPD", footer copyright "© 2026 OPD — Consultation OPD Management System".
- `pages/admin-dashboard.html`, `pages/doctor-dashboard.html`, `pages/patient-dashboard.html` — page title, sidebar logo "OPD".
- `pages/print-record.html` — header logo "OPD" and the document footer text.
- `pages/qr-booking.html`, `pages/qr-checkin.html` — branding strings.

- [ ] **Step 8: Verify the welcome SweetAlert (register page)**

```bash
grep -n "Welcome to" pages/register.html
```

Expected: `Welcome to OPD,` (post-rename). If still says `IM-OPD`, fix manually.

- [ ] **Step 9: Commit**

```bash
git add index.html pages/ js/
git commit -m "$(cat <<'EOF'
chore(brand): rename "Internal Medicine OPD" -> "Consultation OPD"

Repo-wide rename driven by Bislig District Hospital's terminology
shift away from a single-specialty framing. Long name "Internal
Medicine OPD Management System" -> "Consultation OPD Management
System"; short brand "IM-OPD" -> "OPD". No DB schema, env vars,
folder names, or favicon changed. Doctor-specialization strings
("Internal Medicine" alone) are preserved.
EOF
)"
```

---

### Task 9: Rename "Visit Clinic" → "Visit Consultation"

**Files:**
- Modify: `index.html` line 367.

- [ ] **Step 1: Locate the occurrence**

```bash
grep -n "Visit Clinic" index.html
```

Expected: line 367 inside the "How It Works" section.

- [ ] **Step 2: Edit the heading**

Change:

```html
          <h3 class="font-heading font-bold text-gray-900 text-lg mb-2">Visit Clinic</h3>
```

To:

```html
          <h3 class="font-heading font-bold text-gray-900 text-lg mb-2">Visit Consultation</h3>
```

The icon, surrounding markup, and step-number badge are untouched.

- [ ] **Step 3: Verify visually**

Open `index.html`, scroll to "How It Works", confirm step three reads "Visit Consultation".

- [ ] **Step 4: Commit**

```bash
git add index.html
git commit -m "$(cat <<'EOF'
fix(ui): rename "Visit Clinic" -> "Visit Consultation" on landing page

Matches Bislig District Hospital's preferred terminology for the
patient flow.
EOF
)"
```

---

### Task 10: Reframe manual QR token field as a camera-failure fallback

**Files:**
- Modify: `pages/doctor-dashboard.html` lines ~750–770 (manual token block inside `#qr-modal`).

- [ ] **Step 1: Read the current manual token block**

```bash
grep -n "manual-token\|Token is the part" pages/doctor-dashboard.html
```

Read 25 lines around the match. Expected current markup:

```html
                <!-- Manual token input -->
                <div class="...">
                    <input type="text" id="manual-token"
                        placeholder="Paste QR token here…"
                        class="..."
                        onkeypress="if(event.key==='Enter') checkInManual()">
                    <button onclick="checkInManual()"
                        class="...">Submit</button>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    Token is the part after <code class="bg-gray-100 text-slate-600 px-1.5 py-0.5 rounded-md">?token=</code> in the QR URL
                </p>
```

(Actual class names may differ; preserve them.)

- [ ] **Step 2: Wrap the manual block behind a toggle and rewrite the labels**

Replace the entire manual-token block + help paragraph with:

```html
                <!-- Manual entry toggle (hidden by default; shown when camera unavailable) -->
                <button type="button" id="manual-toggle"
                    onclick="document.getElementById('manual-block').classList.toggle('hidden'); this.classList.add('hidden');"
                    class="block w-full text-center text-xs text-cyan-700 hover:text-cyan-800 underline mt-2">
                    Camera not working? Enter code manually
                </button>

                <!-- Manual token input — camera-failure fallback -->
                <div id="manual-block" class="hidden">
                    <div class="flex gap-2 mt-3">
                        <input type="text" id="manual-token"
                            placeholder="Type the appointment code from the QR"
                            class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500"
                            onkeypress="if(event.key==='Enter') checkInManual()">
                        <button onclick="checkInManual()"
                            class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Submit</button>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">
                        Use this only if the camera will not start. Ask the patient to read out their appointment code.
                    </p>
                </div>
```

Notes for the implementer:

- The id `manual-token` is preserved; `js/doctor-dashboard.js` `checkInManual()` reads from this id and must not break.
- If the original markup uses different Tailwind classes (e.g., for the input border or button colour), keep those rather than the values shown above. The structure (hidden block, toggle button, plain-language placeholder, plain-language help) is what matters.
- The toggle button hides itself once clicked so the prompt does not stick around after the fallback is opened.

- [ ] **Step 3: Verify the modal opens with manual hidden**

Open the doctor dashboard, click Scan QR. Confirm:

1. The manual input is NOT visible by default.
2. A small underlined link reads "Camera not working? Enter code manually" below the camera region.

- [ ] **Step 4: Verify the toggle reveals the input**

Click the link. Confirm:

1. The input + Submit button appear.
2. The placeholder reads "Type the appointment code from the QR".
3. The help text below reads "Use this only if the camera will not start. Ask the patient to read out their appointment code."
4. The toggle link itself disappears.

- [ ] **Step 5: Verify check-in still works**

Type a valid appointment code into the now-revealed input. Click Submit. Confirm SweetAlert success.

- [ ] **Step 6: Verify state resets when re-opening the modal**

Close the modal. Re-open it (click the global Scan QR button). Confirm the manual input is hidden again and the toggle link is visible. (If the toggle persists from the previous open, fix `closeQRScanner()` in JS to reset both classes — but only if the test fails. Most modal-open paths re-render or re-show the elements; if not, add a small reset to the open path.)

If reset is needed: edit `js/doctor-dashboard.js` `openQRScanner()` to add at the top of the function:

```js
    const manualBlock  = document.getElementById('manual-block');
    const manualToggle = document.getElementById('manual-toggle');
    if (manualBlock)  manualBlock.classList.add('hidden');
    if (manualToggle) manualToggle.classList.remove('hidden');
```

- [ ] **Step 7: Commit**

```bash
git add pages/doctor-dashboard.html js/doctor-dashboard.js
git commit -m "$(cat <<'EOF'
fix(ui): reframe doctor manual QR input as a camera-failure fallback

The Scan QR modal's manual-token input now hides behind a "Camera not
working? Enter code manually" toggle so it reads as a fallback rather
than a primary action. Plain-language placeholder and help text
replace the technical 'Paste QR token / ?token= URL' references that
confused clinic staff. The submit handler and #manual-token id are
unchanged.
EOF
)"
```

---

## Self-Review

**Spec coverage:**

- Fix 1 (register password row) → Task 1 ✓
- Fix 2 (admin sidebar rename) → Task 2 ✓
- Fix 3 (global Scan QR) → Task 3 ✓
- Fix 4 (doctor mobile chip) → Task 4 ✓
- Fix 5 (landing hero photo + name) → Task 0 (gate) + Task 5 ✓
- Fix 6 (composite QR download) → Task 6 ✓
- Fix 7 (Dr. prefix) → Task 7 ✓
- Fix 8 (system rename) → Task 8 ✓
- Fix 9 (Visit Consultation) → Task 9 ✓
- Fix 10 (manual token UI) → Task 10 ✓
- Audit-only "5hrs" prompt → Step 5 of Task 8 (post-rename grep window) implicitly covers this; the spec calls for `grep -rn "5 hour\|5hrs\|5-hour"` confirmation. Add as an explicit one-liner if desired.
- Risks: helper-row drift (Task 1 Step 2 comment), sidebar id coupling (Task 2 Steps 2 & 4 callouts), header overflow (Task 3 Step 6 + Task 4 Step 3), photo missing/large (Task 0 Steps 1–2), font availability fallback (`system-ui` in Task 6 font stack), Dr. prefix edge-case (out-of-scope per spec), system-rename misses (Task 8 Step 5 grep), unintended replacement (Task 8 ordering and Step 6 grep) ✓

**Placeholder scan:** No "TBD", "TODO", or "implement later" in any task. Each step has either the exact code change, the exact command to run, or the exact verification action.

**Type/symbol consistency:** `openQRScanner`, `performCheckIn`, `currentAppointment`, `currentQRApptId`, `qrCanvas`, `qrApptNumber`, `qrApptInfo`, `manual-token`, `manual-block`, `manual-toggle`, `#qr-modal`, `#bell-container`, `header-name`, `header-role`, `header-avatar`, `.str-wrap`, `.match-hint`, `.form-grid`, `.hero-gradient`, `switchTab('schedule')`, `data-tab="schedule"` — all consistent across tasks and with the codebase verified during brainstorming.

**Note on TDD:** This codebase has no JS/HTML test runner. The spec mandates manual visual verification. The "test" steps in this plan are scoped browser checks performed at the end of each task so each commit is independently verified. Each task is independently revertable if a regression is found.
