-- 024 Attendance by QR: staff ID cards with a signed QR, a gate scanner, its log and the "Attendance kiosk" login.
-- Additions only: no existing value is changed. To undo: database/rollback/024_qr_attendance_down.sql
SET NAMES utf8mb4;

-- The card's number: "Reissue card" adds 1, and the QR of the old (lost) card stops working at once
ALTER TABLE employees ADD COLUMN card_no SMALLINT UNSIGNED NOT NULL DEFAULT 1;

-- Where the day's row came from: manual (typed on the sheet or form), qr (gate scanner), qr_offline (scanned while the
-- gate phone had no internet) or typed (staff number typed at the gate with the manager PIN)
ALTER TABLE attendance ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'manual' AFTER notes;

-- Every scan at the gate, accepted or not (the attendance row is filled from it)
CREATE TABLE attendance_scans (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id  INT UNSIGNED NULL,
  work_date    DATE NULL,
  scanned_at   DATETIME NOT NULL,
  received_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  action       ENUM('in','out','ignored','rejected') NOT NULL,
  reason       VARCHAR(40) NULL,
  source       VARCHAR(16) NOT NULL DEFAULT 'qr',
  card_no      SMALLINT UNSIGNED NULL,
  device       VARCHAR(80) NULL,
  user_id      INT UNSIGNED NULL,
  KEY idx_scan_emp (employee_id, scanned_at),
  KEY idx_scan_date (work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The gate phone's login: a role that can only record scans. General Manager and HR Officer may open the scanner too.
INSERT IGNORE INTO roles (slug, name_en, name_ar, is_system, require_2fa, horse_scope, edit_window)
  VALUES ('attendance_kiosk', 'Attendance kiosk', 'جهاز الحضور', 1, 0, 'all', 'any');
INSERT IGNORE INTO role_permissions (role_id, module, action)
  SELECT id, 'attendance_scan', 'create' FROM roles WHERE slug IN ('attendance_kiosk', 'general_manager', 'hr_officer');

-- One working day for everyone: start, end, minutes of grace before "late", the longest shift (a check-out after
-- midnight belongs to the day the shift started) and when the end-of-day list is sent
INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('hr.default_in', '06:00'), ('hr.default_out', '15:00'), ('hr.grace_minutes', '10'), ('hr.max_shift_hours', '16'),
  ('hr.brief_time', '16:00'), ('hr.scan_ips', '');
