-- 014 Financial Report drafts + bill linkage: Studio's "fin" document type can now be built by picking
-- specific bills instead of typing lines by hand. A report becomes a real (draft) documents row as soon
-- as it's started, and document_bills links it to the bills it contains; the link row also holds a
-- snapshot of each bill's display fields, refreshed on demand while the report is a draft and left alone
-- once it's issued (so an issued PDF never changes later even if the underlying bill is edited).
SET NAMES utf8mb4;

ALTER TABLE documents
  MODIFY ref_no VARCHAR(30) NULL,
  MODIFY verify_token CHAR(32) NULL,
  ADD COLUMN status ENUM('draft','issued','void') NOT NULL DEFAULT 'issued' AFTER is_void,
  ADD COLUMN period_from DATE NULL AFTER status,
  ADD COLUMN period_to DATE NULL AFTER period_from,
  ADD COLUMN report_kind VARCHAR(30) NULL AFTER period_to;

CREATE TABLE document_bills (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id      INT UNSIGNED NOT NULL,
  bill_id          INT UNSIGNED NULL,              -- NULL for a manual (non-bill) line
  sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_manual        TINYINT(1) NOT NULL DEFAULT 0,
  number           VARCHAR(20) NULL,
  bill_date        DATE NULL,
  due_date         DATE NULL,
  party_name_en    VARCHAR(160) NULL,
  party_name_ar    VARCHAR(160) NULL,
  category_name_en VARCHAR(120) NULL,
  category_name_ar VARCHAR(120) NULL,
  description      VARCHAR(255) NULL,
  manual_label_en  VARCHAR(255) NULL,
  manual_label_ar  VARCHAR(255) NULL,
  bill_type        ENUM('income','expense','liability') NULL,
  bill_status      VARCHAR(20) NULL,
  currency         VARCHAR(3) NOT NULL DEFAULT 'QAR', -- the bill's OWN currency; amount_qar/paid_qar below are always the converted QAR value
  amount_original  DECIMAL(14,2) NULL,                -- NULL when currency = QAR (nothing to show small next to the QAR figure)
  amount_qar       DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_qar         DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL,
  UNIQUE KEY uniq_doc_bill (document_id, bill_id),
  KEY idx_db_bill (bill_id),
  CONSTRAINT fk_db_doc  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_db_bill FOREIGN KEY (bill_id) REFERENCES bills(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
