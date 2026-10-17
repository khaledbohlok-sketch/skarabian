-- 007 Documents and Photos: a standalone library of uploaded PDFs and photos,
-- separate from the "documents" table used by SK Arabian Studio's printed letters/certificates.
SET NAMES utf8mb4;

CREATE TABLE document_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en     VARCHAR(100) NOT NULL,
  name_ar     VARCHAR(100) NULL,
  sort        INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_dc_active (active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_documents (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  category_id INT UNSIGNED NULL,
  notes       TEXT NULL,
  doc_date    DATE NULL,
  -- The uploaded file itself is stored and streamed through the existing, already-secured
  -- `files` table / FileStore / FilesController (random names, MIME-sniffed, outside public_html,
  -- images re-encoded to WebP with an auto thumbnail) rather than duplicating that here.
  file_id     INT UNSIGNED NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  archived_at DATETIME NULL,
  deleted_at  DATETIME NULL,
  KEY idx_md_category (category_id),
  KEY idx_md_file (file_id),
  KEY idx_md_date (doc_date),
  CONSTRAINT fk_md_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_md_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL,
  CONSTRAINT fk_md_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_categories (name_en, name_ar, sort) VALUES
  ('Certificates', 'شهادات', 10),
  ('Veterinary reports', 'تقارير بيطرية', 20),
  ('Contracts', 'عقود', 30),
  ('Invoices', 'فواتير', 40),
  ('Photos', 'صور', 50),
  ('Other', 'أخرى', 60);

-- Grant the new "documents" permission module to Owner and General Manager (the only default
-- roles with full module access); the Owner can grant it to other roles from Roles & Permissions.
INSERT IGNORE INTO role_permissions (role_id, module, action)
SELECT r.id, 'documents', a.action
FROM roles r
CROSS JOIN (
  SELECT 'view' AS action UNION ALL SELECT 'create' UNION ALL SELECT 'edit' UNION ALL
  SELECT 'delete' UNION ALL SELECT 'export' UNION ALL SELECT 'print'
) a
WHERE r.slug IN ('owner', 'general_manager');
