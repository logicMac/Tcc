-- ============================================================
--  TCC RRS – Migration v2
--  Adds: student portal accounts, record archival, and
--        issued-document QR verification.
--
--  Safe to run on an existing tcc_rrs database AND on both
--  MySQL and MariaDB — every change is guarded by
--  information_schema checks, so re-running is a no-op.
--
--  Import via phpMyAdmin → SQL tab (choose tcc_rrs first),
--  or run:  mysql -u root tcc_rrs < migration_v2.sql
-- ============================================================
USE tcc_rrs;

-- ── Student portal accounts ──────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    student_no      VARCHAR(30)          NULL DEFAULT NULL,
    first_name      VARCHAR(100)     NOT NULL,
    middle_name     VARCHAR(100)         NULL DEFAULT NULL,
    last_name       VARCHAR(100)     NOT NULL,
    suffix          VARCHAR(20)          NULL DEFAULT NULL,
    email           VARCHAR(150)     NOT NULL,
    contact_number  VARCHAR(20)      NOT NULL,
    password_hash   VARCHAR(255)     NOT NULL,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    last_login      DATETIME             NULL DEFAULT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Issued documents (QR verification) ───────────────────────
CREATE TABLE IF NOT EXISTS issued_documents (
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

-- ── New columns on pre_registrations / document_requests ─────
-- Each ALTER runs only when the column is missing.
SET @tbl := 'pre_registrations';
SET @stmt := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD COLUMN student_account_id INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'student_account_id'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'is_archived'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'archived_at'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'archived_by'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD COLUMN student_account_id INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND COLUMN_NAME = 'student_account_id'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND COLUMN_NAME = 'is_archived'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND COLUMN_NAME = 'archived_at'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND COLUMN_NAME = 'archived_by'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Indexes ──────────────────────────────────────────────────
SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD INDEX idx_reg_student (student_account_id)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND INDEX_NAME = 'idx_reg_student'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD INDEX idx_reg_archived (is_archived)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND INDEX_NAME = 'idx_reg_archived'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD INDEX idx_doc_student (student_account_id)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND INDEX_NAME = 'idx_doc_student'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD INDEX idx_doc_archived (is_archived)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND INDEX_NAME = 'idx_doc_archived'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Foreign keys ─────────────────────────────────────────────
SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD CONSTRAINT fk_reg_student FOREIGN KEY (student_account_id) REFERENCES students (id) ON DELETE SET NULL',
    'SELECT 1')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND CONSTRAINT_NAME = 'fk_reg_student'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pre_registrations ADD CONSTRAINT fk_reg_archiver FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND CONSTRAINT_NAME = 'fk_reg_archiver'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD CONSTRAINT fk_doc_student FOREIGN KEY (student_account_id) REFERENCES students (id) ON DELETE SET NULL',
    'SELECT 1')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND CONSTRAINT_NAME = 'fk_doc_student'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE document_requests ADD CONSTRAINT fk_doc_archiver FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests' AND CONSTRAINT_NAME = 'fk_doc_archiver'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Verification-code sequence ───────────────────────────────
INSERT IGNORE INTO tracking_sequences (prefix, year, last_seq) VALUES
    ('TCC-VRF', YEAR(CURDATE()), 0);

-- ── Backfill: link existing submissions to accounts by email ─
UPDATE pre_registrations pr
JOIN students s ON s.email = pr.email
SET pr.student_account_id = s.id
WHERE pr.student_account_id IS NULL;

UPDATE document_requests dr
JOIN students s ON s.email = dr.email
SET dr.student_account_id = s.id
WHERE dr.student_account_id IS NULL;
