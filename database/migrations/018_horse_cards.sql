-- 018 Horse information cards: the UELN on a horse, shows known only by their year, the judges' card of a show
-- result (individual class marks), and "not recorded" as a breeding method (a pregnancy whose method was not supplied)
SET NAMES utf8mb4;

ALTER TABLE horses ADD COLUMN ueln VARCHAR(40) NULL AFTER microchip;

ALTER TABLE shows ADD COLUMN year_only TINYINT(1) NOT NULL DEFAULT 0 AFTER end_date;

ALTER TABLE show_results ADD COLUMN judge_card TEXT NULL AFTER notes;

ALTER TABLE breeding_records MODIFY method ENUM('natural','ai_fresh','ai_chilled','ai_frozen','embryo_transfer','icsi','unknown') NOT NULL;
