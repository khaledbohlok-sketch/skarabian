-- 022 Horse ownership history and sales: who owned each horse and when (purchase, sale, gift, lease, breeding
-- share), the horse statuses for horses that left the stud (sold, exported, leased out), an owner typed by name
-- (not in Clients), the "Horse Sales" income category, and translatable notifications (a language key with
-- its values, translated when shown in the reader's language; older notifications keep their stored text).
SET NAMES utf8mb4;

ALTER TABLE horses MODIFY status ENUM('active','in_shelter','deceased','sold','exported','leased_out') NOT NULL DEFAULT 'active';

ALTER TABLE horses MODIFY owner_type ENUM('sk','client','other') NOT NULL DEFAULT 'sk';

ALTER TABLE horses ADD COLUMN owner_name VARCHAR(150) NULL AFTER owner_party_id;

CREATE TABLE horse_ownership (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horse_id        INT UNSIGNED NOT NULL,
  owner_type      ENUM('sk','client','other') NOT NULL,
  owner_party_id  INT UNSIGNED NULL,                 -- a client (parties) when owner_type = client
  owner_name      VARCHAR(150) NULL,                 -- the owner's name as it was at the time (or typed, when not a client)
  from_date       DATE NOT NULL,
  `to_date`       DATE NULL,                         -- NULL = current owner (quoted: TO_DATE is reserved in MariaDB 12)
  transfer_type   ENUM('purchase','sale','gift','lease','breeding_share','birth','opening','other') NOT NULL,
  price           DECIMAL(14,2) NULL,                -- sensitive
  currency        CHAR(3) NOT NULL DEFAULT 'QAR',
  exchange_rate   DECIMAL(18,6) NOT NULL DEFAULT 1,  -- 1 unit = X QAR, saved per transfer (as on bills)
  price_qar       DECIMAL(14,2) NULL,
  bill_id         INT UNSIGNED NULL,                 -- income (SK sells) or expense (SK buys) bill created with the transfer
  notes           TEXT NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  deleted_at      DATETIME NULL,
  KEY idx_ho_horse (horse_id, deleted_at, from_date),
  KEY idx_ho_party (owner_party_id),
  CONSTRAINT fk_ho_horse FOREIGN KEY (horse_id) REFERENCES horses(id),
  CONSTRAINT fk_ho_party FOREIGN KEY (owner_party_id) REFERENCES parties(id),
  CONSTRAINT fk_ho_bill FOREIGN KEY (bill_id) REFERENCES bills(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opening history: one current-owner row for every horse, from the owner fields on file
INSERT INTO horse_ownership (horse_id, owner_type, owner_party_id, owner_name, from_date, transfer_type, price, currency, exchange_rate, price_qar, created_at)
SELECT h.id, h.owner_type, h.owner_party_id,
       CASE WHEN h.owner_type = 'client' THEN p.name_en WHEN h.owner_type = 'other' THEN h.owner_name ELSE NULL END,
       CASE WHEN h.born_at_sk = 1 AND h.dob IS NOT NULL THEN h.dob ELSE COALESCE(h.purchase_date, h.dob, DATE(h.created_at)) END,
       CASE WHEN h.born_at_sk = 1 AND h.dob IS NOT NULL THEN 'birth' WHEN h.purchase_date IS NOT NULL AND h.owner_type = 'sk' THEN 'purchase' ELSE 'opening' END,
       CASE WHEN h.born_at_sk = 0 AND h.purchase_date IS NOT NULL AND h.owner_type = 'sk' THEN h.purchase_price_qar ELSE NULL END,
       'QAR', 1,
       CASE WHEN h.born_at_sk = 0 AND h.purchase_date IS NOT NULL AND h.owner_type = 'sk' THEN h.purchase_price_qar ELSE NULL END,
       NOW()
FROM horses h LEFT JOIN parties p ON p.id = h.owner_party_id
WHERE h.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM horse_ownership o WHERE o.horse_id = h.id);

INSERT INTO finance_categories (type, name_en, name_ar, sort)
SELECT 'income', 'Horse Sales', 'بيع الخيول', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM finance_categories WHERE name_en = 'Horse Sales' AND deleted_at IS NULL);

ALTER TABLE notifications ADD COLUMN msg_key VARCHAR(100) NULL AFTER body, ADD COLUMN msg_params TEXT NULL AFTER msg_key, ADD COLUMN body_key VARCHAR(100) NULL AFTER msg_params, ADD COLUMN body_params TEXT NULL AFTER body_key;
