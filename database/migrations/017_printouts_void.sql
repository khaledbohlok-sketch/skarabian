-- 017 A printout can be voided like a Studio document: it stays in Studio's issued documents (Status → Voided) and
-- scanning its QR code then says it is no longer valid.
SET NAMES utf8mb4;

ALTER TABLE printouts
  ADD COLUMN is_void   TINYINT(1) NOT NULL DEFAULT 0 AFTER has_sensitive,
  ADD COLUMN voided_by INT UNSIGNED NULL AFTER is_void,
  ADD COLUMN voided_at DATETIME NULL AFTER voided_by;
