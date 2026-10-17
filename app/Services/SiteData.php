<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Public website data. Everything comes live from the Horses module (only records marked "show on website").
 * Never returns prices, owner details, medical records or breeding information (no breeding plans, pregnancies,
 * embryos or offspring lists — the public site presents the horses themselves). The homepage foals and bloodlines
 * sections only regroup horses that are already public.
 */
final class SiteData
{
    /** The website only shows photos marked public: the main photo if it is, otherwise the newest public photo. */
    private const PHOTO_COL = "COALESCE((SELECT f.id FROM files f WHERE f.id = h.main_photo_id AND f.is_public = 1 AND f.deleted_at IS NULL),
        (SELECT f.id FROM files f WHERE f.owner_type = 'horse' AND f.owner_id = h.id AND f.category = 'photo' AND f.is_public = 1 AND f.deleted_at IS NULL
         ORDER BY f.sort_order, f.id DESC LIMIT 1)) AS main_photo_id";

    /** Medals, in their order. */
    public const MEDALS = ['gold', 'silver', 'bronze'];

    /** Championship medals shown on the website: the horse's public medals within Website → Show results. */
    private static function titlesCol(): string
    {
        return '(SELECT COUNT(*) FROM show_results r JOIN shows rs ON rs.id = r.show_id WHERE r.horse_id = h.id AND '
            . self::resultFilter('r', 'rs', true) . ') AS titles';
    }

    private static function horseCols(): string
    {
        return 'h.id, h.name_en, h.name_ar, h.slug, h.sex, h.category, h.dob, h.bloodline, ' . self::PHOTO_COL . ', ' . self::titlesCol() . ',
        h.story_en, h.story_ar, h.video_url, h.sire_id, h.dam_id, h.is_favorite, h.status,
        c.value_en AS color_en, c.value_ar AS color_ar, b.value_en AS breed_en, b.value_ar AS breed_ar,
        s.name_en AS sire_en, s.name_ar AS sire_ar, s.slug AS sire_slug, (' . self::publicWhere('s') . ') AS sire_public,
        d.name_en AS dam_en, d.name_ar AS dam_ar, d.slug AS dam_slug, (' . self::publicWhere('d') . ') AS dam_public';
    }

    // ---- Website → Show results: which show results the public site shows (the portal always keeps every one)

    /** Results of shows from this year on (0 = every year). */
    public static function resultsFromYear(): int
    {
        return max(0, (int) Settings::get('site.results_from_year', '2025'));
    }

    /** Medals only (gold, silver, bronze): other placings are left off the website. */
    public static function medalsOnly(): bool
    {
        return (string) Settings::get('site.results_medals_only', '1') === '1';
    }

    /** Whether results name their class and title ("Gold Senior Stallion"), or just the medal ("Gold"). */
    public static function showClass(): bool
    {
        return (string) Settings::get('site.results_show_class', '0') === '1';
    }

    /** SQL conditions for a public result (alias $r) of a show (alias $s); $medals: medals only whatever the setting. */
    private static function resultFilter(string $r = 'r', string $s = 's', bool $medals = false): string
    {
        $w = "$r.deleted_at IS NULL AND $s.deleted_at IS NULL AND $r.is_public = 1";
        if ($medals || self::medalsOnly()) {
            $w .= " AND $r.medal IN ('gold','silver','bronze')";
        }
        if ($y = self::resultsFromYear()) {
            $w .= " AND $s.start_date >= '" . $y . "-01-01'";
        }
        return $w;
    }

    /** What a result says on the website: its title (or class) when the class is shown, else just the medal. */
    public static function resultText(array $r): string
    {
        $medal = in_array($r['medal'] ?? '', self::MEDALS, true) ? __('shows.medal_' . $r['medal']) : '';
        return self::showClass() ? (string) (loc($r, 'title') ?: ($r['class_name'] ?? '') ?: $medal) : $medal;
    }

    /** The order of the horse cards (as on the stud's horse cards): stallions, mares, fillies, colts, foals. */
    public const CATEGORIES = ['stallion', 'mare', 'filly', 'colt', 'foal'];

    private static function horseFrom(): string
    {
        return 'horses h LEFT JOIN lookups c ON c.id = h.color_id LEFT JOIN lookups b ON b.id = h.breed_id
                LEFT JOIN horses s ON s.id = h.sire_id LEFT JOIN horses d ON d.id = h.dam_id';
    }

    /** A horse (alias $a) whose page is on the website: a link to a horse only when this holds (else a 404). */
    private static function publicWhere(string $a = 'h'): string
    {
        return "$a.show_on_website = 1 AND $a.deleted_at IS NULL AND $a.archived_at IS NULL AND $a.status IN ('active','in_shelter')";
    }

    /** Category, then the order set in Website → Horses on website (empty = after the numbered ones), featured, name. */
    private static function order(): string
    {
        return "FIELD(h.category,'stallion','mare','filly','colt','foal'), "
            . (DB::hasColumn('horses', 'website_sort') ? 'h.website_sort IS NULL, h.website_sort, ' : '')
            . 'h.is_favorite DESC, h.name_en';
    }

    public static function horses(array $filters = [], ?int $limit = null): array
    {
        $where = [self::publicWhere()];
        $params = [];
        if (!empty($filters['category']) && in_array($filters['category'], self::CATEGORIES, true)) {
            $where[] = 'h.category = :cat';
            $params['cat'] = $filters['category'];
        }
        if (!empty($filters['sex']) && in_array($filters['sex'], ['male', 'female'], true)) {
            $where[] = 'h.sex = :sex';
            $params['sex'] = $filters['sex'];
        }
        if (!empty($filters['bloodline'])) {
            $where[] = 'h.bloodline = :bl';
            $params['bl'] = $filters['bloodline'];
        }
        if (!empty($filters['age'])) {
            [$min, $max] = match ($filters['age']) {
                'u1' => [0, 1], '1-3' => [1, 4], '4-10' => [4, 11], '10+' => [11, 60], default => [0, 60],
            };
            $where[] = 'h.dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, h.dob, CURDATE()) >= :amin AND TIMESTAMPDIFF(YEAR, h.dob, CURDATE()) < :amax';
            $params['amin'] = $min;
            $params['amax'] = $max;
        }
        $sql = 'SELECT ' . self::horseCols() . ' FROM ' . self::horseFrom() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . self::order();
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return DB::all($sql, $params);
    }

    /** Horses grouped by category in the website order: [category => [horse, ...]] (empty categories left out). */
    public static function grouped(array $horses): array
    {
        $groups = array_fill_keys(self::CATEGORIES, []);
        foreach ($horses as $h) {
            $groups[$h['category']][] = $h;
        }
        return array_filter($groups);
    }

    public static function horse(string $slug): ?array
    {
        return DB::row('SELECT ' . self::horseCols() . ', h.breeder, co.name_en AS country_en, co.name_ar AS country_ar FROM ' . self::horseFrom()
            . ' LEFT JOIN countries co ON co.id = h.origin_country WHERE h.slug = :slug AND ' . self::publicWhere(), ['slug' => $slug]);
    }

    public static function featured(): ?array
    {
        $id = (int) Settings::get('site.featured_horse_id', 0);
        $row = $id ? DB::row('SELECT ' . self::horseCols() . ' FROM ' . self::horseFrom() . ' WHERE h.id = :id AND ' . self::publicWhere(), ['id' => $id]) : null;
        if (!$row) {
            $row = DB::row('SELECT ' . self::horseCols() . ' FROM ' . self::horseFrom() . ' WHERE ' . self::publicWhere() . ' ORDER BY titles DESC, h.is_favorite DESC, h.name_en LIMIT 1');
        }
        return $row;
    }

    /** Every homepage section after the hero, in the order offered in Website → Homepage & texts. */
    public const SECTIONS = ['horses', 'featured', 'foals', 'bloodlines', 'achievements', 'story', 'gallery', 'news', 'experts', 'contact'];

    /** Shown until the order is first saved (foals and bloodlines are off until switched on). */
    public const DEFAULT_SECTIONS = ['horses', 'featured', 'achievements', 'story', 'gallery', 'news', 'experts', 'contact'];

    /** The homepage sections switched on, in their order. */
    public static function homeSections(): array
    {
        $saved = array_map('trim', explode(',', (string) Settings::get('site.sections', implode(',', self::DEFAULT_SECTIONS))));
        return array_values(array_unique(array_intersect($saved, self::SECTIONS)));
    }

    /**
     * Homepage "Latest foals": public foals, youngest first. Only horses already on the website (a foal born at SK is
     * there only after the Owner approved it), so nothing here is new information — no pregnancies, plans or embryos.
     */
    public static function foals(int $limit = 6): array
    {
        return DB::all('SELECT ' . self::horseCols() . ', h.born_at_sk FROM ' . self::horseFrom() . " WHERE h.category = 'foal' AND " . self::publicWhere()
            . ' ORDER BY h.dob IS NULL, h.dob DESC, h.name_en LIMIT ' . (int) $limit);
    }

    /**
     * Homepage "Bloodlines": the bloodline field of public horses (the same text shown on their profiles), each with its
     * horses, largest line first: [['name' => …, 'horses' => [horse, …]], …]. Lines with no public horse do not exist here.
     */
    public static function bloodlines(int $lines = 4, int $perLine = 4): array
    {
        $rows = DB::all('SELECT ' . self::horseCols() . ' FROM ' . self::horseFrom() . " WHERE h.bloodline IS NOT NULL AND TRIM(h.bloodline) <> '' AND " . self::publicWhere()
            . ' ORDER BY ' . self::order());
        $by = [];
        foreach ($rows as $h) {
            $by[trim((string) $h['bloodline'])][] = $h;
        }
        uasort($by, fn ($a, $b) => count($b) <=> count($a));
        $out = [];
        foreach (array_slice($by, 0, $lines, true) as $name => $list) {
            $out[] = ['name' => (string) $name, 'count' => count($list), 'horses' => array_slice($list, 0, $perLine)];
        }
        return $out;
    }

    /** Hero background: photos of the featured horses first, then the others (up to $limit). */
    public static function heroPhotos(int $limit = 5): array
    {
        return array_slice(array_values(array_filter(DB::all('SELECT h.id, h.slug, h.category, h.name_en, h.name_ar, h.is_favorite, ' . self::PHOTO_COL . ' FROM horses h WHERE ' . self::publicWhere()
            . ' ORDER BY h.is_favorite DESC, ' . self::order()), fn ($h) => $h['main_photo_id'])), 0, $limit);
    }

    /** Other horses of the same category (for "More horses" on a profile). */
    public static function related(array $h, int $limit = 4): array
    {
        return DB::all('SELECT ' . self::horseCols() . ' FROM ' . self::horseFrom() . ' WHERE ' . self::publicWhere() . ' AND h.category = :cat AND h.id <> :id ORDER BY ' . self::order() . ' LIMIT ' . $limit,
            ['cat' => $h['category'], 'id' => $h['id']]);
    }

    /** The previous and next horse in the website order: [prev|null, next|null]. */
    public static function neighbours(int $horseId): array
    {
        $all = DB::all('SELECT h.id, h.name_en, h.name_ar, h.slug FROM horses h WHERE ' . self::publicWhere() . ' ORDER BY ' . self::order());
        $i = array_search($horseId, array_map('intval', array_column($all, 'id')), true);
        if ($i === false) {
            return [null, null];
        }
        return [$all[$i - 1] ?? null, $all[$i + 1] ?? null];
    }

    /**
     * Public show results within Website → Show results: titles and medals (no prize money). show_on_website: the
     * horse's page is on the website (sold, archived … horses keep their results, without a link).
     */
    public static function results(?int $horseId = null, ?int $limit = null): array
    {
        $params = [];
        $sql = 'SELECT r.id, r.class_name, r.placing, r.title_en, r.title_ar, r.medal, r.score, s.name_en AS show_en, s.name_ar AS show_ar, s.city, s.country,
                       c.name_en AS country_en, c.name_ar AS country_ar, ' . show_date_sql('s') . ' AS start_date, s.organizer,
                       h.name_en AS horse_en, h.name_ar AS horse_ar, h.slug, (' . self::publicWhere() . ') AS show_on_website, h.category, h.dob
                FROM show_results r JOIN shows s ON s.id = r.show_id JOIN horses h ON h.id = r.horse_id LEFT JOIN countries c ON c.id = s.country
                WHERE ' . self::resultFilter('r', 's') . ' AND h.deleted_at IS NULL';
        if ($horseId) {
            $sql .= ' AND r.horse_id = :hid';
            $params['hid'] = $horseId;
        }
        $sql .= " ORDER BY s.start_date DESC, FIELD(r.medal,'gold','silver','bronze','none'), r.placing";
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return DB::all($sql, $params);
    }

    /**
     * The horses with championship titles (medals) among $results, most decorated first (gold, then silver, bronze,
     * then results): name, link, category, medal counts, titles (gold first, newest first within a medal), results.
     */
    public static function champions(array $results): array
    {
        $rank = ['gold' => 0, 'silver' => 1, 'bronze' => 2];
        $by = [];
        foreach ($results as $r) {
            $k = $r['slug'];
            $by[$k] ??= ['horse_en' => $r['horse_en'], 'horse_ar' => $r['horse_ar'], 'slug' => $r['slug'], 'show_on_website' => $r['show_on_website'],
                'category' => $r['category'], 'dob' => $r['dob'], 'medals' => ['gold' => 0, 'silver' => 0, 'bronze' => 0], 'titles' => [], 'results' => 0];
            $by[$k]['results']++;
            if (isset($rank[$r['medal']])) {
                $by[$k]['medals'][$r['medal']]++;
                $by[$k]['titles'][] = $r;
            }
        }
        $out = array_values(array_filter($by, fn ($c) => $c['titles']));
        foreach ($out as &$c) {
            usort($c['titles'], fn ($a, $b) => $rank[$a['medal']] <=> $rank[$b['medal']]); // stable: newest first within a medal
        }
        unset($c);
        usort($out, fn ($a, $b) => [$b['medals']['gold'], $b['medals']['silver'], $b['medals']['bronze'], $b['results']]
            <=> [$a['medals']['gold'], $a['medals']['silver'], $a['medals']['bronze'], $a['results']]);
        return $out;
    }

    /** Medals among results: ['gold' => n, 'silver' => n, 'bronze' => n]. */
    public static function medalCounts(array $results): array
    {
        $n = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
        foreach ($results as $r) {
            if (isset($n[$r['medal']])) {
                $n[$r['medal']]++;
            }
        }
        return $n;
    }

    /**
     * What the website counts: horses shown and public championship titles (within the years of Website → Show
     * results). The number of shows attended is not shown on the website any more (asked on 8 Oct 2026).
     */
    public static function counters(): array
    {
        $from = self::resultsFromYear();
        return Cache::remember('site_counters_v4_' . $from, 600, fn () => [
            'horses' => (int) DB::value('SELECT COUNT(*) FROM horses h WHERE ' . self::publicWhere()),
            'titles' => (int) DB::value('SELECT COUNT(*) FROM show_results r JOIN shows s ON s.id = r.show_id JOIN horses h ON h.id = r.horse_id WHERE h.deleted_at IS NULL AND ' . self::resultFilter('r', 's', true)),
        ]);
    }

    /** Pedigree tree (generations deep); only names/slugs — public horses are linked. */
    public static function pedigree(?int $sireId, ?int $damId, int $generations): array
    {
        $cache = [];
        $load = function (?int $id) use (&$cache): ?array {
            if (!$id) {
                return null;
            }
            return $cache[$id] ??= DB::row('SELECT h.id, h.name_en, h.name_ar, h.slug, h.sire_id, h.dam_id, h.deleted_at, (' . self::publicWhere() . ') AS public FROM horses h WHERE h.id = ?', [$id]);
        };
        $build = function (?int $id, int $depth) use (&$build, $load, $generations): ?array {
            $h = $load($id);
            if (!$h || $h['deleted_at'] !== null) {
                return null;
            }
            $node = ['name_en' => $h['name_en'], 'name_ar' => $h['name_ar'], 'slug' => $h['public'] ? $h['slug'] : null];
            if ($depth < $generations) {
                $node['sire'] = $build($h['sire_id'] ? (int) $h['sire_id'] : null, $depth + 1);
                $node['dam'] = $build($h['dam_id'] ? (int) $h['dam_id'] : null, $depth + 1);
            }
            return $node;
        };
        return ['sire' => $build($sireId, 1), 'dam' => $build($damId, 1)];
    }

    public static function photos(int $horseId): array
    {
        return DB::all("SELECT id, title, width, height, mime FROM files WHERE owner_type = 'horse' AND owner_id = ? AND is_public = 1 AND deleted_at IS NULL AND category IN ('photo','video') ORDER BY (id = (SELECT main_photo_id FROM horses WHERE id = ?)) DESC, sort_order, id DESC", [$horseId, $horseId]);
    }

    public static function gallery(int $limit = 12): array
    {
        // the horse's link only while its page is on the website
        $items = DB::all('SELECT g.id, g.file_id, g.video_url, g.caption_en, g.caption_ar, IF(' . self::publicWhere() . ', h.slug, NULL) AS slug FROM gallery_items g LEFT JOIN horses h ON h.id = g.horse_id
            WHERE g.published = 1 AND g.deleted_at IS NULL ORDER BY g.sort, g.id DESC LIMIT ' . $limit);
        if (count($items) < $limit) {
            $more = DB::all("SELECT NULL AS id, f.id AS file_id, NULL AS video_url, h.name_en AS caption_en, h.name_ar AS caption_ar, h.slug
                FROM files f JOIN horses h ON h.id = f.owner_id WHERE f.owner_type = 'horse' AND f.category = 'photo' AND f.is_public = 1 AND f.deleted_at IS NULL
                AND " . self::publicWhere() . ' ORDER BY f.id DESC LIMIT ' . ($limit - count($items)));
            $items = array_merge($items, $more);
        }
        return $items;
    }

    public static function experts(): array
    {
        return DB::all("SELECT e.id, e.name_en, e.name_ar, e.photo_id, e.public_title_en, e.public_title_ar, e.public_bio_en, e.public_bio_ar, e.specialties_en, e.specialties_ar,
                p.value_en AS position_en, p.value_ar AS position_ar
            FROM employees e LEFT JOIN lookups p ON p.id = e.position_id
            WHERE e.show_on_website = 1 AND e.deleted_at IS NULL AND e.status <> 'left' ORDER BY e.website_sort, e.name_en LIMIT 8");
    }

    public static function news(?int $limit = null): array
    {
        return DB::all('SELECT id, slug, title_en, title_ar, summary_en, summary_ar, image_id, published_at FROM news
            WHERE published = 1 AND deleted_at IS NULL AND published_at <= NOW() ORDER BY published_at DESC' . ($limit ? ' LIMIT ' . (int) $limit : ''));
    }

    /** URL of a public image, or the placeholder. */
    public static function img(?int $fileId, string $size = 'full'): string
    {
        return $fileId ? url('/media/' . $fileId . '/' . ($size === 'thumb' ? 'thumb' : 'full')) : asset('img/horse-placeholder.svg');
    }

    /** Two letters for a horse without a photo yet ("D SAMED" → "DS"). */
    public static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-]+/u', preg_replace("/[^\\p{L}\\s\\-]/u", '', $name)) ?: []));
        $out = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $out ?: '·';
    }

    /** Which website menu pages are switched on (Home and Horses always are). */
    public static function pageOn(string $page): bool
    {
        if ($page === 'news' && !self::hasNews()) {
            return false; // News & Events appears (menu, footer, sitemap) once a news item is published
        }
        return in_array($page, ['horses'], true) || (string) Settings::get('site.page_' . $page, '1') === '1';
    }

    /** At least one news item is out (published, and its publish date reached). */
    public static function hasNews(): bool
    {
        return (bool) DB::value('SELECT 1 FROM news WHERE published = 1 AND deleted_at IS NULL AND published_at <= NOW() LIMIT 1');
    }

    /** A "what visitors see on a horse profile" switch (Website → Homepage & texts). */
    public static function show(string $what): bool
    {
        return (string) Settings::get('site.' . $what, '1') === '1';
    }
}
