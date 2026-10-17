-- 020 Indexes and fixes
--  * payroll_loan_allocations: how much of each paid salary line's advances went to which advance/loan, so deleting
--    the salary payment gives back exactly that (before: the repayment and the give-back could differ)
--  * inventory unit prices kept to 4 decimals (weighted average cost); totals stay rounded to 2
--  * indexes for lookups by linked record. Each one is added only when its column exists and no index starts with
--    that column yet (information_schema check + PREPARE, which works the same on MySQL 8 and MariaDB 10.6+), so
--    this file is safe to run once on any copy of the database.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_loan_allocations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_line_id INT UNSIGNED NOT NULL,
  loan_id         INT UNSIGNED NOT NULL,
  amount_qar      DECIMAL(12,2) NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pla_line (payroll_line_id),
  KEY idx_pla_loan (loan_id),
  CONSTRAINT fk_pla_line FOREIGN KEY (payroll_line_id) REFERENCES payroll_lines (id) ON DELETE CASCADE,
  CONSTRAINT fk_pla_loan FOREIGN KEY (loan_id) REFERENCES employee_loans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE inventory_items MODIFY unit_price_qar DECIMAL(14,4) NOT NULL DEFAULT 0;
ALTER TABLE stock_movements MODIFY unit_price_qar DECIMAL(14,4) NOT NULL DEFAULT 0;
ALTER TABLE purchase_order_items MODIFY unit_price_qar DECIMAL(14,4) NOT NULL;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'stock_movements' AND column_name = 'health_record_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'stock_movements' AND column_name = 'health_record_id' AND seq_in_index = 1),
  'ALTER TABLE stock_movements ADD INDEX idx_sm_health (health_record_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'stock_movements' AND column_name = 'diet_log_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'stock_movements' AND column_name = 'diet_log_id' AND seq_in_index = 1),
  'ALTER TABLE stock_movements ADD INDEX idx_sm_diet (diet_log_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'invoice_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'invoice_id' AND seq_in_index = 1),
  'ALTER TABLE bills ADD INDEX idx_b_invoice (invoice_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'payroll_run_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'payroll_run_id' AND seq_in_index = 1),
  'ALTER TABLE bills ADD INDEX idx_b_payroll (payroll_run_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'purchase_order_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bills' AND column_name = 'purchase_order_id' AND seq_in_index = 1),
  'ALTER TABLE bills ADD INDEX idx_b_po (purchase_order_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bill_payments' AND column_name = 'payroll_line_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bill_payments' AND column_name = 'payroll_line_id' AND seq_in_index = 1),
  'ALTER TABLE bill_payments ADD INDEX idx_bp_payroll_line (payroll_line_id)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'activity_log' AND column_name = 'module')
  AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'activity_log' AND column_name = 'module' AND seq_in_index = 1),
  'ALTER TABLE activity_log ADD INDEX idx_al_module (module)', 'DO 0'));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
