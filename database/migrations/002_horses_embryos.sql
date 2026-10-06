-- 002 Parties (clients/suppliers), horses and everything linked to a horse, embryos
SET NAMES utf8mb4;

CREATE TABLE parties (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type            ENUM('client','supplier','both') NOT NULL,
  name_en         VARCHAR(150) NOT NULL,
  name_ar         VARCHAR(150) NULL,
  contact_person  VARCHAR(120) NULL,
  id_number       VARCHAR(60) NULL,            -- ID / C.R. no. (transfer certificates)
  nationality     VARCHAR(60) NULL,
  phone           VARCHAR(40) NULL,
  email           VARCHAR(150) NULL,
  address         VARCHAR(255) NULL,
  country         VARCHAR(60) NULL,
  tax_no          VARCHAR(60) NULL,
  notes           TEXT NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  archived_at     DATETIME NULL,
  deleted_at      DATETIME NULL,
  KEY idx_parties_type (type, deleted_at),
  KEY idx_parties_name (name_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horses (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en              VARCHAR(120) NOT NULL,
  name_ar              VARCHAR(120) NULL,
  slug                 VARCHAR(140) NOT NULL UNIQUE,
  registration_no      VARCHAR(60) NULL,
  microchip            VARCHAR(60) NULL,
  passport_no          VARCHAR(60) NULL,
  passport_issue_date  DATE NULL,
  passport_issue_place VARCHAR(120) NULL,
  dob                  DATE NULL,
  sex                  ENUM('male','female','gelding') NOT NULL,
  category             ENUM('foal','colt','filly','stallion','mare','gelding') NOT NULL,
  category_locked      TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = set manually, cron will not re-suggest
  color_id             INT UNSIGNED NULL,
  breed_id             INT UNSIGNED NULL,
  bloodline            VARCHAR(120) NULL,
  breeder              VARCHAR(150) NULL,
  origin_country       VARCHAR(80) NULL,
  height_cm            SMALLINT UNSIGNED NULL,
  owner_type           ENUM('sk','client') NOT NULL DEFAULT 'sk',
  owner_party_id       INT UNSIGNED NULL,
  location_id          INT UNSIGNED NULL,
  status               ENUM('active','in_shelter','sold','transferred','deceased') NOT NULL DEFAULT 'active',
  is_external          TINYINT(1) NOT NULL DEFAULT 0,   -- pedigree-only horse not kept at SK
  sire_id              INT UNSIGNED NULL,
  dam_id               INT UNSIGNED NULL,
  is_favorite          TINYINT(1) NOT NULL DEFAULT 0,
  show_on_website      TINYINT(1) NOT NULL DEFAULT 0,
  breeding_stallion    TINYINT(1) NOT NULL DEFAULT 0,   -- stallion used in SK's own breeding programme (shown on the Breeding page)
  website_approved_at  DATETIME NULL,                   -- foals appear in "Latest Foals" only after Owner approval
  story_en             TEXT NULL,
  story_ar             TEXT NULL,
  video_url            VARCHAR(255) NULL,
  main_photo_id        INT UNSIGNED NULL,
  born_at_sk           TINYINT(1) NOT NULL DEFAULT 0,
  purchase_date        DATE NULL,
  purchase_price_qar   DECIMAL(14,2) NULL,              -- sensitive
  notes                TEXT NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL,
  archived_at          DATETIME NULL,
  deleted_at           DATETIME NULL,
  KEY idx_h_cat (category, status, deleted_at),
  KEY idx_h_web (show_on_website, deleted_at),
  KEY idx_h_sire (sire_id),
  KEY idx_h_dam (dam_id),
  KEY idx_h_name (name_en),
  CONSTRAINT fk_h_sire FOREIGN KEY (sire_id) REFERENCES horses(id),
  CONSTRAINT fk_h_dam FOREIGN KEY (dam_id) REFERENCES horses(id),
  CONSTRAINT fk_h_owner FOREIGN KEY (owner_party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horse_notes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id    INT UNSIGNED NOT NULL,
  note        TEXT NOT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_hn (horse_id, created_at),
  CONSTRAINT fk_hn_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_records (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id        INT UNSIGNED NOT NULL,
  type            ENUM('vaccination','vet_visit','deworming','dental','farrier','treatment','medicine','other') NOT NULL,
  record_date     DATE NOT NULL,
  title           VARCHAR(150) NOT NULL,
  details         TEXT NULL,
  vet_employee_id INT UNSIGNED NULL,
  vet_name        VARCHAR(120) NULL,         -- external vet / farrier
  item_id         INT UNSIGNED NULL,         -- medicine used from inventory
  quantity        DECIMAL(12,3) NULL,
  cost_qar        DECIMAL(14,2) NULL,
  next_due_date   DATE NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  deleted_at      DATETIME NULL,
  KEY idx_hr_h (horse_id, record_date),
  KEY idx_hr_due (next_due_date, deleted_at),
  CONSTRAINT fk_hr_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diet_plans (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id    INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  feeding     ENUM('early_morning','late_morning','afternoon','evening','late_evening') NOT NULL DEFAULT 'early_morning',
  quantity    DECIMAL(12,3) NOT NULL,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_dp (horse_id),
  CONSTRAINT fk_dp_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diet_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id    INT UNSIGNED NOT NULL,
  log_date    DATE NOT NULL,
  feeding     ENUM('early_morning','late_morning','afternoon','evening','late_evening') NOT NULL DEFAULT 'early_morning',
  item_id     INT UNSIGNED NULL,
  quantity    DECIMAL(12,3) NULL,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_dl (horse_id, log_date),
  CONSTRAINT fk_dl_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_logs (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id            INT UNSIGNED NOT NULL,
  log_date            DATE NOT NULL,
  trainer_employee_id INT UNSIGNED NULL,
  activity            VARCHAR(150) NOT NULL,
  duration_min        INT UNSIGNED NULL,
  notes               TEXT NULL,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL,
  deleted_at          DATETIME NULL,
  KEY idx_tl (horse_id, log_date),
  CONSTRAINT fk_tl_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shows (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en     VARCHAR(150) NOT NULL,      -- e.g. GCAT Muscat
  name_ar     VARCHAR(150) NULL,
  organizer   VARCHAR(80) NULL,           -- GCAT, ECAHO, ...
  city        VARCHAR(80) NULL,
  country     VARCHAR(80) NULL,
  start_date  DATE NOT NULL,
  end_date    DATE NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_shows_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE show_results (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  show_id     INT UNSIGNED NOT NULL,
  horse_id    INT UNSIGNED NOT NULL,
  class_name  VARCHAR(150) NULL,
  placing     INT UNSIGNED NULL,
  title_en    VARCHAR(150) NULL,          -- e.g. Gold Champion Junior Filly
  title_ar    VARCHAR(150) NULL,
  medal       ENUM('none','gold','silver','bronze') NOT NULL DEFAULT 'none',
  score       DECIMAL(6,2) NULL,
  prize_qar   DECIMAL(14,2) NULL,         -- sensitive; counted as horse income
  is_public   TINYINT(1) NOT NULL DEFAULT 1,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_sr_h (horse_id),
  KEY idx_sr_s (show_id),
  CONSTRAINT fk_sr_show FOREIGN KEY (show_id) REFERENCES shows(id),
  CONSTRAINT fk_sr_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE embryos (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                   VARCHAR(20) NOT NULL UNIQUE,         -- EMB-2026-001
  name                   VARCHAR(120) NULL,
  donor_mare_id          INT UNSIGNED NOT NULL,
  sire_id                INT UNSIGNED NOT NULL,
  owner_type             ENUM('sk','client') NOT NULL DEFAULT 'sk',
  owner_party_id         INT UNSIGNED NULL,
  flush_date             DATE NULL,
  grade                  VARCHAR(20) NULL,
  stage                  VARCHAR(40) NULL,
  status                 ENUM('fresh','frozen','transferred','pregnant','foaled','failed') NOT NULL DEFAULT 'fresh',
  location_id            INT UNSIGNED NULL,
  recipient_mare_id      INT UNSIGNED NULL,
  transfer_date          DATE NULL,
  expected_foaling_date  DATE NULL,
  foal_id                INT UNSIGNED NULL,
  notes                  TEXT NULL,
  created_by             INT UNSIGNED NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NULL,
  archived_at            DATETIME NULL,
  deleted_at             DATETIME NULL,
  KEY idx_e_status (status, deleted_at),
  KEY idx_e_donor (donor_mare_id),
  KEY idx_e_recip (recipient_mare_id),
  KEY idx_e_foal (expected_foaling_date),
  CONSTRAINT fk_e_donor FOREIGN KEY (donor_mare_id) REFERENCES horses(id),
  CONSTRAINT fk_e_sire FOREIGN KEY (sire_id) REFERENCES horses(id),
  CONSTRAINT fk_e_recip FOREIGN KEY (recipient_mare_id) REFERENCES horses(id),
  CONSTRAINT fk_e_foal FOREIGN KEY (foal_id) REFERENCES horses(id),
  CONSTRAINT fk_e_owner FOREIGN KEY (owner_party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pregnancies of mares at SK (natural cover, AI, or carrying a transferred embryo)
CREATE TABLE breeding_records (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mare_id                INT UNSIGNED NOT NULL,
  stallion_id            INT UNSIGNED NULL,
  embryo_id              INT UNSIGNED NULL,
  method                 ENUM('natural','ai_fresh','ai_chilled','ai_frozen','embryo_transfer','icsi') NOT NULL,
  start_date             DATE NOT NULL,              -- covering / insemination / transfer date
  expected_foaling_date  DATE NULL,                  -- start + 340 days, editable
  status                 ENUM('open','pregnant','not_pregnant','foaled','lost') NOT NULL DEFAULT 'open',
  foaling_date           DATE NULL,
  foal_id                INT UNSIGNED NULL,
  notes                  TEXT NULL,
  created_by             INT UNSIGNED NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NULL,
  deleted_at             DATETIME NULL,
  KEY idx_br_mare (mare_id, status),
  KEY idx_br_due (expected_foaling_date, status),
  CONSTRAINT fk_br_mare FOREIGN KEY (mare_id) REFERENCES horses(id),
  CONSTRAINT fk_br_st FOREIGN KEY (stallion_id) REFERENCES horses(id),
  CONSTRAINT fk_br_emb FOREIGN KEY (embryo_id) REFERENCES embryos(id),
  CONSTRAINT fk_br_foal FOREIGN KEY (foal_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pregnancy_checks (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  breeding_record_id INT UNSIGNED NOT NULL,
  check_date         DATE NOT NULL,
  result             ENUM('scheduled','positive','negative','inconclusive') NOT NULL DEFAULT 'scheduled',
  days_pregnant      INT UNSIGNED NULL,
  notes              VARCHAR(255) NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  KEY idx_pc (breeding_record_id),
  KEY idx_pc_date (check_date, result),
  CONSTRAINT fk_pc_br FOREIGN KEY (breeding_record_id) REFERENCES breeding_records(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ownership_history (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id        INT UNSIGNED NOT NULL,
  event_type      ENUM('purchase','birth','sale','transfer','import') NOT NULL,
  event_date      DATE NOT NULL,
  from_label      VARCHAR(150) NULL,
  from_party_id   INT UNSIGNED NULL,
  to_party_id     INT UNSIGNED NULL,           -- NULL = SK Arabians
  price_qar       DECIMAL(14,2) NULL,          -- sensitive
  currency        CHAR(3) NULL,
  exchange_rate   DECIMAL(18,6) NULL,
  price_original  DECIMAL(14,2) NULL,
  status          ENUM('pending','completed','rejected') NOT NULL DEFAULT 'completed',
  bill_id         INT UNSIGNED NULL,
  document_id     INT UNSIGNED NULL,
  notes           VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_oh (horse_id, event_date),
  CONSTRAINT fk_oh_h FOREIGN KEY (horse_id) REFERENCES horses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
