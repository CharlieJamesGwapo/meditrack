# Login & Register Page Redesign

## Goal

Redesign the MediTrack login and register pages to be more responsive, professional, and user-friendly. Replace the current centered-card layout with a split-screen design and convert the long register form into a multi-step wizard.

## Scope

Files affected:
- `pages/login.html` — full redesign
- `pages/register.html` — full redesign with wizard conversion
- `pages/forgot-password.html` — visual consistency update
- `pages/verify-otp.html` — visual consistency update
- `pages/reset-password.html` — visual consistency update

## Login Page

### Layout: Split-Screen

**Desktop (>768px):**
- Left panel (~45% width): Dark green gradient (`#065f46` → `#047857` → `#059669`) with:
  - MediTrack logo in frosted glass container (rounded square, `rgba(255,255,255,0.15)` background)
  - App name in Poppins 26px bold
  - Tagline: "Your trusted healthcare management system for the Philippines"
  - Feature list with green checkmark icons: "Easy appointment booking", "Secure medical records", "QR code check-in"
  - Decorative semi-transparent circles in background
- Right panel (~55% width): White background with:
  - "Welcome back" heading (Poppins 22px bold)
  - "Sign in to your account" subtitle
  - Username field with user icon
  - Password field with lock icon and visibility toggle
  - Password strength indicator (4-segment bar)
  - Remember me checkbox + Forgot password link (same row)
  - "Sign In" button (green gradient, full width)
  - "or" divider
  - "Don't have an account? Create one" outlined button

**Mobile (<768px):**
- Stacks vertically
- Green header becomes compact: logo (44px), app name (20px), one-line subtitle
- Feature list hidden on mobile
- Form section below with full-width fields stacked single-column
- All input fields minimum 42px height for touch targets

### Preserved Functionality
- Real-time clock display
- Password visibility toggle
- Password strength checker (5 criteria)
- Rate limiting (5 attempts, 5-minute lockout)
- Input sanitization (XSS prevention)
- Async login to `../api/auth/login.php`
- Session token creation
- Role-based redirect (patient/doctor/reception/admin)
- Security meta headers
- Context menu and keyboard shortcut blocking

## Register Page

### Layout: Split-Screen with 4-Step Wizard

**Desktop (>768px):**
- Left panel (~43% width): Same dark green gradient as login, containing:
  - MediTrack logo + name (horizontal layout)
  - "Create your account" subtitle
  - Vertical step progress indicator:
    - Each step: numbered circle (32px) + label + description
    - Active step: green circle with outer glow ring, white text
    - Completed step: green circle with checkmark, green label text, "Completed" subtitle
    - Upcoming step: semi-transparent circle with border, dimmed text
    - Steps connected by vertical lines (green for completed, dim for upcoming)
  - "Already have an account? Sign in" link at bottom
- Right panel (~57% width): Light gray background (`#f9fafb`) with:
  - Step title (Poppins 20px bold) + description
  - Form fields for current step only
  - Back/Next navigation buttons at bottom

**Mobile (<768px):**
- Left panel replaced with compact header:
  - Logo + app name on left, "Step X/4" on right
  - Horizontal 4-segment progress bar below (green = completed/active, dim = upcoming)
- Form section below with single-column stacked fields
- Back/Next buttons full width

### Step 1: Account & Profile
- Profile picture upload area:
  - Dashed border container with camera icon placeholder (72px circle)
  - "Profile Picture (2x2)" label
  - Upload and Camera buttons side by side
  - On mobile: 56px circle, compact layout
- Username field (half width on desktop)
- Email field (half width on desktop)
- Password field (half width on desktop)
- Confirm Password field (half width on desktop)
- Password strength indicator bar
- Navigation: "Next Step →" button only

### Step 2: Personal Info
- Full Name field (half width)
- Date of Birth field (half width)
- Gender dropdown (half width)
- Contact Number field (half width)
- Blood Group dropdown (half width)
- Navigation: "← Back" outlined button + "Next Step →" green button

### Step 3: Address
- Street Address textarea (full width)
- Region dropdown (half width)
- Province dropdown (half width, cascades from region)
- City/Municipality dropdown (half width, cascades from province)
- Barangay dropdown (half width, for Bislig City)
- ZIP Code field (half width, auto-filled, read-only)
- Navigation: "← Back" + "Next Step →"

### Step 4: Emergency & Medical
- Emergency Contact Name field (half width)
- Emergency Contact Number field (half width)
- Allergies textarea (full width)
- Navigation: "← Back" + "Create Account" green button with submit action

### Wizard State Management
- Form data persisted in JavaScript object across steps (not lost on Back/Next)
- Per-step validation before allowing Next
- Step 1 validates: username uniqueness (async check), email format, password strength, password match
- Step 2 validates: name format (letters + Ñ/ñ), DOB age range (1-120), phone format
- Step 3 validates: region/province/city selected
- Step 4 validates: emergency contact name and number required
- Final submit on Step 4 sends all data via FormData to `../api/auth/register.php`

### Preserved Functionality
- Philippine location database (18 regions, provinces, cities, barangays, ZIP codes)
- Cascading dropdown logic
- Camera capture via HTML5 MediaDevices API
- Photo compression and 2x2 resizing
- File type validation
- Real-time field validation with patterns
- Duplicate username/email checking
- SweetAlert2 modals for success/error
- FormData multipart upload
- All existing API integration

## Forgot Password Page

Same split-screen layout as login:
- Left panel: MediTrack branding (no feature list, just logo + tagline)
- Right panel: "Reset Password" heading, email field, "Send OTP" button, info box
- Mobile: stacked with compact green header

## Verify OTP Page

Same split-screen layout:
- Left panel: MediTrack branding
- Right panel: "Verify OTP" heading, 6 OTP input fields (auto-advance), timer countdown, resend button
- Mobile: stacked with compact green header

## Reset Password Page

Same split-screen layout:
- Left panel: MediTrack branding
- Right panel: "New Password" heading, new password field, confirm password field, submit button
- Mobile: stacked with compact green header

## Shared Design Tokens

### Colors
| Token | Value | Usage |
|-------|-------|-------|
| green-900 | #065f46 | Left panel gradient start |
| green-800 | #047857 | Left panel gradient mid |
| green-700 | #059669 | Buttons, links, focus states |
| green-500 | #10b981 | Button gradient end, accents |
| green-400 | #34d399 | Completed steps, checkmarks |
| green-50 | #f0fdf4 | Light backgrounds |
| gray-50 | #f9fafb | Input backgrounds, right panel bg |
| gray-100 | #f3f4f6 | Hover states |
| gray-200 | #e5e7eb | Borders |
| gray-400 | #9ca3af | Placeholder text |
| gray-500 | #6b7280 | Subtitle text |
| gray-700 | #374151 | Label text |
| gray-800 | #1f2937 | Heading text |
| red-500 | #ef4444 | Error states |

### Typography
- Headings: `'Poppins', sans-serif` — weights 600-800
- Body: `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif` — weights 300-900
- Font smoothing: `-webkit-font-smoothing: antialiased`

### Input Fields
- Height: 42-44px
- Background: `#f9fafb` (or white on gray backgrounds)
- Border: `1.5px solid #e5e7eb`
- Border radius: `10px`
- Focus: `border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1)`
- Padding: `0 14px`
- Font size: 13-14px
- Min font size on mobile: 16px (prevents iOS zoom)

### Buttons
- Primary: `background: linear-gradient(135deg, #059669, #10b981); color: white; font-weight: 600`
- Shadow: `0 4px 14px rgba(5,150,105,0.3)`
- Hover: `transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.35)`
- Height: 42-44px
- Border radius: 10px
- Secondary/outlined: `background: white; border: 1.5px solid #e5e7eb; color: #374151`

### Responsive Breakpoints
- Mobile: `< 768px` — single column, stacked layout
- Desktop: `>= 768px` — split-screen layout
- Large desktop: `>= 1024px` — slightly more padding

### Decorative Elements
- Left panel: 2 large semi-transparent circles (`rgba(255,255,255,0.04-0.05)`) positioned at corners
- Logo container: frosted glass effect (`rgba(255,255,255,0.15)` bg, `rgba(255,255,255,0.2)` border)

## Libraries (unchanged)
- Tailwind CSS 2.2.19 (CDN)
- Font Awesome 6.0.0 (CDN)
- Google Fonts: Inter, Poppins (CDN)
- SweetAlert2 11 (CDN, register page only)

## What Does Not Change
- All API endpoints and request/response formats
- Database schema
- Session management and auth token handling
- Security headers and meta tags
- All validation rules and patterns
- Rate limiting logic
- Role-based redirects
- Audit logging
