-- 001 Core: settings, users, roles, permissions, sessions, security, activity log, notifications, approvals, trash, files
SET NAMES utf8mb4;

CREATE TABLE settings (
  `key`       VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`     MEDIUMTEXT NULL,
  updated_at  DATETIME NULL,
  updated_by  INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sequences (
  name        VARCHAR(30) NOT NULL,
  period      VARCHAR(10) NOT NULL,
  last_value  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (name, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                VARCHAR(40) NOT NULL UNIQUE,
  name_en             VARCHAR(80) NOT NULL,
  name_ar             VARCHAR(80) NULL,
  is_system           TINYINT(1) NOT NULL DEFAULT 0,
  require_2fa         TINYINT(1) NOT NULL DEFAULT 0,
  horse_scope         ENUM('all','assigned') NOT NULL DEFAULT 'all',
  edit_window         ENUM('any','own_24h') NOT NULL DEFAULT 'any',
  restrict_country    VARCHAR(2) NULL,          -- e.g. 'QA' = only allow logins from Qatar
  business_hours_only TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id  INT UNSIGNED NOT NULL,
  module   VARCHAR(30) NOT NULL,
  action   VARCHAR(20) NOT NULL,
  PRIMARY KEY (role_id, module, action),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username             VARCHAR(60) NOT NULL UNIQUE,
  email                VARCHAR(150) NOT NULL UNIQUE,
  name                 VARCHAR(120) NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  role_id              INT UNSIGNED NOT NULL,
  employee_id          INT UNSIGNED NULL,
  status               ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  password_changed_at  DATETIME NULL,
  twofa_method         ENUM('none','totp','email') NOT NULL DEFAULT 'none',
  totp_secret          VARCHAR(255) NULL,        -- encrypted
  failed_attempts      INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until         DATETIME NULL,
  last_login_at        DATETIME NULL,
  last_login_ip        VARCHAR(45) NULL,
  lang                 CHAR(2) NOT NULL DEFAULT 'en',
  phone                VARCHAR(30) NULL,         -- WhatsApp alerts
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL,
  deleted_at           DATETIME NULL,
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temporary extra permissions (e.g. Technical Support granted Finance view until a date)
CREATE TABLE user_temp_grants (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  module      VARCHAR(30) NOT NULL,
  action      VARCHAR(20) NOT NULL,
  expires_at  DATETIME NOT NULL,
  granted_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_utg_user (user_id, expires_at),
  CONSTRAINT fk_utg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL UNIQUE,
  ip            VARCHAR(45) NULL,
  country       VARCHAR(2) NULL,
  user_agent    VARCHAR(255) NULL,
  device        VARCHAR(60) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at    DATETIME NULL,
  KEY idx_us_user (user_id, revoked_at),
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE known_devices (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  device_hash  CHAR(64) NOT NULL,
  country      VARCHAR(2) NULL,
  first_seen   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kd (user_id, device_hash),
  CONSTRAINT fk_kd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(150) NOT NULL,
  user_id     INT UNSIGNED NULL,
  ip          VARCHAR(45) NOT NULL,
  success     TINYINT(1) NOT NULL,
  reason      VARCHAR(40) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_ip (ip, created_at),
  KEY idx_la_user (username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_codes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  purpose     VARCHAR(20) NOT NULL,
  code_hash   CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  attempts    INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_ec_user (user_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tamper-evident activity log: each row stores an HMAC chained to the previous row.
-- The application never updates or deletes rows; migration 009 adds triggers that block it at database level.
CREATE TABLE activity_log (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NULL,
  user_name    VARCHAR(120) NULL,
  action       VARCHAR(40) NOT NULL,       -- login, logout, login_failed, view_sensitive, create, update, delete, restore, approve, reject, print, export, download, share, access_denied, ...
  module       VARCHAR(30) NULL,
  record_type  VARCHAR(40) NULL,
  record_id    BIGINT UNSIGNED NULL,
  summary      VARCHAR(255) NULL,
  old_values   JSON NULL,
  new_values   JSON NULL,
  ip           VARCHAR(45) NULL,
  device       VARCHAR(60) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prev_hash    CHAR(64) NULL,
  hash         CHAR(64) NOT NULL,
  KEY idx_al_record (record_type, record_id),
  KEY idx_al_user (user_id, created_at),
  KEY idx_al_action (action, created_at),
  KEY idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(40) NOT NULL,
  title       VARCHAR(200) NOT NULL,
  body        VARCHAR(500) NULL,
  url         VARCHAR(255) NULL,
  dedupe_key  VARCHAR(120) NULL,
  read_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_n_user (user_id, read_at),
  UNIQUE KEY uq_n_dedupe (user_id, dedupe_key),
  CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approvals (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type          VARCHAR(30) NOT NULL,   -- bill, payroll, horse_sale, delete_financial, new_user, purchase_order, foal_website, leave
  record_type   VARCHAR(40) NOT NULL,
  record_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  amount_qar    DECIMAL(14,2) NULL,
  payload       JSON NULL,
  status        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  requested_by  INT UNSIGNED NOT NULL,
  requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by    INT UNSIGNED NULL,
  decided_at    DATETIME NULL,
  decision_note VARCHAR(500) NULL,
  KEY idx_ap_status (status, type),
  KEY idx_ap_record (record_type, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trash (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_type  VARCHAR(40) NOT NULL,
  record_table VARCHAR(40) NOT NULL,
  record_id    INT UNSIGNED NOT NULL,
  label        VARCHAR(255) NOT NULL,
  module       VARCHAR(30) NOT NULL,
  deleted_by   INT UNSIGNED NOT NULL,
  deleted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trash_rec (record_table, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All uploaded files live outside the public folder and are streamed by FilesController after a permission check.
CREATE TABLE files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type    VARCHAR(40) NOT NULL,     -- horse, employee, bill, embryo, party, news, cms, document
  owner_id      INT UNSIGNED NOT NULL,
  category      VARCHAR(30) NOT NULL,     -- photo, video, document, receipt, contract, id_copy, certificate
  title         VARCHAR(200) NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(100) NOT NULL,
  thumb_name    VARCHAR(100) NULL,
  mime          VARCHAR(80) NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL,
  width         INT UNSIGNED NULL,
  height        INT UNSIGNED NULL,
  is_sensitive  TINYINT(1) NOT NULL DEFAULT 0,
  is_public     TINYINT(1) NOT NULL DEFAULT 0,  -- may appear on the public website (horse photos, gallery)
  sort_order    INT NOT NULL DEFAULT 0,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  KEY idx_files_owner (owner_type, owner_id, category),
  KEY idx_files_public (is_public, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable dropdown lists: breeds, colors, positions, departments, stables/locations, nationalities, payment methods, ...
CREATE TABLE lookups (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type      VARCHAR(30) NOT NULL,
  value_en  VARCHAR(120) NOT NULL,
  value_ar  VARCHAR(120) NULL,
  code      VARCHAR(30) NULL,
  sort      INT NOT NULL DEFAULT 0,
  active    TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_lookup (type, value_en),
  KEY idx_lookup_type (type, active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE currencies (
  code             CHAR(3) NOT NULL PRIMARY KEY,
  name_en          VARCHAR(60) NOT NULL,
  name_ar          VARCHAR(60) NULL,
  rate_to_qar      DECIMAL(18,6) NOT NULL,      -- 1 unit = X QAR
  auto_update      TINYINT(1) NOT NULL DEFAULT 1,
  manual_override  TINYINT(1) NOT NULL DEFAULT 0,
  active           TINYINT(1) NOT NULL DEFAULT 1,
  updated_at       DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE currency_rate_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        CHAR(3) NOT NULL,
  rate_to_qar DECIMAL(18,6) NOT NULL,
  source      VARCHAR(20) NOT NULL,   -- auto, manual, migration
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_crh (code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
