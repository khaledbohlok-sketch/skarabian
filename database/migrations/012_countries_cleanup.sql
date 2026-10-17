-- 012 Countries cleanup — run AFTER 011_countries.sql, and after you've reviewed/fixed any "unmatched"
-- rows it reported (re-run the matching UPDATE from 011 for any row you fixed, or set its *_code column
-- directly). This drops the old free-text/lookup columns and renames the new code columns into their
-- place, so the rest of the app can use the final column names.
SET NAMES utf8mb4;

ALTER TABLE employees
  DROP COLUMN nationality_id,
  CHANGE COLUMN nationality_code nationality CHAR(2) NULL;

ALTER TABLE parties
  DROP COLUMN nationality,
  DROP COLUMN country,
  CHANGE COLUMN nationality_code nationality CHAR(2) NULL,
  CHANGE COLUMN country_code country CHAR(2) NULL;

ALTER TABLE horses
  DROP COLUMN origin_country,
  CHANGE COLUMN origin_country_code origin_country CHAR(2) NULL;

ALTER TABLE shows
  DROP COLUMN country,
  CHANGE COLUMN country_code country CHAR(2) NULL;

-- The old lookups type='nationality' rows are no longer referenced by anything.
DELETE FROM lookups WHERE type = 'nationality';
