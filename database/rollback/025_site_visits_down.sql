-- Undoes database update 025 (website visitor numbers). Run in phpMyAdmin only after restoring the previous program files.
-- Removes only what 025 added: the visits table (the counted visits are lost), the day's visitor key in the settings
-- and its line in schema_migrations, so it can be applied again later.
SET NAMES utf8mb4;

DROP TABLE IF EXISTS site_visits;
DELETE FROM settings WHERE `key` IN ('stats.salt', 'stats.salt_day');
DELETE FROM schema_migrations WHERE version = '025_site_visits.sql';
