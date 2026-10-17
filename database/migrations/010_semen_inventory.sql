-- 010 Semen Inventory (frozen stallion semen stored in liquid nitrogen tanks).
SET NAMES utf8mb4;

CREATE TABLE straw_colors (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en     VARCHAR(60) NOT NULL,
  name_ar     VARCHAR(60) NULL,
  hex         VARCHAR(7) NOT NULL DEFAULT '#8a8f98',
  sort        INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_sc_active (active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tanks (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                  VARCHAR(100) NOT NULL,
  location              VARCHAR(150) NULL,
  notes                 TEXT NULL,
  last_refill_date      DATE NULL,
  refill_interval_days  INT UNSIGNED NULL,  -- used to compute the "refill due" reminder; leave empty to disable
  created_by            INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL,
  deleted_at            DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tank_refills (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tank_id     INT UNSIGNED NOT NULL,
  refill_date DATE NOT NULL,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trf_tank (tank_id, refill_date),
  CONSTRAINT fk_trf_tank FOREIGN KEY (tank_id) REFERENCES tanks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canisters (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tank_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_can_tank (tank_id),
  CONSTRAINT fk_can_tank FOREIGN KEY (tank_id) REFERENCES tanks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per batch/collection of a stallion's straws in one canister. The straws count changes
-- ONLY through semen_movements (see below) — never updated directly by an edit form.
CREATE TABLE semen_stock (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stallion_id           INT UNSIGNED NULL,        -- linked to horses (stallions only); NULL for an outside stallion
  stallion_name_free    VARCHAR(150) NULL,        -- free-text name when stallion_id is NULL
  batch_label           VARCHAR(60) NULL,         -- "Part 1", "Part 2"...
  canister_id           INT UNSIGNED NOT NULL,
  straws                INT UNSIGNED NOT NULL DEFAULT 0,
  straws_per_dose_min   INT UNSIGNED NOT NULL DEFAULT 1,
  straws_per_dose_max   INT UNSIGNED NOT NULL DEFAULT 1,   -- equals min when not a range
  straw_size            VARCHAR(20) NULL,         -- e.g. 0.25ml, 0.5ml
  color_id              INT UNSIGNED NULL,
  owner_type            ENUM('sk','client') NOT NULL DEFAULT 'sk',
  owner_party_id        INT UNSIGNED NULL,
  report_date           DATE NULL,                -- set from the last confirmed physical count
  notes                 TEXT NULL,
  created_by            INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL,
  archived_at           DATETIME NULL,
  deleted_at            DATETIME NULL,
  KEY idx_ss_stallion (stallion_id),
  KEY idx_ss_canister (canister_id),
  KEY idx_ss_color (color_id),
  CONSTRAINT fk_ss_stallion FOREIGN KEY (stallion_id) REFERENCES horses(id),
  CONSTRAINT fk_ss_canister FOREIGN KEY (canister_id) REFERENCES canisters(id),
  CONSTRAINT fk_ss_color FOREIGN KEY (color_id) REFERENCES straw_colors(id),
  CONSTRAINT fk_ss_party FOREIGN KEY (owner_party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger (no edit, no delete — matches stock_movements for inventory items).
CREATE TABLE semen_movements (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stock_id          INT UNSIGNED NOT NULL,
  type              ENUM('in','out','transfer','adjustment') NOT NULL,
  movement_date     DATE NOT NULL,
  straws_delta      INT NOT NULL,              -- signed: + for in/transfer-in/adjustment-up, - for out/transfer-out/adjustment-down
  doses             INT UNSIGNED NULL,         -- doses this OUT movement represents (informational)
  recipient         VARCHAR(150) NULL,         -- OUT: stud/farm name
  mare_id           INT UNSIGNED NULL,         -- OUT: optional
  related_stock_id  INT UNSIGNED NULL,         -- TRANSFER: the other leg
  reason            VARCHAR(255) NULL,         -- required (enforced in code) for ADJUSTMENT
  notes             TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_stock (stock_id, movement_date),
  KEY idx_sm_type (type, movement_date),
  CONSTRAINT fk_sm_stock FOREIGN KEY (stock_id) REFERENCES semen_stock(id),
  CONSTRAINT fk_sm_mare FOREIGN KEY (mare_id) REFERENCES horses(id),
  CONSTRAINT fk_sm_related FOREIGN KEY (related_stock_id) REFERENCES semen_stock(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO straw_colors (name_en, name_ar, hex, sort) VALUES
  ('Blue', 'أزرق', '#2563eb', 10),
  ('White', 'أبيض', '#e5e7eb', 20),
  ('Yellow', 'أصفر', '#eab308', 30),
  ('Red', 'أحمر', '#dc2626', 40),
  ('Green', 'أخضر', '#16a34a', 50),
  ('Orange', 'برتقالي', '#ea580c', 60);

-- Grant the new "semen_stock" permission module to Owner, General Manager and Veterinarian.
INSERT IGNORE INTO role_permissions (role_id, module, action)
SELECT r.id, 'semen_stock', a.action
FROM roles r
CROSS JOIN (
  SELECT 'view' AS action UNION ALL SELECT 'create' UNION ALL SELECT 'edit' UNION ALL
  SELECT 'delete' UNION ALL SELECT 'approve' UNION ALL SELECT 'export' UNION ALL SELECT 'print'
) a
WHERE r.slug IN ('owner', 'general_manager', 'veterinarian');
