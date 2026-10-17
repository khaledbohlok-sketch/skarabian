-- Undoes database update 023 (how each salary is paid). Run in phpMyAdmin only after restoring the previous program files.
-- Removes only what 023 added (the "Salary paid by" choices typed in since then are lost; payments already recorded keep
-- their method on the payment itself) and its line in schema_migrations, so the update can be applied again later.
SET NAMES utf8mb4;

ALTER TABLE payroll_lines DROP COLUMN payment_method_id;
ALTER TABLE employees DROP COLUMN salary_method_id;
DELETE FROM schema_migrations WHERE version = '023_salary_payment.sql';
