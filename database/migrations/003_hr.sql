-- 003 Employees & HR
SET NAMES utf8mb4;

CREATE TABLE employees (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  emp_no             VARCHAR(20) NULL UNIQUE,
  name_en            VARCHAR(120) NOT NULL,
  name_ar            VARCHAR(120) NULL,
  gender             ENUM('m','f') NOT NULL DEFAULT 'm',
  photo_id           INT UNSIGNED NULL,
  nationality_id     INT UNSIGNED NULL,
  position_id        INT UNSIGNED NULL,       -- from list only: a phone number can never land here
  department_id      INT UNSIGNED NULL,
  phone              VARCHAR(30) NULL,
  email              VARCHAR(150) NULL,
  hire_date          DATE NULL,
  end_date           DATE NULL,
  status             ENUM('active','on_leave','suspended','left') NOT NULL DEFAULT 'active',
  basic_salary_qar   DECIMAL(12,2) NULL,      -- sensitive
  housing_allowance  DECIMAL(12,2) NULL,      -- sensitive
  transport_allowance DECIMAL(12,2) NULL,     -- sensitive
  other_allowance    DECIMAL(12,2) NULL,      -- sensitive
  bank_name          VARCHAR(255) NULL,       -- encrypted
  bank_iban          VARCHAR(255) NULL,       -- encrypted
  qid_no             VARCHAR(255) NULL,       -- encrypted
  qid_expiry         DATE NULL,
  passport_no        VARCHAR(255) NULL,       -- encrypted
  passport_expiry    DATE NULL,
  visa_expiry        DATE NULL,
  health_card_expiry DATE NULL,
  blood_group        VARCHAR(5) NULL,
  emergency_contact  VARCHAR(150) NULL,
  annual_leave_days  DECIMAL(5,1) NOT NULL DEFAULT 30,
  leave_balance      DECIMAL(6,1) NOT NULL DEFAULT 0,
  show_on_website    TINYINT(1) NOT NULL DEFAULT 0,
  public_title_en    VARCHAR(120) NULL,
  public_title_ar    VARCHAR(120) NULL,
  public_bio_en      TEXT NULL,
  public_bio_ar      TEXT NULL,
  specialties_en     VARCHAR(255) NULL,
  specialties_ar     VARCHAR(255) NULL,
  website_sort       INT NOT NULL DEFAULT 0,
  notes              TEXT NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL,
  archived_at        DATETIME NULL,
  deleted_at         DATETIME NULL,
  KEY idx_emp_status (status, deleted_at),
  KEY idx_emp_qid (qid_expiry),
  KEY idx_emp_pp (passport_expiry),
  KEY idx_emp_visa (visa_expiry),
  KEY idx_emp_hc (health_card_expiry),
  KEY idx_emp_name (name_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horse_assignments (
  horse_id     INT UNSIGNED NOT NULL,
  employee_id  INT UNSIGNED NOT NULL,
  role         VARCHAR(30) NOT NULL DEFAULT 'groom',
  assigned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (horse_id, employee_id),
  KEY idx_ha_emp (employee_id),
  CONSTRAINT fk_ha_h FOREIGN KEY (horse_id) REFERENCES horses(id),
  CONSTRAINT fk_ha_e FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id    INT UNSIGNED NOT NULL,
  work_date      DATE NOT NULL,
  status         ENUM('present','absent','late','leave','holiday','sick') NOT NULL DEFAULT 'present',
  check_in       TIME NULL,
  check_out      TIME NULL,
  overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  notes          VARCHAR(255) NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL,
  deleted_at     DATETIME NULL,
  UNIQUE KEY uq_att (employee_id, work_date),
  CONSTRAINT fk_att_e FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id  INT UNSIGNED NOT NULL,
  leave_type   ENUM('annual','sick','unpaid','emergency','other') NOT NULL DEFAULT 'annual',
  start_date   DATE NOT NULL,
  end_date     DATE NOT NULL,
  days         DECIMAL(5,1) NOT NULL,
  reason       VARCHAR(255) NULL,
  status       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  decided_by   INT UNSIGNED NULL,
  decided_at   DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL,
  deleted_at   DATETIME NULL,
  KEY idx_lr (employee_id, status),
  CONSTRAINT fk_lr_e FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_loans (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id        INT UNSIGNED NOT NULL,
  type               ENUM('advance','loan') NOT NULL,
  issue_date         DATE NOT NULL,
  amount_qar         DECIMAL(12,2) NOT NULL,
  monthly_deduction  DECIMAL(12,2) NOT NULL,
  balance_qar        DECIMAL(12,2) NOT NULL,
  notes              VARCHAR(255) NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL,
  deleted_at         DATETIME NULL,
  KEY idx_el (employee_id),
  CONSTRAINT fk_el_e FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD CONSTRAINT fk_users_emp FOREIGN KEY (employee_id) REFERENCES employees(id);
