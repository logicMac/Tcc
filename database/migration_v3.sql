-- ============================================================
--  TCC RRS – Migration v3
--  Aligns pre_registrations with the printed
--  "APPLICATION FOR ADMISSION" form (TCC-RRMO-FORM-001).
--
--  Adds: birth place, citizenship, telephone number,
--        district/region on permanent address,
--        full mailing address block,
--        family information (father/mother/guardian/spouse),
--        schools attended history,
--        applicant photo, '2nd Undergraduate' enrollment type.
--
--  Idempotent: every ALTER is guarded by an
--  information_schema check — safe to re-run.
-- ============================================================
USE tcc_rrs;

-- ── Admission type: add '2nd Undergraduate' option ───────────
-- (MODIFY is naturally idempotent)
ALTER TABLE pre_registrations
    MODIFY COLUMN enrollment_type
    ENUM('New Student','Transferee','Returnee','Cross-Enrollee','2nd Undergraduate')
    NOT NULL DEFAULT 'New Student';

-- ── Personal information ─────────────────────────────────────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN birth_place VARCHAR(150) NULL DEFAULT NULL AFTER date_of_birth',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'birth_place');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN citizenship ENUM(''Filipino'',''Dual'',''Others'') NOT NULL DEFAULT ''Filipino'' AFTER nationality',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'citizenship');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN citizenship_specify VARCHAR(100) NULL DEFAULT NULL AFTER citizenship',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'citizenship_specify');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Contact: telephone number (item 3 — separate from mobile) ─
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN telephone_number VARCHAR(20) NULL DEFAULT NULL AFTER contact_number',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'telephone_number');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Permanent address extras (item 1: District, Region) ──────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN district VARCHAR(100) NULL DEFAULT NULL AFTER province',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'district');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN region VARCHAR(100) NULL DEFAULT NULL AFTER district',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'region');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Mailing address block (item 2) ───────────────────────────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_same_as_permanent TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_same_as_permanent');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_house_no_street VARCHAR(200) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_house_no_street');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_barangay VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_barangay');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_municipality_city VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_municipality_city');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_province VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_province');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_district VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_district');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mail_region VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mail_region');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Family information (item 12) ─────────────────────────────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN father_name VARCHAR(150) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'father_name');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN father_status ENUM(''Living'',''Deceased'') NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'father_status');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN father_contact VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'father_contact');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mother_name VARCHAR(150) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mother_name');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mother_status ENUM(''Living'',''Deceased'') NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mother_status');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN mother_contact VARCHAR(20) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'mother_contact');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN guardian_status ENUM(''Living'',''Deceased'') NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'guardian_status');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN spouse_name VARCHAR(150) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'spouse_name');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Schools attended (item 13) ───────────────────────────────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN elementary_school VARCHAR(200) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'elementary_school');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN elementary_year_graduated VARCHAR(10) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'elementary_year_graduated');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN jhs_school VARCHAR(200) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'jhs_school');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN jhs_year_completed VARCHAR(10) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'jhs_year_completed');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN shs_school VARCHAR(200) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'shs_school');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN shs_year_graduated VARCHAR(10) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'shs_year_graduated');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN college_school VARCHAR(200) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'college_school');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN college_year_attended VARCHAR(10) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'college_year_attended');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Applicant photo (2x2 box on the form) ────────────────────
SET @stmt := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pre_registrations ADD COLUMN photo_path VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_registrations' AND COLUMN_NAME = 'photo_path');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
