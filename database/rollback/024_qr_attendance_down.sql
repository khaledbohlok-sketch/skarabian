-- Undoes database update 024 (attendance by QR). Run in phpMyAdmin only after restoring the previous program files.
-- Removes only what 024 added: the card numbers (every card counts as card 1 again), the scans log, the "manual / QR"
-- mark on attendance rows (the rows themselves, their times and statuses stay), the kiosk role when no user has it,
-- the scanner permission and the hours settings — and its line in schema_migrations, so it can be applied again later.
SET NAMES utf8mb4;

DROP TABLE IF EXISTS attendance_scans;
ALTER TABLE attendance DROP COLUMN source;
ALTER TABLE employees DROP COLUMN card_no;
DELETE FROM role_permissions WHERE module = 'attendance_scan';
DELETE r FROM roles r LEFT JOIN users u ON u.role_id = r.id WHERE r.slug = 'attendance_kiosk' AND u.id IS NULL;
DELETE FROM settings WHERE `key` IN ('hr.default_in', 'hr.default_out', 'hr.grace_minutes', 'hr.max_shift_hours', 'hr.brief_time', 'hr.scan_ips', 'hr.scan_pin_hash');
DELETE FROM schema_migrations WHERE version = '024_qr_attendance.sql';
