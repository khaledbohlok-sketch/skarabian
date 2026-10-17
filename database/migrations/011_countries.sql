-- 011 Countries: one shared reference table for every nationality/country field in the system
-- (employees, parties/clients/suppliers, horse "country of birth", show country).
-- Primary key is the ISO 3166-1 alpha-2 code itself (e.g. 'QA') so it can be stored directly as the
-- foreign-key value everywhere, exactly like every other picker source in this app keys off `t.id`.
SET NAMES utf8mb4;

CREATE TABLE countries (
  id               CHAR(2)      NOT NULL PRIMARY KEY,   -- ISO 3166-1 alpha-2
  name_en          VARCHAR(80)  NOT NULL,                -- country noun, e.g. "Qatar"
  name_ar          VARCHAR(80)  NOT NULL,                -- قطر
  nationality_en   VARCHAR(40)  NOT NULL,                -- adjective, e.g. "Qatari"
  nationality_ar   VARCHAR(40)  NOT NULL,                -- قطري
  flag             VARCHAR(8)   NOT NULL,                -- 🇶🇦
  common           TINYINT(1)   NOT NULL DEFAULT 0,       -- pinned to the top of the dropdown
  sort             INT          NOT NULL DEFAULT 0,       -- order within the "common" group
  KEY idx_common (common, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ the 18 most-used (GCC + Levant + South Asia + Philippines)
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag, common, sort) VALUES
('QA','Qatar','قطر','Qatari','قطري','🇶🇦',1,0),
('SA','Saudi Arabia','السعودية','Saudi','سعودي','🇸🇦',1,1),
('AE','United Arab Emirates','الإمارات العربية المتحدة','Emirati','إماراتي','🇦🇪',1,2),
('KW','Kuwait','الكويت','Kuwaiti','كويتي','🇰🇼',1,3),
('BH','Bahrain','البحرين','Bahraini','بحريني','🇧🇭',1,4),
('OM','Oman','عُمان','Omani','عُماني','🇴🇲',1,5),
('EG','Egypt','مصر','Egyptian','مصري','🇪🇬',1,6),
('SY','Syria','سوريا','Syrian','سوري','🇸🇾',1,7),
('LB','Lebanon','لبنان','Lebanese','لبناني','🇱🇧',1,8),
('JO','Jordan','الأردن','Jordanian','أردني','🇯🇴',1,9),
('PS','Palestine','فلسطين','Palestinian','فلسطيني','🇵🇸',1,10),
('SD','Sudan','السودان','Sudanese','سوداني','🇸🇩',1,11),
('IN','India','الهند','Indian','هندي','🇮🇳',1,12),
('PK','Pakistan','باكستان','Pakistani','باكستاني','🇵🇰',1,13),
('BD','Bangladesh','بنغلاديش','Bangladeshi','بنغلاديشي','🇧🇩',1,14),
('NP','Nepal','نيبال','Nepali','نيبالي','🇳🇵',1,15),
('LK','Sri Lanka','سريلانكا','Sri Lankan','سريلانكي','🇱🇰',1,16),
('PH','Philippines','الفلبين','Filipino','فلبيني','🇵🇭',1,17);

-- ------------------------------------------------------------------ Africa (54 UN members, minus Egypt & Sudan above)
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('DZ','Algeria','الجزائر','Algerian','جزائري','🇩🇿'),
('AO','Angola','أنغولا','Angolan','أنغولي','🇦🇴'),
('BJ','Benin','بنين','Beninese','بنيني','🇧🇯'),
('BW','Botswana','بوتسوانا','Motswana','بوتسواني','🇧🇼'),
('BF','Burkina Faso','بوركينا فاسو','Burkinabe','بوركينابي','🇧🇫'),
('BI','Burundi','بوروندي','Burundian','بوروندي','🇧🇮'),
('CV','Cabo Verde','الرأس الأخضر','Cabo Verdean','رأس أخضري','🇨🇻'),
('CM','Cameroon','الكاميرون','Cameroonian','كاميروني','🇨🇲'),
('CF','Central African Republic','جمهورية أفريقيا الوسطى','Central African','أفريقي أوسطي','🇨🇫'),
('TD','Chad','تشاد','Chadian','تشادي','🇹🇩'),
('KM','Comoros','جزر القمر','Comoran','قمري','🇰🇲'),
('CG','Congo (Republic of the)','جمهورية الكونغو','Congolese','كونغولي','🇨🇬'),
('CD','Congo (DRC)','جمهورية الكونغو الديمقراطية','Congolese','كونغولي','🇨🇩'),
('DJ','Djibouti','جيبوتي','Djiboutian','جيبوتي','🇩🇯'),
('GQ','Equatorial Guinea','غينيا الاستوائية','Equatorial Guinean','غيني استوائي','🇬🇶'),
('ER','Eritrea','إريتريا','Eritrean','إريتري','🇪🇷'),
('SZ','Eswatini','إسواتيني','Swazi','إسواتيني','🇸🇿'),
('ET','Ethiopia','إثيوبيا','Ethiopian','إثيوبي','🇪🇹'),
('GA','Gabon','الغابون','Gabonese','غابوني','🇬🇦'),
('GM','Gambia','غامبيا','Gambian','غامبي','🇬🇲'),
('GH','Ghana','غانا','Ghanaian','غاني','🇬🇭'),
('GN','Guinea','غينيا','Guinean','غيني','🇬🇳'),
('GW','Guinea-Bissau','غينيا بيساو','Bissau-Guinean','غيني بيساوي','🇬🇼'),
('CI','Ivory Coast','ساحل العاج','Ivorian','إيفواري','🇨🇮'),
('KE','Kenya','كينيا','Kenyan','كيني','🇰🇪'),
('LS','Lesotho','ليسوتو','Mosotho','ليسوتوي','🇱🇸'),
('LR','Liberia','ليبيريا','Liberian','ليبيري','🇱🇷'),
('LY','Libya','ليبيا','Libyan','ليبي','🇱🇾'),
('MG','Madagascar','مدغشقر','Malagasy','مدغشقري','🇲🇬'),
('MW','Malawi','مالاوي','Malawian','مالاوي','🇲🇼'),
('ML','Mali','مالي','Malian','مالي','🇲🇱'),
('MR','Mauritania','موريتانيا','Mauritanian','موريتاني','🇲🇷'),
('MU','Mauritius','موريشيوس','Mauritian','موريشيوسي','🇲🇺'),
('MA','Morocco','المغرب','Moroccan','مغربي','🇲🇦'),
('MZ','Mozambique','موزمبيق','Mozambican','موزمبيقي','🇲🇿'),
('NA','Namibia','ناميبيا','Namibian','ناميبي','🇳🇦'),
('NE','Niger','النيجر','Nigerien','نيجري','🇳🇪'),
('NG','Nigeria','نيجيريا','Nigerian','نيجيري','🇳🇬'),
('RW','Rwanda','رواندا','Rwandan','رواندي','🇷🇼'),
('ST','Sao Tome and Principe','ساو تومي وبرينسيبي','Sao Tomean','ساو تومي','🇸🇹'),
('SN','Senegal','السنغال','Senegalese','سنغالي','🇸🇳'),
('SC','Seychelles','سيشل','Seychellois','سيشلي','🇸🇨'),
('SL','Sierra Leone','سيراليون','Sierra Leonean','سيراليوني','🇸🇱'),
('SO','Somalia','الصومال','Somali','صومالي','🇸🇴'),
('ZA','South Africa','جنوب أفريقيا','South African','جنوب أفريقي','🇿🇦'),
('SS','South Sudan','جنوب السودان','South Sudanese','جنوب سوداني','🇸🇸'),
('TZ','Tanzania','تنزانيا','Tanzanian','تنزاني','🇹🇿'),
('TG','Togo','توغو','Togolese','توغولي','🇹🇬'),
('TN','Tunisia','تونس','Tunisian','تونسي','🇹🇳'),
('UG','Uganda','أوغندا','Ugandan','أوغندي','🇺🇬'),
('ZM','Zambia','زامبيا','Zambian','زامبي','🇿🇲'),
('ZW','Zimbabwe','زيمبابوي','Zimbabwean','زيمبابوي','🇿🇼');

-- ------------------------------------------------------------------ Asia (46 UN members, minus the 15 above) + Taiwan/Hong Kong/Macau
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('AF','Afghanistan','أفغانستان','Afghan','أفغاني','🇦🇫'),
('AM','Armenia','أرمينيا','Armenian','أرميني','🇦🇲'),
('AZ','Azerbaijan','أذربيجان','Azerbaijani','أذربيجاني','🇦🇿'),
('BT','Bhutan','بوتان','Bhutanese','بوتاني','🇧🇹'),
('BN','Brunei','بروناي','Bruneian','برونايي','🇧🇳'),
('KH','Cambodia','كمبوديا','Cambodian','كمبودي','🇰🇭'),
('CN','China','الصين','Chinese','صيني','🇨🇳'),
('GE','Georgia','جورجيا','Georgian','جورجي','🇬🇪'),
('ID','Indonesia','إندونيسيا','Indonesian','إندونيسي','🇮🇩'),
('IR','Iran','إيران','Iranian','إيراني','🇮🇷'),
('IQ','Iraq','العراق','Iraqi','عراقي','🇮🇶'),
('IL','Israel','إسرائيل','Israeli','إسرائيلي','🇮🇱'),
('JP','Japan','اليابان','Japanese','ياباني','🇯🇵'),
('KZ','Kazakhstan','كازاخستان','Kazakhstani','كازاخستاني','🇰🇿'),
('KG','Kyrgyzstan','قيرغيزستان','Kyrgyzstani','قيرغيزستاني','🇰🇬'),
('LA','Laos','لاوس','Laotian','لاوسي','🇱🇦'),
('MY','Malaysia','ماليزيا','Malaysian','ماليزي','🇲🇾'),
('MV','Maldives','جزر المالديف','Maldivian','مالديفي','🇲🇻'),
('MN','Mongolia','منغوليا','Mongolian','منغولي','🇲🇳'),
('MM','Myanmar','ميانمار','Burmese','ميانماري','🇲🇲'),
('KP','North Korea','كوريا الشمالية','North Korean','كوري شمالي','🇰🇵'),
('SG','Singapore','سنغافورة','Singaporean','سنغافوري','🇸🇬'),
('KR','South Korea','كوريا الجنوبية','South Korean','كوري جنوبي','🇰🇷'),
('TJ','Tajikistan','طاجيكستان','Tajikistani','طاجيكستاني','🇹🇯'),
('TH','Thailand','تايلاند','Thai','تايلاندي','🇹🇭'),
('TL','Timor-Leste','تيمور الشرقية','Timorese','تيموري','🇹🇱'),
('TR','Turkey','تركيا','Turkish','تركي','🇹🇷'),
('TM','Turkmenistan','تركمانستان','Turkmen','تركماني','🇹🇲'),
('UZ','Uzbekistan','أوزبكستان','Uzbekistani','أوزبكستاني','🇺🇿'),
('VN','Vietnam','فيتنام','Vietnamese','فيتنامي','🇻🇳'),
('YE','Yemen','اليمن','Yemeni','يمني','🇾🇪'),
('TW','Taiwan','تايوان','Taiwanese','تايواني','🇹🇼'),
('HK','Hong Kong','هونغ كونغ','Hong Konger','هونغ كونغي','🇭🇰'),
('MO','Macau','ماكاو','Macanese','ماكاوي','🇲🇴');

-- ------------------------------------------------------------------ Europe (44 UN members) + Vatican City/Kosovo
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('AL','Albania','ألبانيا','Albanian','ألباني','🇦🇱'),
('AD','Andorra','أندورا','Andorran','أندوري','🇦🇩'),
('AT','Austria','النمسا','Austrian','نمساوي','🇦🇹'),
('BY','Belarus','بيلاروسيا','Belarusian','بيلاروسي','🇧🇾'),
('BE','Belgium','بلجيكا','Belgian','بلجيكي','🇧🇪'),
('BA','Bosnia and Herzegovina','البوسنة والهرسك','Bosnian','بوسني','🇧🇦'),
('BG','Bulgaria','بلغاريا','Bulgarian','بلغاري','🇧🇬'),
('HR','Croatia','كرواتيا','Croatian','كرواتي','🇭🇷'),
('CY','Cyprus','قبرص','Cypriot','قبرصي','🇨🇾'),
('CZ','Czechia','التشيك','Czech','تشيكي','🇨🇿'),
('DK','Denmark','الدنمارك','Danish','دنماركي','🇩🇰'),
('EE','Estonia','إستونيا','Estonian','إستوني','🇪🇪'),
('FI','Finland','فنلندا','Finnish','فنلندي','🇫🇮'),
('FR','France','فرنسا','French','فرنسي','🇫🇷'),
('DE','Germany','ألمانيا','German','ألماني','🇩🇪'),
('GR','Greece','اليونان','Greek','يوناني','🇬🇷'),
('HU','Hungary','المجر','Hungarian','مجري','🇭🇺'),
('IS','Iceland','آيسلندا','Icelandic','آيسلندي','🇮🇸'),
('IE','Ireland','أيرلندا','Irish','أيرلندي','🇮🇪'),
('IT','Italy','إيطاليا','Italian','إيطالي','🇮🇹'),
('LV','Latvia','لاتفيا','Latvian','لاتفي','🇱🇻'),
('LI','Liechtenstein','ليختنشتاين','Liechtensteiner','ليختنشتايني','🇱🇮'),
('LT','Lithuania','ليتوانيا','Lithuanian','ليتواني','🇱🇹'),
('LU','Luxembourg','لوكسمبورغ','Luxembourgish','لوكسمبورغي','🇱🇺'),
('MT','Malta','مالطا','Maltese','مالطي','🇲🇹'),
('MD','Moldova','مولدوفا','Moldovan','مولدوفي','🇲🇩'),
('MC','Monaco','موناكو','Monégasque','موناكي','🇲🇨'),
('ME','Montenegro','الجبل الأسود','Montenegrin','مونتينيغري','🇲🇪'),
('NL','Netherlands','هولندا','Dutch','هولندي','🇳🇱'),
('MK','North Macedonia','مقدونيا الشمالية','Macedonian','مقدوني','🇲🇰'),
('NO','Norway','النرويج','Norwegian','نرويجي','🇳🇴'),
('PL','Poland','بولندا','Polish','بولندي','🇵🇱'),
('PT','Portugal','البرتغال','Portuguese','برتغالي','🇵🇹'),
('RO','Romania','رومانيا','Romanian','روماني','🇷🇴'),
('RU','Russia','روسيا','Russian','روسي','🇷🇺'),
('SM','San Marino','سان مارينو','Sammarinese','سان مارينسي','🇸🇲'),
('RS','Serbia','صربيا','Serbian','صربي','🇷🇸'),
('SK','Slovakia','سلوفاكيا','Slovak','سلوفاكي','🇸🇰'),
('SI','Slovenia','سلوفينيا','Slovenian','سلوفيني','🇸🇮'),
('ES','Spain','إسبانيا','Spanish','إسباني','🇪🇸'),
('SE','Sweden','السويد','Swedish','سويدي','🇸🇪'),
('CH','Switzerland','سويسرا','Swiss','سويسري','🇨🇭'),
('UA','Ukraine','أوكرانيا','Ukrainian','أوكراني','🇺🇦'),
('GB','United Kingdom','المملكة المتحدة','British','بريطاني','🇬🇧'),
('VA','Vatican City','الفاتيكان','Vatican','فاتيكاني','🇻🇦'),
('XK','Kosovo','كوسوفو','Kosovar','كوسوفي','🇽🇰');

-- ------------------------------------------------------------------ North America (23 UN members)
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('AG','Antigua and Barbuda','أنتيغوا وباربودا','Antiguan','أنتيغوي','🇦🇬'),
('BS','Bahamas','باهاماس','Bahamian','باهامي','🇧🇸'),
('BB','Barbados','باربادوس','Barbadian','باربادوسي','🇧🇧'),
('BZ','Belize','بليز','Belizean','بليزي','🇧🇿'),
('CA','Canada','كندا','Canadian','كندي','🇨🇦'),
('CR','Costa Rica','كوستاريكا','Costa Rican','كوستاريكي','🇨🇷'),
('CU','Cuba','كوبا','Cuban','كوبي','🇨🇺'),
('DM','Dominica','دومينيكا','Dominican','دومينيكي','🇩🇲'),
('DO','Dominican Republic','جمهورية الدومينيكان','Dominican','دومينيكاني','🇩🇴'),
('SV','El Salvador','السلفادور','Salvadoran','سلفادوري','🇸🇻'),
('GD','Grenada','غرينادا','Grenadian','غرينادي','🇬🇩'),
('GT','Guatemala','غواتيمالا','Guatemalan','غواتيمالي','🇬🇹'),
('HT','Haiti','هايتي','Haitian','هايتي','🇭🇹'),
('HN','Honduras','هندوراس','Honduran','هندوراسي','🇭🇳'),
('JM','Jamaica','جامايكا','Jamaican','جامايكي','🇯🇲'),
('MX','Mexico','المكسيك','Mexican','مكسيكي','🇲🇽'),
('NI','Nicaragua','نيكاراغوا','Nicaraguan','نيكاراغوي','🇳🇮'),
('PA','Panama','بنما','Panamanian','بنمي','🇵🇦'),
('KN','Saint Kitts and Nevis','سانت كيتس ونيفيس','Kittitian','سانت كيتسي','🇰🇳'),
('LC','Saint Lucia','سانت لوسيا','Saint Lucian','لوسياني','🇱🇨'),
('VC','Saint Vincent and the Grenadines','سانت فينسنت والغرينادين','Vincentian','فينسنتي','🇻🇨'),
('TT','Trinidad and Tobago','ترينيداد وتوباغو','Trinidadian','ترينيدادي','🇹🇹'),
('US','United States','الولايات المتحدة','American','أمريكي','🇺🇸');

-- ------------------------------------------------------------------ South America (12 UN members)
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('AR','Argentina','الأرجنتين','Argentine','أرجنتيني','🇦🇷'),
('BO','Bolivia','بوليفيا','Bolivian','بوليفي','🇧🇴'),
('BR','Brazil','البرازيل','Brazilian','برازيلي','🇧🇷'),
('CL','Chile','تشيلي','Chilean','تشيلي','🇨🇱'),
('CO','Colombia','كولومبيا','Colombian','كولومبي','🇨🇴'),
('EC','Ecuador','الإكوادور','Ecuadorian','إكوادوري','🇪🇨'),
('GY','Guyana','غيانا','Guyanese','غياني','🇬🇾'),
('PY','Paraguay','باراغواي','Paraguayan','باراغواياني','🇵🇾'),
('PE','Peru','بيرو','Peruvian','بيروفي','🇵🇪'),
('SR','Suriname','سورينام','Surinamese','سورينامي','🇸🇷'),
('UY','Uruguay','أوروغواي','Uruguayan','أوروغواياني','🇺🇾'),
('VE','Venezuela','فنزويلا','Venezuelan','فنزويلي','🇻🇪');

-- ------------------------------------------------------------------ Oceania (14 UN members)
INSERT INTO countries (id, name_en, name_ar, nationality_en, nationality_ar, flag) VALUES
('AU','Australia','أستراليا','Australian','أسترالي','🇦🇺'),
('FJ','Fiji','فيجي','Fijian','فيجي','🇫🇯'),
('KI','Kiribati','كيريباتي','I-Kiribati','كيريباتي','🇰🇮'),
('MH','Marshall Islands','جزر مارشال','Marshallese','مارشالي','🇲🇭'),
('FM','Micronesia','ميكرونيزيا','Micronesian','ميكرونيزي','🇫🇲'),
('NR','Nauru','ناورو','Nauruan','ناوروي','🇳🇷'),
('NZ','New Zealand','نيوزيلندا','New Zealander','نيوزيلندي','🇳🇿'),
('PW','Palau','بالاو','Palauan','بالاوي','🇵🇼'),
('PG','Papua New Guinea','بابوا غينيا الجديدة','Papua New Guinean','بابوي','🇵🇬'),
('WS','Samoa','ساموا','Samoan','ساموي','🇼🇸'),
('SB','Solomon Islands','جزر سليمان','Solomon Islander','سليماني','🇸🇧'),
('TO','Tonga','تونغا','Tongan','تونغي','🇹🇴'),
('TV','Tuvalu','توفالو','Tuvaluan','توفالوي','🇹🇻'),
('VU','Vanuatu','فانواتو','Ni-Vanuatu','فانواتوي','🇻🇺');

-- ==================================================================================================
-- PHASE 1 — add the new code columns and migrate existing data into them. The OLD columns are left in
-- place on purpose: run this first, check the "unmatched" SELECTs below, fix anything they list (either
-- edit the row's old text value to something that matches and re-run just that UPDATE, or set its new
-- *_code column by hand to the right 2-letter code), THEN run 012_countries_cleanup.sql to drop the old
-- columns. Safe to run more than once — every UPDATE only touches rows it can actually match.
-- ==================================================================================================

-- employees.nationality_id (FK to lookups, where the lookup value is a country name) -> employees.nationality_code
ALTER TABLE employees ADD COLUMN nationality_code CHAR(2) NULL AFTER nationality_id;
UPDATE employees e
  JOIN lookups l ON l.id = e.nationality_id
  JOIN countries c ON c.name_en = l.value_en
  SET e.nationality_code = c.id
  WHERE e.nationality_id IS NOT NULL;

-- parties.nationality (free text) -> parties.nationality_code
ALTER TABLE parties ADD COLUMN nationality_code CHAR(2) NULL AFTER nationality;
UPDATE parties p
  JOIN countries c ON c.name_en = p.nationality OR c.name_ar = p.nationality OR c.nationality_en = p.nationality OR c.nationality_ar = p.nationality
  SET p.nationality_code = c.id
  WHERE p.nationality IS NOT NULL AND p.nationality <> '';

-- parties.country (free text) -> parties.country_code
ALTER TABLE parties ADD COLUMN country_code CHAR(2) NULL AFTER country;
UPDATE parties p
  JOIN countries c ON c.name_en = p.country OR c.name_ar = p.country
  SET p.country_code = c.id
  WHERE p.country IS NOT NULL AND p.country <> '';

-- horses.origin_country (free text) -> horses.origin_country_code
ALTER TABLE horses ADD COLUMN origin_country_code CHAR(2) NULL AFTER origin_country;
UPDATE horses h
  JOIN countries c ON c.name_en = h.origin_country OR c.name_ar = h.origin_country
  SET h.origin_country_code = c.id
  WHERE h.origin_country IS NOT NULL AND h.origin_country <> '';

-- shows.country (free text) -> shows.country_code
ALTER TABLE shows ADD COLUMN country_code CHAR(2) NULL AFTER country;
UPDATE shows s
  JOIN countries c ON c.name_en = s.country OR c.name_ar = s.country
  SET s.country_code = c.id
  WHERE s.country IS NOT NULL AND s.country <> '';

-- ------------------------------------------------------------------ unmatched values — fix these by hand before Phase 2
SELECT e.id, e.name_en AS employee, l.value_en AS old_nationality
  FROM employees e JOIN lookups l ON l.id = e.nationality_id
  WHERE e.nationality_code IS NULL;

SELECT id, name_en AS party, nationality AS old_nationality
  FROM parties WHERE nationality IS NOT NULL AND nationality <> '' AND nationality_code IS NULL;

SELECT id, name_en AS party, country AS old_country
  FROM parties WHERE country IS NOT NULL AND country <> '' AND country_code IS NULL;

SELECT id, name_en AS horse, origin_country AS old_origin_country
  FROM horses WHERE origin_country IS NOT NULL AND origin_country <> '' AND origin_country_code IS NULL;

SELECT id, name_en AS show_name, country AS old_country
  FROM shows WHERE country IS NOT NULL AND country <> '' AND country_code IS NULL;
