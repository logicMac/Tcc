# Tampakan Community College — Registration & Records System (TCC RRS)

> A web-based registration and records management system built with **PHP 8.0+ / MySQL / Bootstrap 5**, designed to run on **WAMP/XAMPP (localhost)** or any PHP-capable web server.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Installation Guide](#2-installation-guide)
3. [Default Accounts](#3-default-accounts)
4. [System Structure](#4-system-structure)
5. [How the System Works](#5-how-the-system-works)
   - [Student Public Portal](#51-student-public-portal)
   - [Pre-Registration for Enrollment](#52-pre-registration-for-enrollment)
   - [Document Pre-Request](#53-document-pre-request)
   - [Tracking System](#54-tracking-system)
   - [Staff Dashboard](#55-staff-dashboard)
   - [Registrar Dashboard](#56-registrar-dashboard)
   - [Student Accounts](#57-student-accounts)
   - [Records Management & Archiving](#58-records-management--archiving)
   - [Student Master List](#59-student-master-list)
   - [Issued Document Verification](#510-issued-document-verification)
6. [User Roles & Access](#6-user-roles--access)
7. [Status Workflows](#7-status-workflows)
8. [Notification System](#8-notification-system)
9. [QR Code System](#9-qr-code-system)
10. [Security Features](#10-security-features)
11. [Common Issues & Troubleshooting](#11-common-issues--troubleshooting)
12. [File Structure Reference](#12-file-structure-reference)

---

## 1. System Requirements

| Requirement | Minimum Version |
|---|---|
| PHP | 8.0 or higher |
| MySQL / MariaDB | 5.7 / 10.3 or higher |
| Apache Web Server | 2.4 or higher |
| XAMPP / WAMP (Windows) | bundle with PHP 8.0+ |
| Browser | Chrome, Firefox, Edge (latest) |
| Internet Connection | Required for CDN assets (Bootstrap, QR library) |

> **Tip:** All CDN resources (Bootstrap 5, Bootstrap Icons, QRCodeJS, Html5-QRCode) are loaded from the internet. For offline/LAN use, download and host them locally.

---

## 2. Installation Guide

### Step 1 — Download / Place the Project

Copy the entire `tcc_rrs` folder into your Apache web root
(`C:\wamp64\www` for WAMP, `C:\xampp\htdocs` for XAMPP):

```
C:\wamp64\www\tcc_rrs\
```

Your folder structure should look like:

```
C:\wamp64\www\
└── tcc_rrs\
    ├── assets\
    ├── auth\
    ├── database\
    ├── includes\
    ├── registrar\
    ├── staff\
    ├── student\
    ├── uploads\
    ├── .htaccess
    ├── index.php
    ├── setup.php
    └── README.md
```

---

### Step 2 — Start Apache & MySQL

1. Open the **WAMP/XAMPP Control Panel**.
2. Start **Apache** — click Start next to Apache.
3. Start **MySQL** — click Start next to MySQL.
4. Wait until both show green "Running" status.

---

### Step 3 — Create the Database

1. Open your browser and go to:
   ```
   http://localhost/phpmyadmin
   ```
2. Click **Import** in the top navigation.
3. Click **Choose File** and select:
   ```
   C:\wamp64\www\tcc_rrs\database\tcc_rrs.sql
   ```
4. Scroll down and click **Import / Go**.
5. You should see a success message. The database `tcc_rrs` is now created with all tables and seed data.

> **⚠ Fresh installs only:** `tcc_rrs.sql` starts with `DROP DATABASE IF EXISTS tcc_rrs`. Never import it over an existing database — it will erase all data.

---

### Step 3b — Upgrading an Existing v1 Install?

If you already have a `tcc_rrs` database from an earlier version, **do not** run `tcc_rrs.sql`. Import the migration instead:

```
C:\wamp64\www\tcc_rrs\database\migration_v2.sql
```

It is idempotent (safe to run twice) and adds: `students` accounts, `student_id` links on submissions, archive columns, `issued_documents`, and the `TCC-VRF` sequence — without touching existing data.

---

### Step 4 — Run the Setup Script

This is the most important step. It generates the correct bcrypt hash for `password123` on **your specific PHP version** and writes it directly into the database.

1. Open your browser and visit:
   ```
   http://localhost/tcc_rrs/setup.php
   ```
2. The page will run automatically and show a checklist of completed steps.
3. You should see **"Setup complete!"** with both accounts listed.
4. **⚠ Delete the setup file immediately after:**
   - Click the login link, then manually delete the file, OR
   - Delete it from Windows Explorer:
     ```
     C:\wamp64\www\tcc_rrs\setup.php
     ```

---

### Step 5 — Configure the Database Connection (if needed)

Open `includes/config.php` and verify these settings match your XAMPP setup:

```php
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'tcc_rrs');
define('DB_USER',    'root');
define('DB_PASS',    '');        // Leave blank for default XAMPP
define('DB_CHARSET', 'utf8mb4');
```

> Default XAMPP MySQL: host = `localhost`, user = `root`, password = *(empty)*.

---

### Step 6 — Set Upload Folder Permissions

The `uploads/` folder must be writable by PHP. On XAMPP for Windows this is automatic. On Linux/macOS servers run:

```bash
chmod -R 755 uploads/
chmod -R 755 uploads/qrcodes/
chmod -R 755 uploads/documents/
```

---

### Step 7 — Open the System

| Page | URL |
|---|---|
| Student Portal | `http://localhost/tcc_rrs/` |
| Student Portal (direct) | `http://localhost/tcc_rrs/student/index.php` |
| Staff / Registrar Login | `http://localhost/tcc_rrs/auth/login.php` |

---

## 3. Default Accounts

> **Change these passwords immediately after your first login.**

| Role | Email | Default Password |
|---|---|---|
| **Registrar** | `registrar@tcc.edu.ph` | `password123` |
| **Staff** | `staff@tcc.edu.ph` | `password123` |
| **Student** | `student@tcc.edu.ph` | `password123` |

> The staff accounts will **require a password change on first login**. You cannot access any dashboard page until the password is changed. The student account signs in directly to the student portal.

To change a password after first login — use the 🔑 key icon in the sidebar footer at any time.

---

## 4. System Structure

```
tcc_rrs/
│
├── assets/
│   ├── css/main.css          ← All styles (dashboard + portal)
│   └── js/main.js            ← Sidebar toggle, QR helpers, copy/print
│
├── auth/
│   ├── login.php             ← Login page (Staff & Registrar only)
│   ├── change_password.php   ← Forced/voluntary password change
│   ├── logout.php            ← Session destroy + redirect
│   └── unauthorized.php      ← 403 page
│
├── database/
│   ├── tcc_rrs.sql           ← Full database schema + seed data
│   ├── migration_v2.sql      ← Idempotent v2 upgrade (students, archive, issued docs)
│   └── .htaccess             ← Blocks direct web access
│
├── includes/                 ← PHP internals (.htaccess blocks web access)
│   ├── config.php            ← App constants, DB credentials
│   ├── db.php                ← PDO database singleton
│   ├── helpers.php           ← Session, CSRF, tracking codes, logging
│   ├── .htaccess             ← Blocks direct web access
│   ├── notifications.php     ← Email notification templates & sender
│   └── partials/
│       ├── head.php          ← HTML <head> + fonts + Bootstrap imports
│       ├── portal_nav.php    ← Public portal navbar
│       ├── topbar.php        ← Dashboard top navigation bar
│       ├── staff_sidebar.php ← Staff sidebar menu
│       ├── registrar_sidebar.php ← Registrar sidebar menu
│       └── footer.php        ← Scripts + closing tags
│
├── student/                  ← PUBLIC + optional student accounts
│   ├── index.php             ← Student portal landing page
│   ├── pre_registration.php  ← Enrollment pre-registration form
│   ├── document_request.php  ← Document request form
│   ├── track.php             ← Status tracking + QR scanner
│   ├── register.php          ← Student account creation
│   ├── login.php             ← Student sign-in
│   ├── logout.php            ← Student sign-out
│   └── account.php           ← Student dashboard (history + security)
│
├── staff/                    ← PROTECTED (Staff & Registrar login)
│   ├── dashboard.php
│   ├── registrations.php     ← List + filter enrollments (+ archived view)
│   ├── view_registration.php ← View detail + update status + archive
│   ├── edit_registration.php ← Edit student record fields
│   ├── master_list.php       ← Student master list + CSV export
│   ├── document_requests.php ← List + filter document requests (+ archived view)
│   ├── view_document.php     ← View detail + update status + archive + issue
│   ├── edit_document.php     ← Edit document request fields
│   ├── issue_document.php    ← QR-verifiable release slip (printable)
│   ├── qr_verify.php         ← QR scanner + tracking/verification codes
│   └── reports.php
│
├── registrar/                ← PROTECTED (Registrar login only)
│   ├── dashboard.php
│   ├── staff.php             ← Manage staff accounts
│   ├── create_staff.php
│   ├── edit_staff.php
│   ├── students.php          ← Manage student portal accounts
│   ├── registrations.php     ← Monitor all enrollments
│   ├── document_requests.php ← Monitor all doc requests
│   ├── reports.php           ← Full analytics + staff activity
│   ├── activity_logs.php     ← System-wide activity logs
│   └── settings.php          ← School info + SMTP + academic year
│
├── uploads/
│   ├── qrcodes/              ← (reserved for future QR file storage)
│   └── documents/            ← (reserved for document uploads)
│
├── .htaccess                 ← Security rules, directory protection
├── index.php                 ← Root redirect (portal or dashboard)
├── verify.php                ← PUBLIC issued-document authenticity check
├── setup.php                 ← One-time setup script (DELETE after use)
└── README.md
```

---

## 5. How the System Works

### 5.1 Student Public Portal

The student-facing side works **with or without an account** — accounts are optional but recommended.

**Access:** `http://localhost/tcc_rrs/`

Students can:
- Browse available registrar documents and their fees
- Submit a pre-registration for enrollment
- Request a registrar document
- Track the status of any submission using a Tracking Code or QR Code
- **Sign in** to their Student Account to see a full history of all their submissions

The portal automatically redirects to `student/index.php`. Students **cannot** reach any staff dashboard URL — the system will redirect them to the staff login page if they try.

---

### 5.2 Pre-Registration for Enrollment

**URL:** `student/pre_registration.php`

**How it works:**

1. Student fills out the multi-section form:
   - Personal Information (name, birth date, gender, civil status, nationality, LRN)
   - Contact & Address (email, phone, full address, guardian info)
   - Academic Information (program, year level, enrollment type, semester, previous school)
2. Student agrees to the terms and clicks **Submit Pre-Registration**.
3. The system:
   - Validates all required fields (server-side + client-side)
   - Checks CSRF token to prevent cross-site attacks
   - Saves the record to `pre_registrations` table with status = **Pending**
   - Generates a unique **Tracking Code** in format `TCC-REG-2026-000001`
   - Records the initial status in `status_history`
   - Sends a **confirmation email** to the student's email address
4. A **success screen** is shown with:
   - The Tracking Code (copyable)
   - A generated **QR Code** pointing to the student's tracking URL
   - Buttons to Download QR, Print, and Copy Code

---

### 5.3 Document Pre-Request

**URL:** `student/document_request.php`

**How it works:**

1. Student fills out their information and selects the document type from a visual card grid:
   - Transcript of Records (TOR)
   - Certificate of Enrollment
   - Certificate of Good Moral
   - Certificate of Registration
   - Diploma, Form 137, Form 138
   - Honorable Dismissal, Certificate of Graduation
   - Authenticated Copies, Other
2. Student enters number of copies, purpose, date needed, and delivery preference (Pick-up or Email).
3. On submission the system:
   - Saves to `document_requests` table with status = **Pending**
   - Generates a **Tracking Code** in format `TCC-DOC-2026-000001`
   - Records status history
   - Sends a **confirmation email**
4. Success screen shows Tracking Code and QR Code.

---

### 5.4 Tracking System

**URL:** `student/track.php`

Students can check the status of any submission **without an account**.

**Two ways to track:**

| Method | How |
|---|---|
| **Enter Code** | Type the Tracking Code (e.g. `TCC-REG-2026-000001`) in the search box and press Track |
| **Scan QR Code** | Click "Scan QR Code Instead", allow camera access, point at the QR from the submission receipt |

**What the tracking page shows:**
- Current status with color-coded badge
- Visual progress bar showing which step has been completed
- Full submission details (name, document/program, dates)
- Complete **Status Timeline** — every status change with date, time, and remarks
- Remarks or instructions left by staff
- A re-generated QR Code (downloadable/printable)
- A contextual "What happens next?" hint

The tracking page only shows non-sensitive information — it does **not** expose contact details or full addresses.

---

### 5.5 Staff Dashboard

**Login:** `auth/login.php` → redirects to `staff/dashboard.php`

#### Dashboard Overview
- Pending enrollment count and today's new submissions
- Pending document request count
- Status breakdown bars for both categories
- Recent registrations and document requests tables

#### Processing a Pre-Registration

1. Go to **Enrollments** in the sidebar.
2. Use the filter bar to search by name, status, program, or enrollment type.
3. Click **View** on any row.
4. The detail page shows all student information in organized sections.
5. In the right panel — select a new **Status** from the dropdown:
   - `Under Review` → staff is reviewing
   - `Processing` → actively being worked on
   - `Approved` → enrollment accepted
   - `Needs Correction` → student must correct something
   - `Declined` → enrollment rejected
6. Add optional **Remarks** (visible to the student on the tracking page).
7. Click **Update Status**.
8. The system automatically:
   - Updates the database
   - Adds to the status history timeline
   - Sends a notification email to the student
   - Logs the action in the activity log

#### Editing a Student Record

- Click **Edit Record** on any registration or document-request detail page.
- Correct any submitted field (name, contact, program, document type, etc.).
- Optionally add an **Edit Note** — it appears in the record's status history.
- All edits are logged in the activity log (`RECORD_EDIT`).

#### Archiving Records

- Click **Archive This Record** on a detail page to archive it.
- Archived records are hidden from default lists but fully preserved and searchable via the **Archived** toggle on each list page.
- Click **Restore from Archive** to bring a record back.
- Pending counts and dashboards only count non-archived records.

#### Processing a Document Request

Same workflow as registrations via **Document Requests** in the sidebar. Additional statuses available:
- `Ready for Release` — document is printed and ready to claim
- `Released` — student has collected the document

When a request reaches `Approved` / `Ready for Release` / `Released`, the
**Issue Verified Document** button appears — see §5.10.

#### QR Code Verification

Go to **QR Verification** in the sidebar:
- Scan a student's QR code using the camera, or
- Type the tracking code manually.
- The system shows the student's submission details and current status.
- Staff can then click **Open Full Record** to process it directly.
- All verifications are logged in the activity log.

---

### 5.6 Registrar Dashboard

**Login:** `auth/login.php` → redirects to `registrar/dashboard.php`

The Registrar has access to everything Staff can see, **plus**:

#### Staff Account Management (`registrar/staff.php`)

- **View** all staff accounts with search and active/inactive filter
- **Create** new staff account — set Employee ID, name, email, phone, password
- **Edit** existing staff — update any detail or change password
- **Activate / Deactivate** accounts — deactivated staff cannot log in

#### Monitoring

- **Pre-Registrations Monitor** — view all enrollments with clickable status tiles
- **Document Requests Monitor** — view all doc requests with clickable status tiles
- Both monitoring pages link directly to the full detail records

#### Reports (`registrar/reports.php`)

- Summary KPIs: total counts, approval rates, release rates
- Enrollments by Status (table + percentages)
- Document Requests by Status
- Staff Activity Summary — how many actions each staff member has performed
- Monthly submission trend for the last 12 months

#### Activity Logs (`registrar/activity_logs.php`)

Every important system action is logged:
- Login and logout events
- Failed login attempts
- Status updates (who changed what, when)
- Staff account creations and edits
- Activations and deactivations
- QR code verifications
- Settings changes

Filter logs by user, module, date, or keyword.

#### System Settings (`registrar/settings.php`)

- School name, address, email, phone, website
- Current academic year and semester
- Enable or disable email notifications
- SMTP configuration (host, port, username, password, encryption)

#### Student Account Management (`registrar/students.php`)

- View all registered student portal accounts
- See each student's linked submissions count
- Activate / deactivate accounts — deactivated students cannot sign in

---

### 5.7 Student Accounts

**URL:** `student/register.php` · `student/login.php` · `student/account.php`

Students may optionally create an account to manage all their transactions in one place.

**How it works:**

1. Student registers at `student/register.php` with name, optional student number, email, contact, and password (bcrypt, cost 12).
2. On registration or login, the system **automatically links** all past submissions made with the same email to their account.
3. Submissions made **while signed in** are linked instantly — form fields are prefilled from the account.
4. `student/account.php` shows:
   - **My Registrations** — every pre-registration with status and tracking link
   - **My Document Requests** — every request, including issued-document verification codes
   - **Security** — change password
5. Student sessions are independent from staff sessions — both can coexist in one browser.
6. Guest tracking via Tracking Code / QR still works without an account.

---

### 5.8 Records Management & Archiving

Staff and the Registrar can fully manage student records:

| Operation | Where |
|---|---|
| **View / Retrieve** | List pages with search + filters; detail pages |
| **Update (status)** | Status dropdown on detail pages |
| **Edit (fields)** | **Edit Record** button on detail pages — every field correctable, all edits logged |
| **Archive** | **Archive This Record** on detail pages — hidden from default lists, never deleted |
| **Restore** | **Restore from Archive** brings the record back |

Archived records:
- Remain in the database with `archived_at` / `archived_by` audit fields
- Are excluded from list defaults, status tiles, and pending counts
- Are reachable via the **Archived** toggle on registrations / document-requests lists

---

### 5.9 Student Master List

**URL:** `staff/master_list.php` (also linked in the Registrar sidebar)

A registrar-style master list of all registered students:

- Filters: program, year level, enrollment type, status, school year, search
- **Print** — browser print (clean layout, action column hidden)
- **Export CSV** — UTF-8 BOM, Excel-ready, respects active filters

---

### 5.10 Issued Document Verification

When a document request reaches `Approved`, `Ready for Release`, or `Released`:

1. Staff clicks **Issue Verified Document** on the request page.
2. The system generates a unique **Verification Code** (`TCC-VRF-YYYY-NNNNNN`) and stores it in `issued_documents`.
3. The **Verification Slip** (`staff/issue_document.php`) is a printable slip containing the QR code — attach it to the released document.
4. The QR points to the **public verification page** `verify.php?code=…` — anyone (employers, other schools, agencies) can confirm the document's authenticity without logging in.
5. The staff **QR Verification** tool also accepts `TCC-VRF-*` codes.
6. The student's account page and tracking page show the verification code once issued.

A `NOT VERIFIED` result means the document does not match official records — a forgery indicator.

---

## 6. User Roles & Access

| Feature | Student (Guest) | Student (Account) | Staff | Registrar |
|---|:---:|:---:|:---:|:---:|
| Student Portal | ✓ | ✓ | ✓ | ✓ |
| Pre-Registration Form | ✓ | ✓ (prefilled + linked) | ✓ | ✓ |
| Document Request Form | ✓ | ✓ (prefilled + linked) | ✓ | ✓ |
| Status Tracking | ✓ | ✓ | ✓ | ✓ |
| Submission History | ✗ | ✓ | ✓ | ✓ |
| Verify Issued Documents | ✓ | ✓ | ✓ | ✓ |
| Staff Dashboard | ✗ | ✗ | ✓ | ✓ |
| Process Registrations | ✗ | ✗ | ✓ | ✓ |
| Process Doc Requests | ✗ | ✗ | ✓ | ✓ |
| Edit / Archive Records | ✗ | ✗ | ✓ | ✓ |
| Student Master List + CSV | ✗ | ✗ | ✓ | ✓ |
| QR Verification | ✗ | ✗ | ✓ | ✓ |
| Reports | ✗ | ✗ | ✓ | ✓ |
| Registrar Dashboard | ✗ | ✗ | ✗ | ✓ |
| Manage Staff Accounts | ✗ | ✗ | ✗ | ✓ |
| Manage Student Accounts | ✗ | ✗ | ✗ | ✓ |
| Activity Logs | ✗ | ✗ | ✗ | ✓ |
| System Settings | ✗ | ✗ | ✗ | ✓ |

---

## 7. Status Workflows

### Enrollment Pre-Registration

```
Submitted by Student
        ↓
    [Pending]           ← Initial state
        ↓
  [Under Review]        ← Staff starts reviewing
        ↓
   [Processing]         ← Being actively processed
        ↓
   [Approved] ✓         ← Student can now enroll in person

— OR —

[Needs Correction] ⚠    ← Student must fix something
[Declined]         ✗    ← Enrollment rejected
```

### Document Request

```
Submitted by Student
        ↓
    [Pending]           ← Initial state
        ↓
  [Under Review]        ← Staff starts reviewing
        ↓
   [Processing]         ← Document being prepared
        ↓
   [Approved]           ← Document approved for release
        ↓
[Ready for Release] 📋  ← Student can claim the document
        ↓
   [Released] ✓         ← Document collected by student

— OR —

[Needs Correction] ⚠    ← Student must provide corrections
[Declined]         ✗    ← Request rejected
```

---

## 8. Notification System

The system sends automated email notifications to students at key status milestones.

| Event | Trigger |
|---|---|
| Pre-registration submitted | Student submits the form |
| Registration status updated | Staff changes the status |
| Document request submitted | Student submits the form |
| Document request status updated | Staff changes the status |

### Configuring Email (SMTP)

1. Log in as **Registrar**.
2. Go to **System Settings**.
3. Fill in the SMTP section:

| Setting | Example (Gmail) |
|---|---|
| SMTP Host | `smtp.gmail.com` |
| SMTP Port | `587` |
| SMTP Username | `your-email@gmail.com` |
| SMTP Password | Your Gmail App Password |
| Encryption | `TLS` |
| From Name | `TCC Registrar` |

> **Gmail App Password:** In your Google Account → Security → 2-Step Verification → App Passwords → Generate one for "Mail".

4. Set **Email Notifications** to **Enabled**.
5. Click **Save Settings**.

> If SMTP is not configured, the system falls back to PHP's built-in `mail()` function. On XAMPP, emails will only work if a local mail server (e.g., Mercury Mail) is configured, or if you use proper SMTP credentials.

---

## 9. QR Code System

### How QR Codes are Generated

Every submission automatically generates a QR code using the **QRCodeJS** JavaScript library. The QR code encodes the full tracking URL:

```
http://localhost/tcc_rrs/student/track.php?code=TCC-REG-2026-000001
```

When scanned by any QR reader, the browser opens the tracking page directly for that submission.

### Student QR Code Options

After submitting a form, students can:
- **Download** the QR code as a PNG image
- **Print** the QR code section directly
- **Copy** the tracking code to clipboard

### Staff QR Verification

Staff can use the **QR Verification** tool in their dashboard:
- Uses the device camera (via Html5-QRCode library)
- Or type/paste a tracking code manually
- Shows instant verification result (name, document/program, current status)
- Links directly to the full record for processing
- Also accepts `TCC-VRF-*` verification codes from issued-document QR codes

### Issued-Document QR Verification

Verified release slips (`staff/issue_document.php`) carry a QR encoding:

```
http://localhost/tcc_rrs/verify.php?code=TCC-VRF-2026-000001
```

Scanning it opens the public `verify.php` page, which confirms whether the document matches an issued record in the centralized database — see §5.10.

---

## 10. Security Features

| Security Layer | Implementation |
|---|---|
| **Password Hashing** | PHP `password_hash()` with bcrypt (cost 12) |
| **Session Management** | HTTPOnly, SameSite=Strict cookies; session regeneration on login |
| **CSRF Protection** | Token per session, validated on every POST |
| **Role-Based Access** | `requireAuth()` checks on every protected page |
| **Input Sanitization** | `htmlspecialchars()` on all output; prepared statements for all queries |
| **SQL Injection Prevention** | PDO prepared statements with bound parameters throughout |
| **Directory Protection** | `.htaccess` blocks `/includes/` and `/database/` |
| **Activity Logging** | Every login, logout, status change, and admin action is logged |
| **Unique Tracking Codes** | Atomic DB sequence — no sequential guessing between types |
| **Security Headers** | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection via `.htaccess` |

---

## 11. Common Issues & Troubleshooting

### ❌ "Database Connection Error"

**Cause:** XAMPP MySQL is not running, or DB credentials are wrong.

**Fix:**
1. Make sure MySQL is started in XAMPP Control Panel.
2. Open `includes/config.php` and verify `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.
3. Confirm the database `tcc_rrs` exists in phpMyAdmin.

---

### ❌ Login fails with correct credentials

**Cause:** Password hashes in the database are placeholder values.

**Fix:**
1. Visit `http://localhost/tcc_rrs/setup.php`
2. It regenerates the bcrypt hash for `password123` on your PHP version
   for any account still on the temporary password.
3. Try logging in again.

---

### ❌ QR code not appearing after submission

**Cause:** JavaScript CDN (QRCodeJS) failed to load — no internet connection.

**Fix:**
- Ensure the computer has internet access, OR
- Download `qrcode.min.js` from `https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js` and save it to `assets/js/qrcode.min.js`, then update the `<script>` tags in the student pages and footer partial.

---

### ❌ Camera scanner not working (QR Verification)

**Cause:** Browser requires HTTPS to access the camera, or camera permission was denied.

**Fix:**
- On localhost, Chrome/Firefox allow camera access without HTTPS.
- Click **Allow** when the browser asks for camera permission.
- If permission was denied previously: click the camera/lock icon in the address bar → reset permission → reload.

---

### ❌ Emails not being sent

**Cause:** SMTP not configured, or PHP `mail()` not set up on XAMPP.

**Fix:**
1. Log in as Registrar → System Settings → configure SMTP with Gmail App Password.
2. Or install and configure **Mercury Mail** in XAMPP for local mail.
3. Check the `notifications` table in phpMyAdmin — failed entries will have `status = 'failed'`.

---

### ❌ Pages show "403 Forbidden"

**Cause:** `.htaccess` is blocking a path, or Apache `mod_rewrite` is not enabled.

**Fix:**
1. Open XAMPP → Apache → `httpd.conf`
2. Find `#LoadModule rewrite_module` and remove the `#`
3. Find `AllowOverride None` for the htdocs directory and change to `AllowOverride All`
4. Restart Apache.

---

### ❌ "Page not found" for any URL

**Cause:** The `BASE_URL` is being calculated incorrectly.

**Fix:** Open `includes/config.php` and check the `BASE_URL` block. You may need to manually set it:

```php
// Uncomment and set this manually if auto-detection fails:
define('BASE_URL', 'http://localhost/tcc_rrs');
```

---

## 12. File Structure Reference

### Database Tables

| Table | Purpose |
|---|---|
| `users` | Staff and Registrar accounts |
| `students` | Student portal accounts |
| `programs` | Available academic programs/courses |
| `pre_registrations` | Student enrollment pre-registration submissions |
| `document_types` | Available document types with fees |
| `document_requests` | Student document request submissions |
| `issued_documents` | QR-verifiable issued official documents |
| `status_history` | Every status change for any submission |
| `notifications` | Log of all email notifications sent |
| `activity_logs` | Staff/Registrar action audit trail |
| `system_settings` | School info, SMTP, academic year config |
| `tracking_sequences` | Atomic counters for generating tracking/verification codes |

### Tracking Code Format

| Type | Format | Example |
|---|---|---|
| Enrollment | `TCC-REG-YYYY-NNNNNN` | `TCC-REG-2026-000001` |
| Document | `TCC-DOC-YYYY-NNNNNN` | `TCC-DOC-2026-000042` |
| Verification | `TCC-VRF-YYYY-NNNNNN` | `TCC-VRF-2026-000007` |

- `YYYY` = current year
- `NNNNNN` = zero-padded 6-digit sequence (resets each year per prefix)

---

## Quick Reference URLs

| Page | URL |
|---|---|
| Student Portal | `/tcc_rrs/` |
| Student Sign In | `/tcc_rrs/student/login.php` |
| Student Register | `/tcc_rrs/student/register.php` |
| My Account | `/tcc_rrs/student/account.php` |
| Pre-Registration | `/tcc_rrs/student/pre_registration.php` |
| Document Request | `/tcc_rrs/student/document_request.php` |
| Track Status | `/tcc_rrs/student/track.php` |
| Verify Document | `/tcc_rrs/verify.php` |
| Login (Staff) | `/tcc_rrs/auth/login.php` |
| Staff Dashboard | `/tcc_rrs/staff/dashboard.php` |
| Student Master List | `/tcc_rrs/staff/master_list.php` |
| Registrar Dashboard | `/tcc_rrs/registrar/dashboard.php` |
| Student Accounts | `/tcc_rrs/registrar/students.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |

---

*Tampakan Community College — Registration & Records System v1.0.0*  
*Built with PHP · MySQL · Bootstrap 5 · WAMP/XAMPP*
