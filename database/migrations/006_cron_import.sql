-- 006 Scheduled tasks log and old-system import map
SET NAMES utf8mb4;

-- One row per scheduled task run (cron/run.php); the scheduler uses the last successful run to decide what is due
CREATE TABLE cron_runs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task         VARCHAR(30) NOT NULL,
  started_at   DATETIME NOT NULL,
  finished_at  DATETIME NULL,
  ok           TINYINT(1) NULL,
  message      VARCHAR(500) NULL,
  KEY idx_cron (task, ok, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Old system record -> new record, so the import can be re-run without creating duplicates
CREATE TABLE import_map (
  entity      VARCHAR(40) NOT NULL,
  old_id      VARCHAR(60) NOT NULL,
  new_id      INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (entity, old_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
