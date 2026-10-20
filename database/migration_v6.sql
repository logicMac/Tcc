-- ════════════════════════════════════════════════════════════════════
-- TCC RRS — Migration v6
-- Students get a must_change_password flag so accounts auto-created
-- during pre-registration force a password change on first login.
--
-- Run once against the tcc_rrs database.
-- ════════════════════════════════════════════════════════════════════
ALTER TABLE students
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;
