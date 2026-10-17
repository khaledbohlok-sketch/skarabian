-- 023 How each salary is paid (cash, bank transfer, cheque ...). Additions only: two new empty columns, no existing
-- value is changed. To undo: database/rollback/023_salary_payment_down.sql
SET NAMES utf8mb4;

-- The employee's usual way (a "Payment methods" dropdown value); the payroll copies it to each month's line
ALTER TABLE employees ADD COLUMN salary_method_id INT UNSIGNED NULL AFTER bank_iban;

-- This month's way for the line (chosen while the payroll is a draft); paying uses it and the payslip prints it
ALTER TABLE payroll_lines ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER net_qar;
