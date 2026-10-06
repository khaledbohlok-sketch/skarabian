-- 004 Finance (accounts, bills, payments, invoices, purchase orders, payroll, budgets) and Inventory
-- Every amount is stored in QAR with 2 decimals. Foreign-currency transactions keep the original amount,
-- currency and the exchange rate used ("1 unit = X QAR"); amount_qar is calculated once at entry.
SET NAMES utf8mb4;

CREATE TABLE accounts (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(120) NOT NULL,
  type                 ENUM('cash','bank','petty_cash') NOT NULL,
  bank_name            VARCHAR(120) NULL,
  account_no           VARCHAR(255) NULL,      -- encrypted
  opening_balance_qar  DECIMAL(14,2) NOT NULL DEFAULT 0,
  opening_date         DATE NULL,
  active               TINYINT(1) NOT NULL DEFAULT 1,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL,
  deleted_at           DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_transfers (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_account_id  INT UNSIGNED NOT NULL,
  to_account_id    INT UNSIGNED NOT NULL,
  transfer_date    DATE NOT NULL,
  amount_qar       DECIMAL(14,2) NOT NULL,
  reference_no     VARCHAR(60) NULL,
  notes            VARCHAR(255) NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at       DATETIME NULL,
  CONSTRAINT fk_at_from FOREIGN KEY (from_account_id) REFERENCES accounts(id),
  CONSTRAINT fk_at_to FOREIGN KEY (to_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finance_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id   INT UNSIGNED NULL,
  type        ENUM('income','expense','liability','any') NOT NULL DEFAULT 'expense',
  name_en     VARCHAR(100) NOT NULL,
  name_ar     VARCHAR(100) NULL,
  sort        INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_fc (parent_id, name_en),
  CONSTRAINT fk_fc_parent FOREIGN KEY (parent_id) REFERENCES finance_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en     VARCHAR(100) NOT NULL,
  name_ar     VARCHAR(100) NULL,
  category    ENUM('clinic','cosmetics','equipment','feed','grooming','vitamins','other') NOT NULL,
  location    VARCHAR(120) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id    INT UNSIGNED NOT NULL,
  name_en         VARCHAR(150) NOT NULL,
  name_ar         VARCHAR(150) NULL,
  sku             VARCHAR(60) NULL,
  unit            VARCHAR(30) NOT NULL DEFAULT 'pcs',
  quantity        DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit_price_qar  DECIMAL(14,2) NOT NULL DEFAULT 0,
  min_quantity    DECIMAL(14,3) NOT NULL DEFAULT 0,
  supplier_id     INT UNSIGNED NULL,
  expiry_date     DATE NULL,
  notes           VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  archived_at     DATETIME NULL,
  deleted_at      DATETIME NULL,
  KEY idx_ii_inv (inventory_id, deleted_at),
  KEY idx_ii_low (quantity, min_quantity),
  KEY idx_ii_exp (expiry_date),
  KEY idx_ii_name (name_en),
  CONSTRAINT fk_ii_inv FOREIGN KEY (inventory_id) REFERENCES inventories(id),
  CONSTRAINT fk_ii_sup FOREIGN KEY (supplier_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number        VARCHAR(20) NOT NULL UNIQUE,              -- PO-YYYYMM-0001
  supplier_id   INT UNSIGNED NOT NULL,
  order_date    DATE NOT NULL,
  expected_date DATE NULL,
  category_id   INT UNSIGNED NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'QAR',
  exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1,
  total_qar     DECIMAL(14,2) NOT NULL DEFAULT 0,
  status        ENUM('draft','pending','approved','received','cancelled') NOT NULL DEFAULT 'draft',
  bill_id       INT UNSIGNED NULL,
  received_at   DATETIME NULL,
  notes         TEXT NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  KEY idx_po_status (status),
  CONSTRAINT fk_po_sup FOREIGN KEY (supplier_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  item_id          INT UNSIGNED NOT NULL,
  quantity         DECIMAL(14,3) NOT NULL,
  unit_price       DECIMAL(14,2) NOT NULL,        -- in PO currency
  unit_price_qar   DECIMAL(14,2) NOT NULL,
  line_total_qar   DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_runs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period        CHAR(7) NOT NULL UNIQUE,                  -- YYYY-MM
  status        ENUM('draft','pending','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
  total_net_qar DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  approved_by   INT UNSIGNED NULL,
  approved_at   DATETIME NULL,
  deleted_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_lines (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id  INT UNSIGNED NOT NULL,
  employee_id     INT UNSIGNED NOT NULL,
  basic_qar       DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances_qar  DECIMAL(12,2) NOT NULL DEFAULT 0,
  overtime_qar    DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions_qar  DECIMAL(12,2) NOT NULL DEFAULT 0,
  advances_qar    DECIMAL(12,2) NOT NULL DEFAULT 0,       -- advance/loan repayments
  net_qar         DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes           VARCHAR(255) NULL,
  bill_id         INT UNSIGNED NULL,
  payslip_document_id INT UNSIGNED NULL,
  paid_at         DATETIME NULL,
  UNIQUE KEY uq_pl (payroll_run_id, employee_id),
  CONSTRAINT fk_pl_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_emp FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number        VARCHAR(20) NOT NULL UNIQUE,               -- INV-YYYYMM-0001
  party_id      INT UNSIGNED NOT NULL,
  invoice_type  ENUM('boarding','other') NOT NULL DEFAULT 'other',
  invoice_date  DATE NOT NULL,
  due_date      DATE NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'QAR',
  exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1,
  total_original DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_qar     DECIMAL(14,2) NOT NULL DEFAULT 0,
  horse_id      INT UNSIGNED NULL,
  embryo_id     INT UNSIGNED NULL,
  bill_id       INT UNSIGNED NULL,                         -- income record used by reports and payments
  status        ENUM('draft','issued','partially_paid','paid','cancelled') NOT NULL DEFAULT 'issued',
  notes         TEXT NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  CONSTRAINT fk_inv_party FOREIGN KEY (party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id   INT UNSIGNED NOT NULL,
  description  VARCHAR(255) NOT NULL,
  quantity     DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit_price   DECIMAL(14,2) NOT NULL,
  line_total   DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bills (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number            VARCHAR(20) NOT NULL UNIQUE,                 -- BILL-YYYYMM-0001
  type              ENUM('income','expense','liability') NOT NULL,
  category_id       INT UNSIGNED NULL,
  subcategory_id    INT UNSIGNED NULL,
  party_id          INT UNSIGNED NULL,
  description       VARCHAR(255) NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'QAR',
  exchange_rate     DECIMAL(18,6) NOT NULL DEFAULT 1,            -- 1 unit = X QAR, saved per transaction
  amount_original   DECIMAL(14,2) NOT NULL,
  amount_qar        DECIMAL(14,2) NOT NULL,
  paid_qar          DECIMAL(14,2) NOT NULL DEFAULT 0,
  bill_date         DATE NOT NULL,
  due_date          DATE NULL,
  payment_method_id INT UNSIGNED NULL,
  account_id        INT UNSIGNED NULL,
  reference_no      VARCHAR(80) NULL,
  status            ENUM('draft','pending','approved','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  horse_id          INT UNSIGNED NULL,
  embryo_id         INT UNSIGNED NULL,
  employee_id       INT UNSIGNED NULL,
  item_id           INT UNSIGNED NULL,
  purchase_order_id INT UNSIGNED NULL,
  payroll_run_id    INT UNSIGNED NULL,
  invoice_id        INT UNSIGNED NULL,
  approved_by       INT UNSIGNED NULL,
  approved_at       DATETIME NULL,
  notes             TEXT NULL,
  legacy_id         INT UNSIGNED NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL,
  deleted_at        DATETIME NULL,
  KEY idx_b_date (bill_date, type, status, deleted_at),
  KEY idx_b_status (status, due_date),
  KEY idx_b_cat (category_id),
  KEY idx_b_party (party_id),
  KEY idx_b_horse (horse_id),
  KEY idx_b_embryo (embryo_id),
  KEY idx_b_emp (employee_id),
  CONSTRAINT fk_b_cat FOREIGN KEY (category_id) REFERENCES finance_categories(id),
  CONSTRAINT fk_b_party FOREIGN KEY (party_id) REFERENCES parties(id),
  CONSTRAINT fk_b_horse FOREIGN KEY (horse_id) REFERENCES horses(id),
  CONSTRAINT fk_b_embryo FOREIGN KEY (embryo_id) REFERENCES embryos(id),
  CONSTRAINT fk_b_emp FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_b_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
  CONSTRAINT fk_b_acc FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bill_payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_id       INT UNSIGNED NOT NULL,
  payment_date  DATE NOT NULL,
  amount_qar    DECIMAL(14,2) NOT NULL,
  account_id    INT UNSIGNED NULL,
  payment_method_id INT UNSIGNED NULL,
  reference_no  VARCHAR(80) NULL,
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  KEY idx_bp (bill_id),
  KEY idx_bp_date (payment_date, account_id),
  CONSTRAINT fk_bp_bill FOREIGN KEY (bill_id) REFERENCES bills(id),
  CONSTRAINT fk_bp_acc FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budgets (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED NOT NULL,
  period_type  ENUM('month','year') NOT NULL,
  year         SMALLINT UNSIGNED NOT NULL,
  month        TINYINT UNSIGNED NULL,
  amount_qar   DECIMAL(14,2) NOT NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL,
  deleted_at   DATETIME NULL,
  UNIQUE KEY uq_budget (category_id, period_type, year, month),
  CONSTRAINT fk_bud_cat FOREIGN KEY (category_id) REFERENCES finance_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id           INT UNSIGNED NOT NULL,
  direction         ENUM('in','out','adjust') NOT NULL,
  quantity          DECIMAL(14,3) NOT NULL,          -- positive; adjust may be negative
  unit_price_qar    DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_qar         DECIMAL(14,2) NOT NULL DEFAULT 0,
  movement_date     DATE NOT NULL,
  horse_id          INT UNSIGNED NULL,               -- cost of usage is allocated to this horse
  health_record_id  INT UNSIGNED NULL,
  diet_log_id       INT UNSIGNED NULL,
  purchase_order_id INT UNSIGNED NULL,
  bill_id           INT UNSIGNED NULL,
  note              VARCHAR(255) NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_item (item_id, movement_date),
  KEY idx_sm_horse (horse_id),
  CONSTRAINT fk_sm_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
  CONSTRAINT fk_sm_horse FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE health_records ADD CONSTRAINT fk_hr_item FOREIGN KEY (item_id) REFERENCES inventory_items(id);
ALTER TABLE diet_plans ADD CONSTRAINT fk_dp_item FOREIGN KEY (item_id) REFERENCES inventory_items(id);
ALTER TABLE diet_logs ADD CONSTRAINT fk_dl_item FOREIGN KEY (item_id) REFERENCES inventory_items(id);
