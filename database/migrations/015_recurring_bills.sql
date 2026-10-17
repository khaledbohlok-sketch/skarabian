-- 015 Recurring bills: a template (rent, a feed contract, a subscription…) that creates the same bill on a schedule.
-- The daily "recurring" task creates each bill on its date, catching up dates it missed, and moves next_date on.
-- Bills it creates point back to their template (bills.recurring_bill_id), at most one per template and date.
SET NAMES utf8mb4;

CREATE TABLE recurring_bills (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150) NOT NULL,                 -- e.g. "Stable rent"
  type              ENUM('income','expense','liability') NOT NULL DEFAULT 'expense',
  category_id       INT UNSIGNED NOT NULL,
  subcategory_id    INT UNSIGNED NULL,
  party_id          INT UNSIGNED NULL,
  description       VARCHAR(255) NULL,                     -- the bills' description (the period is added to it)
  currency          CHAR(3) NOT NULL DEFAULT 'QAR',
  amount_original   DECIMAL(14,2) NOT NULL,                -- in the currency; the QAR value uses the rate of the day
  frequency         ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  start_date        DATE NOT NULL,                         -- the first bill's date; later ones keep its day of the month
  end_date          DATE NULL,                             -- no bills after this date (empty = no end)
  next_date         DATE NOT NULL,                         -- the date of the next bill to create
  due_days          SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- due date = bill date + this many days (0 = no due date)
  horse_id          INT UNSIGNED NULL,
  employee_id       INT UNSIGNED NULL,
  active            TINYINT(1) NOT NULL DEFAULT 1,
  bills_created     INT UNSIGNED NOT NULL DEFAULT 0,
  last_bill_id      INT UNSIGNED NULL,
  notes             TEXT NULL,
  saved_by          INT UNSIGNED NULL,                     -- who last saved it: its bills are their request (approval, no self-approval)
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL,
  deleted_at        DATETIME NULL,
  KEY idx_rb_next (active, next_date),
  CONSTRAINT fk_rb_cat FOREIGN KEY (category_id) REFERENCES finance_categories(id),
  CONSTRAINT fk_rb_party FOREIGN KEY (party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bills
  ADD COLUMN recurring_bill_id INT UNSIGNED NULL AFTER invoice_id,
  ADD UNIQUE KEY uq_b_recurring (recurring_bill_id, bill_date);
