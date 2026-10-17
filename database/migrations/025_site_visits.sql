-- 025 Website visitor numbers (Website → Visitors and the dashboard): one row for each page a visitor opens on the
-- public website. No cookies are set and no addresses are kept: a visitor is a short code made from the address and the
-- browser with a key that is replaced every day (the old key is thrown away), so visits can be counted once a day but
-- nobody can be followed from day to day or traced back. Staff who are logged in, search engines and link previews are
-- not counted. Rows older than 400 days are removed by the nightly clean-up.
-- Additions only: no existing value is changed. To undo: database/rollback/025_site_visits_down.sql
SET NAMES utf8mb4;

CREATE TABLE site_visits (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visited_at  DATETIME NOT NULL,
  visit_day   DATE NOT NULL,
  page        VARCHAR(16) NOT NULL,          -- home, horses, horse, champions, news, news_item, about, contact
  horse_id    INT UNSIGNED NULL,             -- the horse of a horse page
  lang        CHAR(2) NOT NULL,
  device      VARCHAR(8) NOT NULL,           -- phone, tablet, computer
  source      VARCHAR(16) NOT NULL,          -- where the visit came from: google, instagram, whatsapp, direct … ("site" = a page after the first)
  country     CHAR(2) NULL,
  visitor     CHAR(16) NOT NULL,             -- the day's visitor code (see above)
  KEY idx_visits_day (visit_day, visitor),
  KEY idx_visits_horse (horse_id, visit_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
