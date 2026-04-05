# MediTrack Simplification — Internal Medicine Appointment System

## Overview

Simplify MediTrack from a multi-department, multi-doctor healthcare platform into a focused single-doctor Internal Medicine clinic system. The two highlighted features are **Appointment Booking via QR Code** and **Printable Medical Records**.

## Roles

Three roles, down from four:

| Role | Access |
|------|--------|
| **Patient** | Register, login, book appointments, view appointment QR, view/print medical records (diagnosis, prescriptions, lab results) |
| **Doctor** | View today's appointments, check-in patients (QR scan), record vitals/diagnosis/prescription/lab orders, mark appointments complete |
| **Admin** | View all appointments, manage patients, configure doctor schedule, view reports/stats, view activity logs |

Reception role is removed. The doctor handles check-in by scanning the patient's QR code directly.

## Core Flow

Based on the provided flowchart:

```
1. Scan QR Code (static clinic QR on poster/card/website)
       |
2. Visit Appointment Website (landing page opens)
       |
3. Register / Log In (patient account required)
       |
4. Fill in Details (date, time, reason — doctor auto-selected)
       |
5. Select Date & Internal Medicine Doctor (calendar + time slots)
       |
6. Confirm Appointment (appointment created, QR code generated)
       |
7. [Day of visit] Doctor scans patient's appointment QR → checked in
       |
8. Doctor records diagnosis, prescription, lab results
       |
9. Receive Printed Files (view/print from dashboard or at clinic)
       — Medical Files
       — Lab Test Results
       — Prescriptions
       — Appointment Details
```

## Pages (9 total)

### 1. Landing Page (`index.html`)
- Clinic name, Internal Medicine branding
- Brief description of the clinic
- Login and Register buttons
- Static QR code display (links to this page or directly to booking)

### 2. Login Page (`pages/login.html`)
- Email + password form
- Links to register and forgot password
- Redirects to role-appropriate dashboard after login

### 3. Register Page (`pages/register.html`)
- Fields: full name, email, password, date of birth, gender, contact number, address (region, city, barangay), blood group, allergies, emergency contact
- Creates user (role=patient) + patient profile
- Redirects to login after success

### 4. Patient Dashboard (`pages/patient-dashboard.html`)
- **Book Appointment tab**: date picker, time slot picker (from doctor's schedule), reason for visit field. Doctor is auto-selected (single Internal Medicine doctor shown, not a dropdown). Submit creates appointment and generates QR.
- **My Appointments tab**: list of upcoming and past appointments with status badges (scheduled, checked_in, completed, cancelled). Each appointment shows a QR code button.
- **Medical Records tab**: list of completed visits. Each record shows diagnosis, prescription, lab results, vital signs. Print button per record opens printable view.
- **Profile tab**: view/edit personal info, change password.
- **QR Code modal**: displays the appointment QR code (for doctor to scan at check-in). Option to download/print.

### 5. Doctor Dashboard (`pages/doctor-dashboard.html`)
- **Today's Appointments tab**: list of today's appointments with patient name, time, status. Check-in button (opens camera to scan QR or manual token entry).
- **All Appointments tab**: filterable list of all appointments (by date, status).
- **Medical Record Form**: when a checked-in patient is selected, form appears with: vital signs (BP, temp, heart rate, weight), chief complaint, diagnosis, prescription, lab tests ordered, notes, follow-up date. Save button creates the medical record and marks appointment as completed.
- **Profile tab**: view doctor profile, change password.

### 6. Admin Dashboard (`pages/admin-dashboard.html`)
- **Overview tab**: stats cards — total patients, total appointments (today/week/month), completed, cancelled.
- **Appointments tab**: list all appointments, filter by date/status. Can cancel appointments.
- **Patients tab**: list all patients, view profile details, activate/deactivate accounts.
- **Doctor Schedule tab**: configure the single doctor's weekly schedule — set working days, start/end times, slot duration (default 30 min), max patients per day.
- **Reports tab**: appointment statistics (daily/weekly/monthly), patient demographics, completion rates. Printable.
- **Activity Logs tab**: audit trail of all actions (logins, bookings, check-ins, record creation).

### 7. QR Booking Entry (`pages/qr-booking.html`)
- The page that opens when someone scans the static clinic QR code
- If logged in: redirect to patient dashboard booking tab
- If not logged in: show login/register options with a message like "Book your Internal Medicine appointment"

### 8. Print Record (`pages/print-record.html`)
- Clean, print-optimized layout
- Shows: clinic header, patient info, appointment date, doctor name
- Sections: diagnosis, prescription, lab test results, vital signs, follow-up date
- Print button triggers browser print dialog
- Accessible from patient dashboard and doctor dashboard

### 9. Password Reset (`pages/forgot-password.html`, `pages/reset-password.html`)
- Forgot password: enter email, receive OTP
- Verify OTP, set new password
- Simplified to two pages (combine verify-otp into reset flow)

## Database Schema (8 tables)

### `users`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
email VARCHAR(100) UNIQUE NOT NULL
username VARCHAR(50) UNIQUE NOT NULL
password_hash VARCHAR(255) NOT NULL
role ENUM('patient', 'doctor', 'admin') NOT NULL
status ENUM('active', 'inactive') DEFAULT 'active'
last_login DATETIME
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### `patients`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
user_id INT UNIQUE NOT NULL (FK → users.id)
full_name VARCHAR(100) NOT NULL
date_of_birth DATE
gender ENUM('Male', 'Female', 'Other')
contact_number VARCHAR(20)
address TEXT
region VARCHAR(100)
city VARCHAR(100)
barangay VARCHAR(100)
blood_group VARCHAR(5)
allergies TEXT
emergency_contact_name VARCHAR(100)
emergency_contact_number VARCHAR(20)
profile_picture VARCHAR(255)
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### `doctors`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
user_id INT UNIQUE NOT NULL (FK → users.id)
full_name VARCHAR(100) NOT NULL
specialization VARCHAR(100) DEFAULT 'Internal Medicine'
license_number VARCHAR(50)
consultation_fee DECIMAL(10,2) DEFAULT 0
experience_years INT DEFAULT 0
bio TEXT
status ENUM('active', 'inactive') DEFAULT 'active'
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### `doctor_schedules`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
doctor_id INT NOT NULL (FK → doctors.id)
day_of_week TINYINT NOT NULL (0=Sunday, 6=Saturday)
start_time TIME NOT NULL
end_time TIME NOT NULL
slot_duration INT DEFAULT 30 (minutes)
max_patients INT DEFAULT 20
is_active TINYINT(1) DEFAULT 1
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

### `appointments`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
appointment_number VARCHAR(20) UNIQUE NOT NULL
patient_id INT NOT NULL (FK → patients.id)
doctor_id INT NOT NULL (FK → doctors.id)
appointment_date DATE NOT NULL
appointment_time TIME NOT NULL
status ENUM('scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled'
reason_for_visit TEXT
checked_in_at DATETIME
completed_at DATETIME
cancelled_at DATETIME
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### `medical_records`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
appointment_id INT UNIQUE NOT NULL (FK → appointments.id)
patient_id INT NOT NULL (FK → patients.id)
doctor_id INT NOT NULL (FK → doctors.id)
chief_complaint TEXT
symptoms TEXT
vital_signs JSON (bp, temp, heart_rate, weight, height)
diagnosis TEXT
prescription TEXT
lab_tests_ordered TEXT
notes TEXT
follow_up_date DATE
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### `qr_tokens`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
appointment_id INT UNIQUE NOT NULL (FK → appointments.id)
qr_payload JSON NOT NULL
signature VARCHAR(255) NOT NULL
token_hash VARCHAR(255) UNIQUE NOT NULL
issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
expires_at DATETIME NOT NULL
is_used TINYINT(1) DEFAULT 0
used_at DATETIME
used_by INT (FK → users.id)
```

### `activity_logs`
```sql
id INT PRIMARY KEY AUTO_INCREMENT
user_id INT (FK → users.id)
username VARCHAR(50)
user_role VARCHAR(20)
action_type ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'CHECKIN')
module VARCHAR(50)
record_id INT
description TEXT
ip_address VARCHAR(45)
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

## API Endpoints (simplified from 78 → ~30)

### Auth (5)
- `POST /api/auth/register.php` — patient registration
- `POST /api/auth/login.php` — login (all roles)
- `POST /api/auth/logout.php` — logout
- `POST /api/auth/request-otp.php` — forgot password OTP
- `POST /api/auth/reset-password.php` — reset password with OTP

### Patient (7)
- `GET /api/patient/get-profile.php` — get profile
- `POST /api/patient/update-profile.php` — update profile
- `POST /api/patient/change-password.php` — change password
- `GET /api/patient/get-appointments.php` — list appointments
- `POST /api/patient/book-appointment.php` — book appointment (auto-assigns single doctor)
- `POST /api/patient/cancel-appointment.php` — cancel appointment
- `GET /api/patient/get-medical-records.php` — list medical records
- `GET /api/patient/get-available-slots.php` — get open time slots for a date

### Doctor (6)
- `GET /api/doctor/get-profile.php` — get profile
- `POST /api/doctor/change-password.php` — change password
- `GET /api/doctor/get-appointments.php` — list appointments (filterable by date/status)
- `POST /api/doctor/checkin-patient.php` — check in patient via QR token
- `POST /api/doctor/save-medical-record.php` — save medical record + complete appointment
- `GET /api/doctor/stats.php` — dashboard stats

### Admin (10)
- `GET /api/admin/stats.php` — dashboard statistics
- `GET /api/admin/appointments.php` — all appointments
- `POST /api/admin/cancel-appointment.php` — cancel appointment
- `GET /api/admin/get-all-patients.php` — list patients
- `POST /api/admin/update-patient-status.php` — activate/deactivate patient
- `GET /api/admin/get-doctor-schedule.php` — get doctor's weekly schedule
- `POST /api/admin/update-doctor-schedule.php` — update schedule
- `GET /api/admin/reports-data.php` — reports data
- `GET /api/admin/activity.php` — activity logs
- `GET /api/admin/get-doctor-profile.php` — view the single doctor's info

### Appointments (2)
- `POST /api/appointments/generate-qr.php` — generate/regenerate QR for an appointment
- `POST /api/appointments/checkin.php` — validate QR token and check in

## QR Code Implementation

### Static Clinic QR Code
- A fixed QR code that links to `{APP_URL}/pages/qr-booking.html`
- Printed on posters, cards, or displayed on screens in the clinic
- Purpose: direct patients to the booking website

### Appointment QR Code
- Generated when an appointment is created
- Contains a signed token (HMAC-SHA256) stored in `qr_tokens`
- Patient views the QR on their dashboard
- Doctor scans it to check the patient in
- One-time use, expires in 24 hours
- Generated via Google Charts QR API (no server dependencies)

## Features Removed
- Department management (hardcoded "Internal Medicine")
- Doctor CRUD (single pre-seeded doctor)
- Reception role and dashboard
- Triage assessments
- Notification system (use SweetAlert2 for in-app feedback)
- Settings page
- Visits table (merged into medical_records)
- Search system (unified search)
- Email service (no SMTP dependency)
- Mobile search page
- Booking QR page (replaced by qr-booking.html)

## Tech Stack (unchanged)
- **Frontend**: HTML, Tailwind CSS (CDN), Vanilla JavaScript, SweetAlert2
- **Backend**: PHP with PDO
- **Database**: MySQL (utf8mb4)
- **QR**: Google Charts QR API
- **Server**: XAMPP (Apache + MySQL)

## Seed Data

The system must be pre-seeded with:

1. **Admin account**: admin@meditrack.com / admin123
2. **Doctor account**: doctor@meditrack.com / doctor123
   - Name: Dr. [configurable]
   - Specialization: Internal Medicine
   - Default schedule: Monday-Friday, 8:00 AM - 5:00 PM, 30-min slots, max 20 patients/day
3. **Internal Medicine department**: hardcoded, no UI to manage

## Print Record Layout

The printable record page includes:
- **Header**: clinic name, address, contact, logo
- **Patient Info**: name, DOB, gender, contact
- **Appointment Info**: date, time, appointment number, doctor name
- **Medical Record**: chief complaint, vital signs (BP, temp, heart rate, weight), diagnosis, prescription, lab tests ordered, notes
- **Follow-up**: follow-up date if any
- **Footer**: "This is a computer-generated document"
- Styled for A4 paper, print-friendly CSS (no backgrounds, clean borders)
