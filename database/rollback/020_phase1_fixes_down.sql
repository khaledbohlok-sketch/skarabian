-- Undoes database update 020 (Phase 1 fixes). Run in phpMyAdmin only after restoring the previous program files.
-- Removes only what 020 added (holidays, payment terms and opening balances typed in since then are lost) and its line
-- in schema_migrations, so the update can be applied again later.
SET NAMES utf8mb4;

ALTER TABLE parties DROP COLUMN payment_terms_days;
ALTER TABLE roles DROP COLUMN read_only;
ALTER TABLE roles DROP COLUMN twofa_since;
ALTER TABLE leave_requests DROP COLUMN is_paid;
DROP TABLE IF EXISTS public_holidays;
ALTER TABLE employees DROP COLUMN leave_opening_balance;
ALTER TABLE employees DROP COLUMN leave_opening_date;
ALTER TABLE known_devices DROP COLUMN browser;
ALTER TABLE known_devices DROP COLUMN ip_net;
ALTER TABLE known_devices DROP COLUMN logins;
DELETE FROM settings WHERE `key` IN ('hr.weekend_days', 'hr.paid_leave_types', 'hr.leave_balance_mode', 'finance.default_terms_days',
  'security.twofa_grace_days', 'approval.owner_second', 'mail.verified_at', 'backup.offsite_last_ok', 'backup.offsite_last_error');
-- (the employee-number counter row 'EMP' stays: numbers already given must not be given again)
DELETE FROM schema_migrations WHERE version = '020_phase1_fixes.sql';
