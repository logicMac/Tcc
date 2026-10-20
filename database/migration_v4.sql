-- ════════════════════════════════════════════════════════════════════
-- TCC RRS — Migration v4
-- School credentials checklist (TCC-RRMO-FORM-001 "Credentials Presented")
--
--   credentials_submitted — JSON map { credential_key: "credentials/file.ext" }
--                           scans/photos the student attached with the form
--   credentials_presented — JSON map { credential_key: 1 }
--                           originals ticked off by the Admission Office
--
-- Run once against the tcc_rrs database.
-- ════════════════════════════════════════════════════════════════════
ALTER TABLE pre_registrations
    ADD COLUMN credentials_submitted JSON NULL DEFAULT NULL AFTER photo_path,
    ADD COLUMN credentials_presented JSON NULL DEFAULT NULL AFTER credentials_submitted;
