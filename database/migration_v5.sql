-- ════════════════════════════════════════════════════════════════════
-- TCC RRS — Migration v5
-- In-app student notifications + pickup appointment scheduling.
--
--   student_notifications — per-account notification feed shown in the
--                           student portal (bell + notifications page)
--   document_requests.pickup_date / pickup_slot / pickup_booked_at —
--                           pickup slot the student booked once their
--                           request is Ready for Release
--
-- Run once against the tcc_rrs database.
-- ════════════════════════════════════════════════════════════════════
ALTER TABLE document_requests
    ADD COLUMN pickup_date      DATE         NULL DEFAULT NULL AFTER released_at,
    ADD COLUMN pickup_slot      VARCHAR(40)  NULL DEFAULT NULL AFTER pickup_date,
    ADD COLUMN pickup_booked_at DATETIME     NULL DEFAULT NULL AFTER pickup_slot;

CREATE TABLE IF NOT EXISTS student_notifications (
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

INSERT IGNORE INTO system_settings (setting_key, setting_value, label, description) VALUES
    ('pickup_slot_capacity', '10', 'Pickup Slot Capacity', 'Maximum pickup appointments allowed per slot per day'),
    ('pickup_slots', '8:00 AM – 10:00 AM|10:00 AM – 12:00 PM|1:00 PM – 3:00 PM|3:00 PM – 5:00 PM', 'Pickup Time Slots', 'Pipe-separated list of pickup time slots offered per day');
