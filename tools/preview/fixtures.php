<?php
/**
 * Fixture data for the database-free preview: the stud's horses, parents, shows and results from the horse
 * information cards (database/data/horse_cards_2026-10-07.json), with preview photos, sample news, gallery and team.
 * Returns ['settings', 'horses', 'files', 'photos', 'results', 'news', 'gallery', 'experts'].
 */
declare(strict_types=1);

$json = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/database/data/horse_cards_2026-10-07.json'), true);

// ---- the photos (names in tools/preview/photos or $PREVIEW_PHOTOS: name.jpg + name-thumb.jpg), file ids from 1
$photoNames = ['hero-samed', 'stallion-2', 'stallion-3', 'grey-run', 'grey-rear', 'grey-halter', 'grey-fence', 'grey-portrait', 'grey-detail', 'bay-head-bw',
    'chestnut-head', 'chestnut-sunset', 'bay-eye', 'bay-trot', 'grey-head-bw', 'white-gallop', 'white-portrait', 'foal-1', 'foal-2', 'foal-3', 'foal-4', 'foal-5',
    'mare-foal', 'foal-6', 'dunes-1', 'dunes-2', 'dunes-sunset', 'qatar-night', 'desert-tree', 'falcon'];
$files = [];
foreach ($photoNames as $i => $n) {
    $files[$i + 1] = ['id' => $i + 1, 'name' => $n, 'title' => null, 'width' => 2000, 'height' => 1333, 'mime' => 'image/jpeg'];
}
$fid = fn (string $name) => (int) array_search($name, $photoNames, true) + 1;

// ---- horses: which photos each horse has (the first is its main photo); horses not listed show the brand tile
$horsePhotos = [
    'D SAMED' => ['hero-samed', 'stallion-2', 'grey-head-bw', 'grey-detail'],
    'D TUMOOH' => ['chestnut-sunset', 'chestnut-head'],
    'HAYMAN AL BAHYA' => ['chestnut-head'],
    'AJ RAZNEH' => ['bay-trot', 'bay-eye'],
    'BADRAH AL SHAQAB' => ['grey-portrait', 'grey-fence'],
    'BAHGA AL FARIDA' => ['grey-halter'],
    'BDOOR AL BAYHA' => ['white-portrait'],
    'D JARASH' => ['stallion-3'],
    'D MUNISAH' => ['grey-fence'],
    'MEERA AL MASHRAB' => ['grey-run'],
    'SG SHAMMAH' => ['bay-head-bw'],
    'WAAD AL AQEED' => ['mare-foal'],
    'BSHARA AL WAJBA' => ['grey-rear'],
    'AMERA AL SHAQAB' => ['white-gallop'],
    'LAYALI SK' => ['foal-1'],
    'SHOMOOKH OBAL' => ['grey-detail'],
    'SULTAN AL GHAF' => ['foal-3'],
    'SHARAR ALMANHAL' => ['foal-5'],
    'AURELIA SK' => ['foal-2'],
    'LAVA SK' => ['foal-4'],
    'LUNA SK' => ['foal-6'],
    "RI'BAL SK" => ['foal-1'],
];
$arabic = [
    'D SAMED' => 'دي صامد', 'D TUMOOH' => 'دي طموح', 'HAYMAN AL BAHYA' => 'هيمان البهية', 'AJ RAZNEH' => 'إيه جيه رزنة', 'BADRAH AL SHAQAB' => 'بدرة الشقب',
    'BAHGA AL FARIDA' => 'بهجة الفريدة', 'BDOOR AL BAYHA' => 'بدور البهية', 'BSHARA AL WAJBA' => 'بشارة الوجبة', "D A'BAQ" => 'دي عبق', 'D JARASH' => 'دي جرش',
    'D MAYRAMEYYAH' => 'دي ميرمية', 'D MUNISAH' => 'دي مؤنسة', 'JAMELLAT NUDRAH' => 'جميلة ندرة', 'MABROKAH MK' => 'مبروكة إم كي', 'MEERA AL MASHRAB' => 'ميرة المشرب',
    'SG SHAMMAH' => 'إس جي شمّة', 'SIHAL AL SHAQAB' => 'سهال الشقب', 'WAAD AL AQEED' => 'وعد العقيد', 'AMERA AL SHAQAB' => 'أميرة الشقب', 'ARYAM AL THAMER' => 'أريام الثامر',
    'LAYALI SK' => 'ليالي إس كي', 'MAWARA' => 'موارة', 'NIMRA AL SHAQAB' => 'نمرة الشقب', 'SALMA ALWAJBA' => 'سلمى الوجبة', 'SHOMOOKH OBAL' => 'شموخ عبال',
    'SHARAR ALMANHAL' => 'شرار المنهل', 'SULTAN AL GHAF' => 'سلطان الغاف', 'AURELIA SK' => 'أوريليا إس كي', 'LAVA SK' => 'لافا إس كي', 'LUNA SK' => 'لونا إس كي',
    'NAWARA SK' => 'نوارة إس كي', "RI'BAL SK" => 'ربال إس كي',
];
$colours = ['Grey' => 'أشهب', 'Bay' => 'كميت', 'Chestnut' => 'أشقر'];
$countries = ['AE' => ['United Arab Emirates', 'الإمارات'], 'QA' => ['Qatar', 'قطر'], 'SA' => ['Saudi Arabia', 'السعودية'], 'DE' => ['Germany', 'ألمانيا'], 'IT' => ['Italy', 'إيطاليا']];
$stories = [
    'D SAMED' => "D Samed is the senior stallion of SK Arabians: a grey son of FA El Rasheem out of D Fitoon, bred by Dubai Arabian Horse Stud and shown across Europe and the Gulf. Gold Senior Stallion and Best Male at the 41st All Nations Cup in Aachen, he combines a classic head and a long, elegant neck with the movement the judges' cards keep rewarding.\n\nAt SK he stands as the foundation sire of the programme, and the first foals by him were born at the stud in 2026.",
    'D TUMOOH' => "A chestnut son of the Dubai programme, D Tumooh brings the FA El Shawan line to the stud: a short, dished head, large dark eyes and a tail carried high in motion.",
    'LAYALI SK' => "The first filly born at SK Arabians, on a May morning in 2025 — a chestnut by the stud's own stallion out of a Shaqab mare.",
];
$slug = fn (string $n) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $n), '-'));
$parents = $json['parents'];
$allNames = array_keys($json['horses']);
$ids = [];
$n = 0;
foreach ($allNames as $name) { $ids[$name] = ++$n; }
$sk = array_filter($json['horses'], fn ($h) => !empty($h['sk']));
$isPublic = fn (string $name) => isset($sk[$name]);
// bloodlines: the sire line (sire's name) of the public horses
$bloodlineOf = fn (string $name) => $parents[$name][0] ?? null;

$horses = [];
foreach ($sk as $name => $h) {
    [$sire, $dam] = $parents[$name] ?? [null, null];
    $photos = $horsePhotos[$name] ?? [];
    $horses[$ids[$name]] = [
        'id' => $ids[$name], 'name_en' => $name, 'name_ar' => $arabic[$name] ?? null, 'slug' => $slug($name), 'sex' => $h['sex'] ?? ($h['category'] === 'stallion' || $h['category'] === 'colt' ? 'male' : 'female'),
        'category' => $h['category'], 'dob' => $h['dob'], 'bloodline' => $bloodlineOf($name), 'main_photo_id' => $photos ? $fid($photos[0]) : null,
        'titles' => 0, 'story_en' => $stories[$name] ?? null, 'story_ar' => null, 'video_url' => null,
        'sire_id' => $sire ? $ids[$sire] ?? null : null, 'dam_id' => $dam ? $ids[$dam] ?? null : null,
        'is_favorite' => in_array($name, ['D SAMED', 'AJ RAZNEH', 'BADRAH AL SHAQAB', 'D TUMOOH', 'MEERA AL MASHRAB'], true) ? 1 : 0, 'status' => 'active',
        'color_en' => $h['color'], 'color_ar' => $colours[$h['color']] ?? null, 'breed_en' => 'Purebred Arabian', 'breed_ar' => 'عربي أصيل',
        'sire_en' => $sire, 'sire_ar' => $sire ? ($arabic[$sire] ?? null) : null, 'sire_slug' => $sire ? $slug($sire) : null, 'sire_public' => $sire && $isPublic($sire) ? 1 : 0,
        'dam_en' => $dam, 'dam_ar' => $dam ? ($arabic[$dam] ?? null) : null, 'dam_slug' => $dam ? $slug($dam) : null, 'dam_public' => $dam && $isPublic($dam) ? 1 : 0,
        'breeder' => $h['breeder'], 'country_en' => $countries[$h['country'] ?? ''][0] ?? null, 'country_ar' => $countries[$h['country'] ?? ''][1] ?? null,
        'born_at_sk' => !empty($h['born_at_sk']) ? 1 : 0, 'website_sort' => null,
        'photos' => array_map($fid, $photos),
    ];
}
// pedigree nodes for every horse named in the cards (public or not)
$nodes = [];
foreach ($allNames as $name) {
    [$sire, $dam] = $parents[$name] ?? [null, null];
    $nodes[$ids[$name]] = ['id' => $ids[$name], 'name_en' => $name, 'name_ar' => $arabic[$name] ?? null, 'slug' => $isPublic($name) ? $slug($name) : null,
        'sire_id' => $sire ? $ids[$sire] ?? null : null, 'dam_id' => $dam ? $ids[$dam] ?? null : null];
}

// ---- results: every medal, title or placing of the cards, as the website's results() rows
$showCity = ['GCAT Aachen Final / All Nations Cup' => ['Aachen', 'Germany', 'ألمانيا'], 'GCAT London' => ['London', 'United Kingdom', 'المملكة المتحدة'], 'GCAT Muscat' => ['Muscat', 'Oman', 'عُمان'],
    'PSAIAHF' => ['Paris', 'France', 'فرنسا'], '41st All Nations Cup' => ['Aachen', 'Germany', 'ألمانيا'], '8th International AHC Show' => ['Doha', 'Qatar', 'قطر']];
$results = [];
$rid = 0;
foreach ($json['results'] as $r) {
    if (!isset($ids[$r['horse']])) { continue; }
    $show = $json['shows'][$r['show']] ?? null;
    if (!$show) { continue; }
    $place = $showCity[$show['name']] ?? [$show['city'] ?? null, $show['country'] ?? null, null];
    $results[] = [
        'id' => ++$rid, 'class_name' => $r['class'], 'placing' => $r['placing'], 'title_en' => $r['title'], 'title_ar' => null, 'medal' => $r['medal'] ?: 'none', 'score' => $r['score'],
        'judge_card' => !empty($r['judge_card']) ? json_encode($r['judge_card'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'show_en' => $show['name'], 'show_ar' => null, 'city' => $place[0], 'country' => null, 'country_en' => $place[1], 'country_ar' => $place[2],
        'start_date' => !empty($show['year_only']) ? (string) $show['year'] : ($show['start'] ?? (string) $show['year']), 'organizer' => null,
        'horse_en' => $r['horse'], 'horse_ar' => $arabic[$r['horse']] ?? null, 'slug' => $slug($r['horse']), 'show_on_website' => $isPublic($r['horse']) ? 1 : 0,
        'category' => $json['horses'][$r['horse']]['category'] ?? 'mare', 'dob' => $json['horses'][$r['horse']]['dob'] ?? null, 'horse_id' => $ids[$r['horse']],
    ];
}
usort($results, fn ($a, $b) => [$b['start_date'], array_search($a['medal'], ['gold', 'silver', 'bronze', 'none'], true), $a['placing']] <=> [$a['start_date'], array_search($b['medal'], ['gold', 'silver', 'bronze', 'none'], true), $b['placing']]);

$settings = [
    'site.established' => '2025', 'site.sections' => 'horses,featured,foals,bloodlines,achievements,story,gallery,news,experts,contact',
    'site.hero_title_en' => 'Purebred Arabian Horses of Distinction', 'site.hero_title_ar' => 'نخبة من الخيل العربية الأصيلة',
    'site.hero_sub_en' => 'A private stud in Doha devoted to the classic Arabian — type, movement and character — presented with pride in the show ring.',
    'site.hero_sub_ar' => 'مربط خاص في الدوحة مكرّس للخيل العربية الكلاسيكية — جمالاً وحركةً وشخصية — تُقدَّم بفخر في حلبات البطولات.',
    'site.story_en' => "SK Arabians was founded in Qatar in 2025 with one purpose: to care for and present purebred Arabian horses of exceptional type, movement and character, with pride in the show ring.\n\nThe collection brings together stallions and mares of the great Shaqab, Dubai and Ajman lines, and the first foals carrying the SK name were born at the stud in 2026.",
    'site.story_ar' => "تأسس مربط اس كي ارابيان في قطر عام 2025 بهدف واحد: رعاية خيل عربية أصيلة بجمال وحركة وشخصية استثنائية، وتقديمها بفخر في حلبات البطولات.\n\nتجمع المجموعة فحولاً وأفراساً من سلالات الشقب ودبي وعجمان العريقة، وقد وُلدت أولى المهور التي تحمل اسم اس كي في المربط عام 2026.",
    'site.pillar1_title_en' => 'Heritage', 'site.pillar1_text_en' => 'Documented pedigrees of proven, classic Arabian lines.', 'site.pillar1_title_ar' => 'الإرث', 'site.pillar1_text_ar' => 'أنساب موثقة لسلالات عربية أصيلة ومجربة.',
    'site.pillar2_title_en' => 'Care', 'site.pillar2_text_en' => 'Veterinary, farriery and nutrition to the highest standard, every day.', 'site.pillar2_title_ar' => 'الرعاية', 'site.pillar2_text_ar' => 'رعاية بيطرية وتغذية وبيطرة بأعلى المعايير، كل يوم.',
    'site.pillar3_title_en' => 'Distinction', 'site.pillar3_text_en' => 'Presented with pride at GCAT and the international shows.', 'site.pillar3_title_ar' => 'التميّز', 'site.pillar3_text_ar' => 'تُقدَّم بفخر في بطولات GCAT والبطولات الدولية.',
    'site.featured_horse_id' => (string) $ids['D SAMED'],
    'site.results_from_year' => '2025', 'site.results_medals_only' => '1', 'site.results_show_class' => '1',
    'site.page_champions' => '1', 'site.page_news' => '1', 'site.page_about' => '1', 'site.page_contact' => '1',
    'company.instagram' => 'https://www.instagram.com/skarabians', 'company.x' => 'https://x.com/skarabians', 'company.youtube' => 'https://www.youtube.com/@skarabians',
    'company.mobile' => '+974 3358 1816', 'company.whatsapp' => '+97433581816', 'company.email' => 'contact@skarabian.com', 'company.po_box' => '6657', 'company.cr' => '231961',
    'company.map_query' => 'Al Shahaniya, Qatar',
];

$news = [
    ['id' => 1, 'slug' => 'd-samed-gold-senior-stallion-all-nations-cup', 'title_en' => 'D Samed crowned Gold Senior Stallion and Best Male at the All Nations Cup', 'title_ar' => 'دي صامد يتوّج بذهبية الفحول وأفضل ذكر في كأس الأمم',
        'summary_en' => 'In Aachen, the senior stallion of SK Arabians won his class with the highest score of the show and took the gold championship.', 'summary_ar' => 'في آخن، فاز فحل المربط بفئته بأعلى درجة في البطولة ونال الذهبية.',
        'body_en' => "The 41st All Nations Cup in Aachen brought SK Arabians its finest result yet. D Samed, the grey son of FA El Rasheem, won class 11 with 92.13 points, the Highest Score Award of the show, and was named Gold Senior Stallion and Best Male.\n\nThe championship was judged by a panel of six, with marks of 20 for type and head on several cards. He returns to Doha in December for the winter season.",
        'body_ar' => 'جلب كأس الأمم الحادي والأربعون في آخن أفضل نتيجة للمربط حتى الآن.', 'image_id' => $fid('stallion-2'), 'published_at' => '2025-09-28 10:00:00'],
    ['id' => 2, 'slug' => 'layali-sk-first-foal-born-at-the-stud', 'title_en' => 'Layali SK — the first foal born at SK Arabians', 'title_ar' => 'ليالي إس كي — أول مهرة تولد في المربط',
        'summary_en' => 'A chestnut filly arrived on a May morning: the first of the SK generation.', 'summary_ar' => 'مهرة شقراء وُلدت في صباح من أيار: أولى مهور جيل اس كي.',
        'body_en' => "Layali SK was born at dawn on 5 May 2025, a chestnut filly with a star. Mare and foal are well, and the team watched her first steps in the paddock the same morning.", 'body_ar' => 'وُلدت ليالي إس كي فجر الخامس من مايو 2025.', 'image_id' => $fid('foal-1'), 'published_at' => '2025-05-12 09:00:00'],
    ['id' => 3, 'slug' => 'sk-arabians-at-gcat-muscat-2026', 'title_en' => 'SK Arabians at GCAT Muscat 2026', 'title_ar' => 'اس كي ارابيان في GCAT مسقط 2026',
        'summary_en' => 'Silver Senior Stallion for D Samed with 92.43 points, and a first international outing for the young mares.', 'summary_ar' => 'فضية الفحول لدي صامد بـ92.43 نقطة، وأول ظهور دولي للأفراس الشابة.',
        'body_en' => "Muscat opened the 2026 season for the stud. D Samed won his class with 92.43 points and took the silver championship; Badrah Al Shaqab and Meera Al Mashrab made their first international appearances.", 'body_ar' => 'افتتحت مسقط موسم 2026 للمربط.', 'image_id' => $fid('grey-halter'), 'published_at' => '2026-02-20 18:00:00'],
];
$gallery = [
    ['id' => 1, 'file_id' => $fid('grey-run'), 'video_url' => null, 'caption_en' => 'Meera Al Mashrab at liberty', 'caption_ar' => 'ميرة المشرب طليقة', 'slug' => $slug('MEERA AL MASHRAB')],
    ['id' => 2, 'file_id' => $fid('grey-detail'), 'video_url' => null, 'caption_en' => 'Dawn at the stud', 'caption_ar' => 'الفجر في المربط', 'slug' => null],
    ['id' => 3, 'file_id' => $fid('bay-eye'), 'video_url' => null, 'caption_en' => 'AJ Razneh', 'caption_ar' => 'إيه جيه رزنة', 'slug' => $slug('AJ RAZNEH')],
    ['id' => 4, 'file_id' => $fid('dunes-sunset'), 'video_url' => null, 'caption_en' => 'The desert at Al Shahaniya', 'caption_ar' => 'الصحراء في الشحانية', 'slug' => null],
    ['id' => 5, 'file_id' => $fid('grey-rear'), 'video_url' => null, 'caption_en' => 'Bshara Al Wajba', 'caption_ar' => 'بشارة الوجبة', 'slug' => $slug('BSHARA AL WAJBA')],
    ['id' => 6, 'file_id' => $fid('chestnut-head'), 'video_url' => null, 'caption_en' => 'Hayman Al Bahya', 'caption_ar' => 'هيمان البهية', 'slug' => $slug('HAYMAN AL BAHYA')],
    ['id' => 7, 'file_id' => $fid('dunes-1'), 'video_url' => null, 'caption_en' => 'The dunes', 'caption_ar' => 'الكثبان', 'slug' => null],
    ['id' => 8, 'file_id' => $fid('white-gallop'), 'video_url' => null, 'caption_en' => 'Amera Al Shaqab', 'caption_ar' => 'أميرة الشقب', 'slug' => $slug('AMERA AL SHAQAB')],
    ['id' => 9, 'file_id' => $fid('qatar-night'), 'video_url' => null, 'caption_en' => 'Doha by night', 'caption_ar' => 'الدوحة ليلاً', 'slug' => null],
    ['id' => 10, 'file_id' => $fid('mare-foal'), 'video_url' => null, 'caption_en' => 'Waad Al Aqeed and her foal', 'caption_ar' => 'وعد العقيد ومهرها', 'slug' => $slug('WAAD AL AQEED')],
];
$experts = [
    ['id' => 1, 'name_en' => 'Claudia Darius', 'name_ar' => 'كلاوديا داريوس', 'photo_id' => null, 'public_title_en' => 'Stud Manager', 'public_title_ar' => 'مديرة المربط', 'public_bio_en' => 'Thirty years with the Arabian horse, from the European show circuit to the Gulf.', 'public_bio_ar' => null, 'specialties_en' => 'Breeding · Shows', 'specialties_ar' => 'الإنتاج · البطولات', 'position_en' => null, 'position_ar' => null],
    ['id' => 2, 'name_en' => 'Ahmed Al Hajri', 'name_ar' => 'أحمد الهاجري', 'photo_id' => null, 'public_title_en' => 'Head Trainer', 'public_title_ar' => 'كبير المدربين', 'public_bio_en' => 'Conditioning and presentation of the show string.', 'public_bio_ar' => null, 'specialties_en' => 'Halter · Conditioning', 'specialties_ar' => 'العرض · التكييف', 'position_en' => null, 'position_ar' => null],
    ['id' => 3, 'name_en' => 'Dr. Sara Mansour', 'name_ar' => 'د. سارة منصور', 'photo_id' => null, 'public_title_en' => 'Veterinarian', 'public_title_ar' => 'الطبيبة البيطرية', 'public_bio_en' => 'Reproduction and foal care.', 'public_bio_ar' => null, 'specialties_en' => 'Reproduction · Foals', 'specialties_ar' => 'التكاثر · المهور', 'position_en' => null, 'position_ar' => null],
];

return ['settings' => $settings, 'horses' => $horses, 'nodes' => $nodes, 'files' => $files, 'results' => $results, 'news' => $news, 'gallery' => $gallery, 'experts' => $experts];
