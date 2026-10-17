-- 013 Tracks every row inserted by tools/seed_test_data.php so tools/cleanup_test_data.php can remove
-- exactly those rows (and nothing the user created manually) before the real/old-data import.
SET NAMES utf8mb4;

CREATE TABLE test_seed_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch       VARCHAR(40) NOT NULL,
  table_name  VARCHAR(64) NOT NULL,
  record_id   BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tsl_table (table_name, record_id),
  KEY idx_tsl_batch (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
