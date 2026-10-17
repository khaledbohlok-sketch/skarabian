-- 016 Printouts: every printed list or report (the "PDF" buttons) gets a reference number (SKA-PRT-2026-0001) and a
-- QR code on each page. Scanning it opens /verify/{token}, which confirms the printout is genuine; staff allowed to
-- see the module can open the original copy in the portal. What was printed is kept compressed, with an HMAC
-- fingerprint that shows the kept copy is unchanged.
SET NAMES utf8mb4;

CREATE TABLE printouts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_no        VARCHAR(30) NOT NULL,
  verify_token  CHAR(32) NOT NULL,
  kind          VARCHAR(20) NOT NULL DEFAULT 'list',     -- list, report, semen_report
  module        VARCHAR(30) NOT NULL,                    -- permission module of what was printed
  title         VARCHAR(200) NOT NULL,
  source_url    VARCHAR(1000) NULL,                      -- the portal page it was printed from, to open it again
  row_count     INT UNSIGNED NOT NULL DEFAULT 0,
  fingerprint   CHAR(64) NOT NULL,                       -- HMAC-SHA256 (app key) of the printed content
  snapshot      MEDIUMBLOB NULL,                         -- the printed content, gzip JSON; NULL when too large to keep
  has_sensitive TINYINT(1) NOT NULL DEFAULT 0,           -- had columns only "view sensitive" may see
  lang          CHAR(2) NOT NULL DEFAULT 'en',
  printed_by    INT UNSIGNED NULL,
  printed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_ref (ref_no),
  UNIQUE KEY uq_pr_token (verify_token),
  KEY idx_pr_when (printed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
