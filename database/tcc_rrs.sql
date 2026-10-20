-- ============================================================
--  Tampakan Community College
--  Registration & Records System (TCC RRS)
--  Database: tcc_rrs
--
--  HOW TO USE
--  1. Open phpMyAdmin → Import → choose this file → Go
--  2. Login at: auth/login.php
--
--  DEFAULT ACCOUNTS
--  ┌─────────────┬─────────────────────────┬─────────────┐
--  │ Role        │ Email                   │ Password    │
--  ├─────────────┼─────────────────────────┼─────────────┤
--  │ Registrar   │ registrar@tcc.edu.ph    │ password123 │
--  │ Staff       │ staff@tcc.edu.ph        │ password123 │
--  │ Student     │ student@tcc.edu.ph      │ password123 │
--  └─────────────┴─────────────────────────┴─────────────┘
--  The staff accounts are flagged to REQUIRE a password
--  change on first login. The student account signs in
--  straight to the portal (no forced change).
--
--  Password hash verified with XAMPP PHP 8.x bcrypt cost 10:
--  password_verify('password123', '<hash>') === true
-- ============================================================

-- ── Drop and recreate cleanly ─────────────────────────────
DROP DATABASE IF EXISTS tcc_rrs;
CREATE DATABASE tcc_rrs
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE tcc_rrs;

-- ============================================================
-- TABLE: users  (Registrar & Staff — student accounts live in
--               the separate `students` table below)
-- ============================================================
CREATE TABLE users (
    id                   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    employee_id          VARCHAR(30)      NOT NULL,
    full_name            VARCHAR(150)     NOT NULL,
    email                VARCHAR(150)     NOT NULL,
    password_hash        VARCHAR(255)     NOT NULL,
    role                 ENUM('registrar','staff') NOT NULL DEFAULT 'staff',
    is_active            TINYINT(1)       NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)       NOT NULL DEFAULT 1,
    profile_photo        VARCHAR(255)         NULL DEFAULT NULL,
    phone                VARCHAR(20)          NULL DEFAULT NULL,
    created_by           INT UNSIGNED         NULL DEFAULT NULL,
    last_login           DATETIME             NULL DEFAULT NULL,
    created_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_id (employee_id),
    UNIQUE KEY uq_email (email),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: students  (Student portal accounts)
-- ============================================================
CREATE TABLE students (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    student_no      VARCHAR(30)          NULL DEFAULT NULL,
    first_name      VARCHAR(100)     NOT NULL,
    middle_name     VARCHAR(100)         NULL DEFAULT NULL,
    last_name       VARCHAR(100)     NOT NULL,
    suffix          VARCHAR(20)          NULL DEFAULT NULL,
    email           VARCHAR(150)     NOT NULL,
    contact_number  VARCHAR(20)      NOT NULL,
    password_hash   VARCHAR(255)     NOT NULL,
    must_change_password TINYINT(1)  NOT NULL DEFAULT 0,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    last_login      DATETIME             NULL DEFAULT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: programs  (Academic programs / courses)
-- ============================================================
CREATE TABLE programs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)  NOT NULL,
    name        VARCHAR(200) NOT NULL,
    department  VARCHAR(200)     NULL DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prog_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: document_types  (Registrar document catalogue)
-- ============================================================
CREATE TABLE document_types (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code            VARCHAR(30)     NOT NULL,
    name            VARCHAR(200)    NOT NULL,
    description     TEXT                NULL DEFAULT NULL,
    fee             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    processing_days INT             NOT NULL DEFAULT 3,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_doc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: pre_registrations  (Student enrollment submissions)
-- ============================================================
CREATE TABLE pre_registrations (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tracking_code       VARCHAR(30)     NOT NULL,
    -- Personal
    first_name          VARCHAR(100)    NOT NULL,
    middle_name         VARCHAR(100)        NULL DEFAULT NULL,
    last_name           VARCHAR(100)    NOT NULL,
    suffix              VARCHAR(20)         NULL DEFAULT NULL,
    date_of_birth       DATE            NOT NULL,
    birth_place         VARCHAR(150)        NULL DEFAULT NULL,
    gender              ENUM('Male','Female','Other') NOT NULL,
    civil_status        ENUM('Single','Married','Widowed','Separated') NOT NULL DEFAULT 'Single',
    nationality         VARCHAR(80)     NOT NULL DEFAULT 'Filipino',
    citizenship         ENUM('Filipino','Dual','Others') NOT NULL DEFAULT 'Filipino',
    citizenship_specify VARCHAR(100)        NULL DEFAULT NULL,
    religion            VARCHAR(80)         NULL DEFAULT NULL,
    -- Contact
    email               VARCHAR(150)    NOT NULL,
    contact_number      VARCHAR(20)     NOT NULL,
    telephone_number    VARCHAR(20)         NULL DEFAULT NULL,
    -- Permanent Address
    house_no_street     VARCHAR(200)    NOT NULL,
    barangay            VARCHAR(100)    NOT NULL,
    municipality_city   VARCHAR(100)    NOT NULL,
    province            VARCHAR(100)    NOT NULL,
    district            VARCHAR(100)        NULL DEFAULT NULL,
    region              VARCHAR(100)        NULL DEFAULT NULL,
    zip_code            VARCHAR(10)         NULL DEFAULT NULL,
    -- Mailing Address (if not the same as permanent)
    mail_same_as_permanent TINYINT(1)   NOT NULL DEFAULT 1,
    mail_house_no_street   VARCHAR(200)     NULL DEFAULT NULL,
    mail_barangay          VARCHAR(100)     NULL DEFAULT NULL,
    mail_municipality_city VARCHAR(100)     NULL DEFAULT NULL,
    mail_province          VARCHAR(100)     NULL DEFAULT NULL,
    mail_district          VARCHAR(100)     NULL DEFAULT NULL,
    mail_region            VARCHAR(100)     NULL DEFAULT NULL,
    -- Academic
    program_id          INT UNSIGNED        NULL DEFAULT NULL,
    program_other       VARCHAR(200)        NULL DEFAULT NULL,
    year_level          ENUM('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL DEFAULT '1st Year',
    enrollment_type     ENUM('New Student','Transferee','Returnee','Cross-Enrollee','2nd Undergraduate') NOT NULL DEFAULT 'New Student',
    school_year         VARCHAR(20)     NOT NULL,
    semester            ENUM('1st Semester','2nd Semester','Summer') NOT NULL DEFAULT '1st Semester',
    -- Previous school
    previous_school     VARCHAR(200)        NULL DEFAULT NULL,
    previous_program    VARCHAR(200)        NULL DEFAULT NULL,
    last_year_attended  VARCHAR(10)         NULL DEFAULT NULL,
    lrn                 VARCHAR(30)         NULL DEFAULT NULL,
    -- Schools Attended (per admission form item 13)
    elementary_school          VARCHAR(200)   NULL DEFAULT NULL,
    elementary_year_graduated  VARCHAR(10)    NULL DEFAULT NULL,
    jhs_school                 VARCHAR(200)   NULL DEFAULT NULL,
    jhs_year_completed         VARCHAR(10)    NULL DEFAULT NULL,
    shs_school                 VARCHAR(200)   NULL DEFAULT NULL,
    shs_year_graduated         VARCHAR(10)    NULL DEFAULT NULL,
    college_school             VARCHAR(200)   NULL DEFAULT NULL,
    college_year_attended      VARCHAR(10)    NULL DEFAULT NULL,
    -- Family Information (item 12)
    father_name         VARCHAR(150)        NULL DEFAULT NULL,
    father_status       ENUM('Living','Deceased') NULL DEFAULT NULL,
    father_contact      VARCHAR(20)         NULL DEFAULT NULL,
    mother_name         VARCHAR(150)        NULL DEFAULT NULL,
    mother_status       ENUM('Living','Deceased') NULL DEFAULT NULL,
    mother_contact      VARCHAR(20)         NULL DEFAULT NULL,
    guardian_name       VARCHAR(150)        NULL DEFAULT NULL,
    guardian_status     ENUM('Living','Deceased') NULL DEFAULT NULL,
    guardian_relation   VARCHAR(80)         NULL DEFAULT NULL,
    guardian_contact    VARCHAR(20)         NULL DEFAULT NULL,
    spouse_name         VARCHAR(150)        NULL DEFAULT NULL,
    -- Applicant photo (2x2)
    photo_path          VARCHAR(255)        NULL DEFAULT NULL,
    -- School credentials: {key: file_path} uploaded scans, {key: 1} originals verified by staff
    credentials_submitted JSON              NULL DEFAULT NULL,
    credentials_presented JSON              NULL DEFAULT NULL,
    -- Processing
    status              ENUM('Pending','Under Review','Processing','Approved','Needs Correction','Declined') NOT NULL DEFAULT 'Pending',
    assigned_to         INT UNSIGNED        NULL DEFAULT NULL,
    remarks             TEXT                NULL DEFAULT NULL,
    processed_at        DATETIME            NULL DEFAULT NULL,
    approved_at         DATETIME            NULL DEFAULT NULL,
    -- Link to student account (optional — guest submissions stay NULL)
    student_account_id  INT UNSIGNED        NULL DEFAULT NULL,
    -- Archival (records management)
    is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
    archived_at         DATETIME            NULL DEFAULT NULL,
    archived_by         INT UNSIGNED        NULL DEFAULT NULL,
    -- Meta
    ip_address          VARCHAR(45)         NULL DEFAULT NULL,
    submitted_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reg_tracking (tracking_code),
    KEY idx_reg_status  (status),
    KEY idx_reg_email   (email),
    KEY idx_reg_student (student_account_id),
    KEY idx_reg_archived (is_archived),
    CONSTRAINT fk_reg_program     FOREIGN KEY (program_id)  REFERENCES programs (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_assigned    FOREIGN KEY (assigned_to) REFERENCES users    (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_student     FOREIGN KEY (student_account_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_archiver    FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: document_requests  (Student document request submissions)
-- ============================================================
CREATE TABLE document_requests (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tracking_code       VARCHAR(30)     NOT NULL,
    -- Student
    first_name          VARCHAR(100)    NOT NULL,
    middle_name         VARCHAR(100)        NULL DEFAULT NULL,
    last_name           VARCHAR(100)    NOT NULL,
    suffix              VARCHAR(20)         NULL DEFAULT NULL,
    student_id          VARCHAR(50)         NULL DEFAULT NULL,
    email               VARCHAR(150)    NOT NULL,
    contact_number      VARCHAR(20)     NOT NULL,
    address             TEXT                NULL DEFAULT NULL,
    -- Document
    document_type_id    INT UNSIGNED        NULL DEFAULT NULL,
    document_type_other VARCHAR(200)        NULL DEFAULT NULL,
    copies              INT             NOT NULL DEFAULT 1,
    purpose             TEXT            NOT NULL,
    date_needed         DATE                NULL DEFAULT NULL,
    delivery_method     ENUM('Pick-up','Email') NOT NULL DEFAULT 'Pick-up',
    -- Additional
    program             VARCHAR(200)        NULL DEFAULT NULL,
    year_graduated      VARCHAR(10)         NULL DEFAULT NULL,
    last_year_attended  VARCHAR(10)         NULL DEFAULT NULL,
    additional_info     TEXT                NULL DEFAULT NULL,
    -- Processing
    status              ENUM('Pending','Under Review','Processing','Approved','Ready for Release','Released','Needs Correction','Declined') NOT NULL DEFAULT 'Pending',
    assigned_to         INT UNSIGNED        NULL DEFAULT NULL,
    remarks             TEXT                NULL DEFAULT NULL,
    processed_at        DATETIME            NULL DEFAULT NULL,
    released_at         DATETIME            NULL DEFAULT NULL,
    -- Pickup appointment (booked by the student when Ready for Release)
    pickup_date         DATE                NULL DEFAULT NULL,
    pickup_slot         VARCHAR(40)         NULL DEFAULT NULL,
    pickup_booked_at    DATETIME            NULL DEFAULT NULL,
    -- Link to student account (optional — guest submissions stay NULL)
    student_account_id  INT UNSIGNED        NULL DEFAULT NULL,
    -- Archival (records management)
    is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
    archived_at         DATETIME            NULL DEFAULT NULL,
    archived_by         INT UNSIGNED        NULL DEFAULT NULL,
    -- Meta
    ip_address          VARCHAR(45)         NULL DEFAULT NULL,
    submitted_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_doc_tracking (tracking_code),
    KEY idx_doc_status  (status),
    KEY idx_doc_email   (email),
    KEY idx_doc_student (student_account_id),
    KEY idx_doc_archived (is_archived),
    CONSTRAINT fk_doc_type       FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_assigned   FOREIGN KEY (assigned_to)      REFERENCES users           (id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_student    FOREIGN KEY (student_account_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_archiver   FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: status_history  (Full timeline for every submission)
-- ============================================================
CREATE TABLE status_history (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    reference_type  ENUM('registration','document_request') NOT NULL,
    reference_id    INT UNSIGNED    NOT NULL,
    tracking_code   VARCHAR(30)     NOT NULL,
    old_status      VARCHAR(50)         NULL DEFAULT NULL,
    new_status      VARCHAR(50)     NOT NULL,
    remarks         TEXT                NULL DEFAULT NULL,
    changed_by      INT UNSIGNED        NULL DEFAULT NULL,
    changed_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sh_tracking (tracking_code),
    KEY idx_sh_ref      (reference_type, reference_id),
    CONSTRAINT fk_sh_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: issued_documents  (Official released documents carrying
--                            a unique verification code + QR)
-- ============================================================
CREATE TABLE issued_documents (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    verification_code   VARCHAR(30)     NOT NULL,
    document_request_id INT UNSIGNED    NOT NULL,
    tracking_code       VARCHAR(30)     NOT NULL,
    document_type       VARCHAR(200)    NOT NULL,
    issued_to           VARCHAR(200)    NOT NULL,
    student_id          VARCHAR(50)         NULL DEFAULT NULL,
    copies              INT             NOT NULL DEFAULT 1,
    purpose             TEXT                NULL DEFAULT NULL,
    issued_by           INT UNSIGNED        NULL DEFAULT NULL,
    issued_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes               TEXT                NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_issued_code (verification_code),
    UNIQUE KEY uq_issued_req  (document_request_id),
    KEY idx_issued_tracking (tracking_code),
    CONSTRAINT fk_issued_req  FOREIGN KEY (document_request_id) REFERENCES document_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_issued_user FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications  (Email notification log)
-- ============================================================
CREATE TABLE notifications (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tracking_code   VARCHAR(30)     NOT NULL,
    recipient_email VARCHAR(150)    NOT NULL,
    recipient_phone VARCHAR(20)         NULL DEFAULT NULL,
    subject         VARCHAR(255)    NOT NULL,
    message         TEXT            NOT NULL,
    channel         ENUM('email','sms','both') NOT NULL DEFAULT 'email',
    status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    sent_at         DATETIME            NULL DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_tracking (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: student_notifications  (In-app feed for portal accounts)
-- ============================================================
CREATE TABLE student_notifications (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED    NOT NULL,
    tracking_code VARCHAR(30)     NOT NULL,
    title         VARCHAR(200)    NOT NULL,
    message       TEXT            NOT NULL,
    link          VARCHAR(255)        NULL DEFAULT NULL,
    is_read       TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sn_student (student_id, is_read),
    KEY idx_sn_created (created_at),
    CONSTRAINT fk_sn_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: activity_logs  (Staff & Registrar audit trail)
-- ============================================================
CREATE TABLE activity_logs (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED        NULL DEFAULT NULL,
    user_name   VARCHAR(150)        NULL DEFAULT NULL,
    user_role   VARCHAR(30)         NULL DEFAULT NULL,
    action      VARCHAR(100)    NOT NULL,
    module      VARCHAR(100)    NOT NULL,
    description TEXT                NULL DEFAULT NULL,
    reference   VARCHAR(100)        NULL DEFAULT NULL,
    ip_address  VARCHAR(45)         NULL DEFAULT NULL,
    user_agent  VARCHAR(500)        NULL DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user    (user_id),
    KEY idx_log_created (created_at),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: system_settings  (Key-value configuration store)
-- ============================================================
CREATE TABLE system_settings (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100)    NOT NULL,
    setting_value TEXT                NULL DEFAULT NULL,
    label         VARCHAR(200)        NULL DEFAULT NULL,
    description   TEXT                NULL DEFAULT NULL,
    updated_by    INT UNSIGNED        NULL DEFAULT NULL,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key),
    CONSTRAINT fk_setting_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: tracking_sequences  (Atomic counters for tracking codes)
-- ============================================================
CREATE TABLE tracking_sequences (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefix   VARCHAR(20)  NOT NULL,
    year     YEAR         NOT NULL,
    last_seq INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seq_prefix_year (prefix, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- ── Default user accounts ─────────────────────────────────
--
--  Temporary password : password123
--  Hash               : bcrypt cost 10, generated by XAMPP PHP 8
--  Verified           : password_verify('password123', hash) = TRUE
--
--  must_change_password = 1 means both accounts MUST change
--  their password on first login before reaching the dashboard.
--
INSERT INTO users
    (employee_id, full_name, email, password_hash, role, is_active, must_change_password)
VALUES
    (
        'TCC-REG-001',
        'System Registrar',
        'registrar@tcc.edu.ph',
        '$2y$10$FqMwPjvINF44VD5c3gOqUeORPFaRbVA26niDavuVf5NqRifUtrpUS',
        'registrar',
        1,
        1
    );

INSERT INTO users
    (employee_id, full_name, email, password_hash, role, is_active, must_change_password, created_by)
VALUES
    (
        'TCC-STF-001',
        'Sample Staff',
        'staff@tcc.edu.ph',
        '$2y$10$FqMwPjvINF44VD5c3gOqUeORPFaRbVA26niDavuVf5NqRifUtrpUS',
        'staff',
        1,
        1,
        1
    );

-- ── Default student account ───────────────────────────────
--  Same temporary password (password123). Student accounts
--  have no must_change_password flag — sign-in goes straight
--  to the portal.
INSERT INTO students
    (student_no, first_name, middle_name, last_name, email, contact_number, password_hash, is_active)
VALUES
    (
        'TCC-STD-001',
        'Sample',
        'D.',
        'Student',
        'student@tcc.edu.ph',
        '09123456789',
        '$2y$10$FqMwPjvINF44VD5c3gOqUeORPFaRbVA26niDavuVf5NqRifUtrpUS',
        1
    );

-- ── Academic programs ─────────────────────────────────────
INSERT INTO programs (code, name, department) VALUES
    ('BSIT',    'Bachelor of Science in Information Technology',   'College of Computing and Technology'),
    ('BSCS',    'Bachelor of Science in Computer Science',         'College of Computing and Technology'),
    ('BSED',    'Bachelor of Secondary Education',                  'College of Education'),
    ('BEED',    'Bachelor of Elementary Education',                 'College of Education'),
    ('BSBA',    'Bachelor of Science in Business Administration',   'College of Business'),
    ('BSA',     'Bachelor of Science in Accountancy',              'College of Business'),
    ('BSN',     'Bachelor of Science in Nursing',                  'College of Nursing and Allied Health'),
    ('BSHM',    'Bachelor of Science in Hospitality Management',   'College of Hospitality'),
    ('BSTM',    'Bachelor of Science in Tourism Management',       'College of Hospitality'),
    ('BSCE',    'Bachelor of Science in Civil Engineering',        'College of Engineering'),
    ('AB-COMM', 'Bachelor of Arts in Communication',              'College of Arts and Sciences'),
    ('ABPS',    'Bachelor of Arts in Political Science',          'College of Arts and Sciences');

-- ── Registrar document types ──────────────────────────────
INSERT INTO document_types (code, name, description, fee, processing_days) VALUES
    ('TOR',       'Transcript of Records',            'Official academic transcript of student records',                 150.00, 5),
    ('COE',       'Certificate of Enrollment',        'Certifies that the student is currently enrolled',                50.00, 2),
    ('COG',       'Certificate of Good Moral',        'Certificate of good moral character',                             50.00, 2),
    ('COR',       'Certificate of Registration',      'Official certificate of registration for current semester',       50.00, 2),
    ('DIPLOMA',   'Diploma',                          'Official diploma for graduates',                                  300.00, 10),
    ('FORM137',   'Form 137 / Permanent Record',      'Permanent student academic record',                               100.00, 5),
    ('FORM138',   'Form 138 / Report Card',           'Official report card',                                             75.00, 3),
    ('HONORABLE', 'Honorable Dismissal',              'Document for students transferring to another institution',        100.00, 5),
    ('CERTGRAD',  'Certificate of Graduation',        'Certifies that the student has completed the program',             75.00, 3),
    ('AUTHCOPY',  'Authenticated Copy of Documents',  'Authenticated / certified copy of academic documents',             75.00, 3),
    ('OTHER',     'Other Document',                   'Other registrar document not listed above',                        50.00, 3);

-- ── System settings ───────────────────────────────────────
INSERT INTO system_settings (setting_key, setting_value, label, description) VALUES
    ('school_name',          'Tampakan Community College',            'School Name',           'Official name of the institution'),
    ('school_short_name',    'TCC',                                   'School Short Name',     'Abbreviated name used in tracking codes'),
    ('school_address',       'Tampakan, South Cotabato, Philippines', 'School Address',        'Official address of the institution'),
    ('school_email',         'registrar@tcc.edu.ph',                  'Registrar Email',       'Official registrar email address'),
    ('school_phone',         '+63 83 000 0000',                       'School Phone',          'Official school contact number'),
    ('school_website',       'https://tcc.edu.ph',                    'School Website',        'Official school website'),
    ('smtp_host',            'smtp.gmail.com',                        'SMTP Host',             'SMTP server hostname for email notifications'),
    ('smtp_port',            '587',                                   'SMTP Port',             'SMTP server port'),
    ('smtp_user',            '',                                      'SMTP Username',         'SMTP account username'),
    ('smtp_pass',            '',                                      'SMTP Password',         'SMTP account password'),
    ('smtp_from_name',       'TCC Registrar',                         'Email From Name',       'Display name for outgoing emails'),
    ('smtp_encryption',      'tls',                                   'SMTP Encryption',       'SMTP encryption: tls or ssl'),
    ('notifications_enabled','1',                                     'Enable Notifications',  '1 to enable email notifications, 0 to disable'),
    ('current_school_year',  '2026-2027',                             'Current School Year',   'Current academic year'),
    ('current_semester',     '1st Semester',                          'Current Semester',      'Current semester'),
    ('pickup_slot_capacity', '10',                                    'Pickup Slot Capacity',  'Maximum pickup appointments allowed per slot per day'),
    ('pickup_slots',         '8:00 AM – 10:00 AM|10:00 AM – 12:00 PM|1:00 PM – 3:00 PM|3:00 PM – 5:00 PM', 'Pickup Time Slots', 'Pipe-separated list of pickup time slots offered per day');

-- ── Tracking code sequences ───────────────────────────────
INSERT INTO tracking_sequences (prefix, year, last_seq) VALUES
    ('TCC-REG', YEAR(CURDATE()), 0),
    ('TCC-DOC', YEAR(CURDATE()), 0),
    ('TCC-VRF', YEAR(CURDATE()), 0);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
