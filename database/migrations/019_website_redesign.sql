-- 019 Website redesign: the order of horses on the website, what visitors see on a horse's profile and in the menu
-- (switches in Website → Homepage & texts), and no breeding content on the public site: the default texts that talked
-- about breeding are replaced — only where they are still the installed default (texts changed by the Owner stay).
SET NAMES utf8mb4;

ALTER TABLE horses ADD COLUMN website_sort SMALLINT UNSIGNED NULL AFTER is_favorite;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('site.page_champions', '1'), ('site.page_news', '1'), ('site.page_about', '1'), ('site.page_contact', '1'),
  ('site.profile_age', '1'), ('site.profile_colour', '1'), ('site.profile_breeder', '1'), ('site.profile_country', '1'),
  ('site.profile_story', '1'), ('site.profile_video', '1'), ('site.profile_pedigree', '1'), ('site.profile_pedigree_gens', '5'),
  ('site.profile_results', '1'), ('site.profile_inquiry', '1'), ('site.card_titles', '1'), ('site.card_parents', '1');

UPDATE settings SET `value` = 'horses,featured,achievements,story,gallery,news,experts,contact'
  WHERE `key` = 'site.sections' AND `value` = 'featured,horses,achievements,foals,bloodlines,breeding,gallery,story,experts,contact';

UPDATE settings SET `value` = 'Purebred Arabian Horses of Distinction'
  WHERE `key` = 'site.hero_title_en' AND `value` = 'Breeding Excellence in Arabian Horses';
UPDATE settings SET `value` = 'نخبة من الخيل العربية الأصيلة'
  WHERE `key` = 'site.hero_title_ar' AND `value` = 'التميّز في إكثار الخيل العربية';

UPDATE settings SET `value` = 'SK Arabians was founded in Qatar in 2025 with one purpose: to care for and present purebred Arabian horses of exceptional type, movement and character, with pride in the show ring.'
  WHERE `key` = 'site.story_en' AND `value` = 'SK Arabians was founded in Qatar in 2025 with one purpose: to breed purebred Arabian horses of exceptional type, movement and character, and to present them with pride in the show ring.';
UPDATE settings SET `value` = 'تأسس مربط اس كي ارابيان في قطر عام 2025 بهدف واحد: رعاية خيل عربية أصيلة بجمال وحركة وشخصية استثنائية، وتقديمها بفخر في حلبات البطولات.'
  WHERE `key` = 'site.story_ar' AND `value` = 'تأسس مربط اس كي ارابيان في قطر عام 2025 بهدف واحد: إكثار خيل عربية أصيلة بجمال وحركة وشخصية استثنائية، وتقديمها بفخر في حلبات البطولات.';

UPDATE settings SET `value` = 'Documented pedigrees of proven, classic Arabian lines.'
  WHERE `key` = 'site.pillar1_text_en' AND `value` = 'Every pairing is planned around proven, documented bloodlines.';
UPDATE settings SET `value` = 'أنساب موثقة لسلالات عربية أصيلة ومجربة.'
  WHERE `key` = 'site.pillar1_text_ar' AND `value` = 'كل تزاوج مدروس على أساس سلالات موثقة ومجربة.';
