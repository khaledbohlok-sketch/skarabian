-- 005 SK Arabian Studio (documents), website CMS, news, gallery, inquiries
SET NAMES utf8mb4;

CREATE TABLE studio_templates (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type            VARCHAR(40) NOT NULL,
  lang                CHAR(2) NOT NULL,
  title               VARCHAR(150) NOT NULL,
  body                MEDIUMTEXT NOT NULL,       -- wording with {{placeholders}}
  letterhead_version  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  updated_by          INT UNSIGNED NULL,
  updated_at          DATETIME NULL,
  UNIQUE KEY uq_tpl (doc_type, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which roles may create each document type (editable by the Owner)
CREATE TABLE studio_doc_permissions (
  role_id   INT UNSIGNED NOT NULL,
  doc_type  VARCHAR(40) NOT NULL,
  PRIMARY KEY (role_id, doc_type),
  CONSTRAINT fk_sdp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_no        VARCHAR(30) NOT NULL UNIQUE,      -- SKA-SAL-2026-0001
  doc_type      VARCHAR(40) NOT NULL,
  lang          CHAR(2) NOT NULL,
  title         VARCHAR(200) NOT NULL,
  record_type   VARCHAR(40) NULL,                 -- horse, employee, bill, party, embryo, invoice, payroll_line, ...
  record_id     INT UNSIGNED NULL,
  doc_date      DATE NOT NULL,
  letterhead_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
  data          JSON NULL,                        -- field values used
  body_html     MEDIUMTEXT NOT NULL,              -- rendered snapshot (re-printable exactly as issued)
  verify_token  CHAR(32) NOT NULL UNIQUE,
  is_void       TINYINT(1) NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  KEY idx_doc_rec (record_type, record_id),
  KEY idx_doc_type (doc_type, doc_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(160) NOT NULL UNIQUE,
  title_en      VARCHAR(200) NOT NULL,
  title_ar      VARCHAR(200) NULL,
  summary_en    VARCHAR(400) NULL,
  summary_ar    VARCHAR(400) NULL,
  body_en       MEDIUMTEXT NULL,
  body_ar       MEDIUMTEXT NULL,
  image_id      INT UNSIGNED NULL,
  show_id       INT UNSIGNED NULL,
  published     TINYINT(1) NOT NULL DEFAULT 0,
  published_at  DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  deleted_at    DATETIME NULL,
  KEY idx_news_pub (published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id     INT UNSIGNED NULL,
  video_url   VARCHAR(255) NULL,
  horse_id    INT UNSIGNED NULL,
  caption_en  VARCHAR(200) NULL,
  caption_ar  VARCHAR(200) NULL,
  sort        INT NOT NULL DEFAULT 0,
  published   TINYINT(1) NOT NULL DEFAULT 1,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  KEY idx_gal (published, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inquiries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         ENUM('general','horse','breeding','embryo','sale') NOT NULL DEFAULT 'general',
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(150) NULL,
  phone        VARCHAR(40) NULL,
  country      VARCHAR(80) NULL,
  message      TEXT NOT NULL,
  horse_id     INT UNSIGNED NULL,
  embryo_id    INT UNSIGNED NULL,
  party_id     INT UNSIGNED NULL,
  lang         CHAR(2) NOT NULL DEFAULT 'en',
  status       ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
  assigned_to  INT UNSIGNED NULL,
  replied_at   DATETIME NULL,
  internal_note TEXT NULL,
  ip           VARCHAR(45) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL,
  deleted_at   DATETIME NULL,
  KEY idx_inq_status (status, created_at),
  KEY idx_inq_horse (horse_id),
  KEY idx_inq_embryo (embryo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE migration_report (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id       VARCHAR(20) NOT NULL,
  entity       VARCHAR(40) NOT NULL,
  old_id       VARCHAR(40) NULL,
  new_id       INT UNSIGNED NULL,
  action       VARCHAR(30) NOT NULL,        -- imported, merged, converted, fixed, linked, skipped, warning
  details      TEXT NULL,
  reviewed     TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mr (run_id, entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
