<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Public website data. Everything comes live from the Horses module (only records marked "show on website").
 * Never returns prices, owner details or medical records.
 */
final class SiteData
{
    private const HORSE_COLS = 'h.id, h.name_en, h.name_ar, h.slug, h.sex, h.category, h.dob, h.bloodline, h.main_photo_id, h.breeding_stallion,
        h.story_en, h.story_ar, h.video_url, h.born_at_sk, h.sire_id, h.dam_id, h.is_favorite,
        c.value_en AS color_en, c.value_ar AS color_ar, b.value_en AS breed_en, b.value_ar AS breed_ar,
        s.name_en AS sire_en, s.name_ar AS sire_ar, s.slug AS sire_slug, s.show_on_website AS sire_public,
        d.name_en AS dam_en, d.name_ar AS dam_ar, d.slug AS dam_slug, d.show_on_website AS dam_public';

    private static function horseFrom(): string
    {
        return 'horses h LEFT JOIN lookups c ON c.id = h.color_id LEFT JOIN lookups b ON b.id = h.breed_id
                LEFT JOIN horses s ON s.id = h.sire_id LEFT JOIN horses d ON d.id = h.dam_id';
    }

    private static function publicWhere(): string
    {
        return "h.show_on_website = 1 AND h.deleted_at IS NULL AND h.archived_at IS NULL AND h.status IN ('active','in_shelter')";
    }

    public static function horses(array $filters = [], ?int $limit = null): array
    {
        $where = [self::publicWhere()];
        $params = [];
        if (!empty($filters['category']) && in_array($filters['category'], ['foal', 'colt', 'filly', 'stallion', 'mare', 'gelding'], true)) {
            $where[] = 'h.category = :cat';
            $params['cat'] = $filters['category'];
        }
        if (!empty($filters['sex']) && in_array($filters['sex'], ['male', 'female', 'gelding'], true)) {
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
        if (!empty($filters['breeding_stallion'])) {
            $where[] = "h.breeding_stallion = 1 AND h.sex = 'male'";
        }
        $sql = 'SELECT ' . self::HORSE_COLS . ' FROM ' . self::horseFrom() . ' WHERE ' . implode(' AND ', $where)
             . " ORDER BY h.is_favorite DESC, FIELD(h.category,'stallion','mare','colt','filly','foal','gelding'), h.name_en";
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return DB::all($sql, $params);
    }

    public static function horse(string $slug): ?array
    {
        return DB::row('SELECT ' . self::HORSE_COLS . ', h.registration_no, h.breeder FROM ' . self::horseFrom() . ' WHERE h.slug = :slug AND ' . self::publicWhere(), ['slug' => $slug]);
    }

    public static function featured(): ?array
    {
        $id = (int) Settings::get('site.featured_horse_id', 0);
        $row = $id ? DB::row('SELECT ' . self::HORSE_COLS . ' FROM ' . self::horseFrom() . ' WHERE h.id = :id AND ' . self::publicWhere(), ['id' => $id]) : null;
        if (!$row) {
            $row = DB::row('SELECT ' . self::HORSE_COLS . ', (SELECT COUNT(*) FROM show_results r WHERE r.horse_id = h.id AND r.deleted_at IS NULL AND r.medal <> \'none\') AS medals FROM '
                . self::horseFrom() . ' WHERE ' . self::publicWhere() . ' ORDER BY medals DESC, h.is_favorite DESC LIMIT 1');
        }
        return $row;
    }

    /** Public show results: titles and medals (no prize money). */
    public static function results(?int $horseId = null, ?int $limit = null): array
    {
        $params = [];
        $sql = 'SELECT r.id, r.class_name, r.placing, r.title_en, r.title_ar, r.medal, s.name_en AS show_en, s.name_ar AS show_ar, s.city, s.country,
                       s.start_date, s.organizer, h.name_en AS horse_en, h.name_ar AS horse_ar, h.slug, h.show_on_website
                FROM show_results r JOIN shows s ON s.id = r.show_id JOIN horses h ON h.id = r.horse_id
                WHERE r.deleted_at IS NULL AND s.deleted_at IS NULL AND r.is_public = 1 AND h.deleted_at IS NULL';
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

    public static function counters(): array
    {
        return Cache::remember('site.counters', 600, fn () => [
            'horses' => (int) DB::value("SELECT COUNT(*) FROM horses WHERE deleted_at IS NULL AND is_external = 0 AND owner_type = 'sk' AND status IN ('active','in_shelter')"),
            'titles' => (int) DB::value("SELECT COUNT(*) FROM show_results r JOIN shows s ON s.id = r.show_id WHERE r.deleted_at IS NULL AND s.deleted_at IS NULL AND r.is_public = 1 AND (r.medal <> 'none' OR (r.title_en IS NOT NULL AND r.title_en <> ''))"),
            'shows'  => (int) DB::value('SELECT COUNT(DISTINCT r.show_id) FROM show_results r JOIN shows s ON s.id = r.show_id WHERE r.deleted_at IS NULL AND s.deleted_at IS NULL'),
            'foals'  => (int) DB::value('SELECT COUNT(*) FROM horses WHERE deleted_at IS NULL AND born_at_sk = 1'),
        ]);
    }

    public static function latestFoals(int $limit = 6): array
    {
        return DB::all('SELECT ' . self::HORSE_COLS . ' FROM ' . self::horseFrom() . ' WHERE ' . self::publicWhere()
            . ' AND h.born_at_sk = 1 AND h.website_approved_at IS NOT NULL ORDER BY h.dob DESC LIMIT ' . $limit);
    }

    /** Main sires (at stud or most offspring) and dam lines (mares with most offspring). */
    public static function bloodlines(): array
    {
        $sires = DB::all('SELECT ' . self::HORSE_COLS . ', (SELECT COUNT(*) FROM horses o WHERE o.sire_id = h.id AND o.deleted_at IS NULL) AS offspring FROM '
            . self::horseFrom() . " WHERE h.sex = 'male' AND (h.category = 'stallion' OR h.breeding_stallion = 1) AND h.show_on_website = 1 AND h.deleted_at IS NULL ORDER BY h.breeding_stallion DESC, offspring DESC LIMIT 4");
        $dams = DB::all('SELECT ' . self::HORSE_COLS . ', (SELECT COUNT(*) FROM horses o WHERE o.dam_id = h.id AND o.deleted_at IS NULL) AS offspring FROM '
            . self::horseFrom() . " WHERE h.sex = 'female' AND h.category = 'mare' AND h.show_on_website = 1 AND h.deleted_at IS NULL HAVING offspring > 0 ORDER BY offspring DESC, h.is_favorite DESC LIMIT 4");
        return [$sires, $dams];
    }

    /** Pedigree tree (generations deep); only names/slugs — public horses are linked. */
    public static function pedigree(?int $sireId, ?int $damId, int $generations): array
    {
        $cache = [];
        $load = function (?int $id) use (&$cache): ?array {
            if (!$id) {
                return null;
            }
            return $cache[$id] ??= DB::row('SELECT id, name_en, name_ar, slug, sire_id, dam_id, show_on_website, deleted_at FROM horses WHERE id = ?', [$id]);
        };
        $build = function (?int $id, int $depth) use (&$build, $load, $generations): ?array {
            $h = $load($id);
            if (!$h || $h['deleted_at'] !== null) {
                return null;
            }
            $node = ['name_en' => $h['name_en'], 'name_ar' => $h['name_ar'], 'slug' => $h['show_on_website'] ? $h['slug'] : null];
            if ($depth < $generations) {
                $node['sire'] = $build($h['sire_id'] ? (int) $h['sire_id'] : null, $depth + 1);
                $node['dam'] = $build($h['dam_id'] ? (int) $h['dam_id'] : null, $depth + 1);
            }
            return $node;
        };
        return ['sire' => $build($sireId, 1), 'dam' => $build($damId, 1)];
    }

    public static function offspring(int $horseId): array
    {
        return DB::all('SELECT h.name_en, h.name_ar, h.slug, h.dob, h.sex, h.category, h.main_photo_id FROM horses h WHERE (h.sire_id = ? OR h.dam_id = ?) AND ' . self::publicWhere() . ' ORDER BY h.dob DESC', [$horseId, $horseId]);
    }

    public static function photos(int $horseId): array
    {
        return DB::all("SELECT id, title, width, height, mime FROM files WHERE owner_type = 'horse' AND owner_id = ? AND is_public = 1 AND deleted_at IS NULL AND category IN ('photo','video') ORDER BY (id = (SELECT main_photo_id FROM horses WHERE id = ?)) DESC, sort_order, id DESC", [$horseId, $horseId]);
    }

    /** Our breeding programme: stallions we breed with, broodmares, and how many foals each has produced. */
    public static function programme(): array
    {
        $sires = DB::all('SELECT ' . self::HORSE_COLS . ', (SELECT COUNT(*) FROM horses o WHERE o.sire_id = h.id AND o.deleted_at IS NULL) AS offspring FROM '
            . self::horseFrom() . " WHERE h.sex = 'male' AND " . self::publicWhere() . ' AND (h.breeding_stallion = 1 OR EXISTS (SELECT 1 FROM horses o WHERE o.sire_id = h.id AND o.born_at_sk = 1 AND o.deleted_at IS NULL)) ORDER BY offspring DESC, h.name_en');
        $mares = DB::all('SELECT ' . self::HORSE_COLS . ', (SELECT COUNT(*) FROM horses o WHERE o.dam_id = h.id AND o.deleted_at IS NULL) AS offspring FROM '
            . self::horseFrom() . " WHERE h.sex = 'female' AND h.category = 'mare' AND " . self::publicWhere() . ' ORDER BY offspring DESC, h.is_favorite DESC, h.name_en');
        return [$sires, $mares];
    }

    public static function gallery(int $limit = 12): array
    {
        $items = DB::all('SELECT g.id, g.file_id, g.video_url, g.caption_en, g.caption_ar, h.slug FROM gallery_items g LEFT JOIN horses h ON h.id = g.horse_id
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

    public static function bloodlineOptions(): array
    {
        return DB::column("SELECT DISTINCT h.bloodline FROM horses h WHERE " . self::publicWhere() . " AND h.bloodline IS NOT NULL AND h.bloodline <> '' ORDER BY h.bloodline");
    }

    /** URL of a public image, or the placeholder. */
    public static function img(?int $fileId, string $size = 'full'): string
    {
        return $fileId ? url('/media/' . $fileId . '/' . ($size === 'thumb' ? 'thumb' : 'full')) : asset('img/horse-placeholder.svg');
    }
}
