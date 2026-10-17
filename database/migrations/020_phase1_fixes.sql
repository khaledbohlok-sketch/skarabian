-- 020 Phase 1 fixes (review of 8 Oct 2026). Additions only: new columns, one new table and new settings; no existing
-- value is changed (new columns are filled from data already there). To undo: database/rollback/020_phase1_fixes_down.sql
SET NAMES utf8mb4;

-- Payment terms per client / supplier: days after the bill date the bill falls due (empty = the default in Settings)
ALTER TABLE parties ADD COLUMN payment_terms_days SMALLINT UNSIGNED NULL AFTER tax_no;

-- View-only roles: the server refuses every change from them, whatever is ticked
ALTER TABLE roles ADD COLUMN read_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system;
-- When 2FA became required for the role: the start of the grace period for its users
ALTER TABLE roles ADD COLUMN twofa_since DATETIME NULL AFTER require_2fa;
UPDATE roles SET twofa_since = NOW() WHERE require_2fa = 1 AND twofa_since IS NULL;

-- Leave: paid or unpaid, kept on each request (the type's setting at the time)
ALTER TABLE leave_requests ADD COLUMN is_paid TINYINT(1) NULL AFTER days;
UPDATE leave_requests SET is_paid = IF(leave_type IN ('annual','sick','emergency'), 1, 0) WHERE is_paid IS NULL;

-- Public holidays: not counted as leave days
CREATE TABLE IF NOT EXISTS public_holidays (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  holiday_date  DATE NOT NULL,
  name_en       VARCHAR(120) NOT NULL,
  name_ar       VARCHAR(120) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  KEY idx_ph_date (holiday_date, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annual leave balance calculated from an opening balance on a date (set by HR / the Owner's check page)
ALTER TABLE employees ADD COLUMN leave_opening_balance DECIMAL(6,1) NULL AFTER leave_balance;
ALTER TABLE employees ADD COLUMN leave_opening_date DATE NULL AFTER leave_opening_balance;

-- Known devices: browser and network (recognised again when the browser lost its cookie), logins seen
ALTER TABLE known_devices ADD COLUMN browser VARCHAR(60) NULL AFTER device_hash;
ALTER TABLE known_devices ADD COLUMN ip_net VARCHAR(45) NULL AFTER browser;
ALTER TABLE known_devices ADD COLUMN logins INT UNSIGNED NOT NULL DEFAULT 1 AFTER country;

-- Employee numbers from a counter that never goes back (starts after the highest SK- number in use)
INSERT IGNORE INTO sequences (name, period, `last_value`)
  SELECT 'EMP', 'all', COALESCE(MAX(CAST(SUBSTRING(emp_no, 4) AS UNSIGNED)), 0) FROM employees WHERE emp_no REGEXP '^SK-[0-9]+$';

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('hr.weekend_days', '5'),
  ('hr.paid_leave_types', 'annual,sick,emergency'),
  ('hr.leave_balance_mode', 'typed'),
  ('finance.default_terms_days', '30'),
  ('security.twofa_grace_days', '7'),
  ('approval.owner_second', '0');
