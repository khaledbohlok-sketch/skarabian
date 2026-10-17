<?php
declare(strict_types=1);

namespace App\Services;

/**
 * The preview's SiteData: the same public methods as app/Services/SiteData.php, answered from tools/preview/fixtures.php
 * instead of the database. Pure helpers (grouped, champions, medalCounts, resultText, initials) are copied as they are.
 */
final class SiteData
{
    public const MEDALS = ['gold', 'silver', 'bronze'];
    public const CATEGORIES = ['stallion', 'mare', 'filly', 'colt', 'foal'];
    public const SECTIONS = ['horses', 'featured', 'foals', 'bloodlines', 'achievements', 'story', 'gallery', 'news', 'experts', 'contact'];
    public const DEFAULT_SECTIONS = ['horses', 'featured', 'achievements', 'story', 'gallery', 'news', 'experts', 'contact'];

    private static ?array $fx = null;

    public static function fixtures(): array
    {
        if (self::$fx === null) {
            self::$fx = require dirname(__DIR__) . '/fixtures.php';
            // titles: public medals of each horse within the website's years
            foreach (self::$fx['horses'] as $id => &$h) {
                $h['titles'] = count(array_filter(self::$fx['results'], fn ($r) => $r['horse_id'] === $id && in_array($r['medal'], self::MEDALS, true) && self::inYears($r)));
            }
            unset($h);
        }
        return self::$fx;
    }

    private static function inYears(array $r): bool
    {
        $y = self::resultsFromYear();
        return !$y || (int) substr((string) $r['start_date'], 0, 4) >= $y;
    }

    public static function resultsFromYear(): int
    {
        return max(0, (int) Settings::get('site.results_from_year', '2025'));
    }

    public static function medalsOnly(): bool
    {
        return (string) Settings::get('site.results_medals_only', '1') === '1';
    }

    public static function showClass(): bool
    {
        return (string) Settings::get('site.results_show_class', '0') === '1';
    }

    public static function resultText(array $r): string
    {
        $medal = in_array($r['medal'] ?? '', self::MEDALS, true) ? __('shows.medal_' . $r['medal']) : '';
        return self::showClass() ? (string) (loc($r, 'title') ?: ($r['class_name'] ?? '') ?: $medal) : $medal;
    }

    private static function sorted(array $rows): array
    {
        $cat = array_flip(self::CATEGORIES);
        usort($rows, fn ($a, $b) => [$cat[$a['category']], -(int) $a['is_favorite'], $a['name_en']] <=> [$cat[$b['category']], -(int) $b['is_favorite'], $b['name_en']]);
        return $rows;
    }

    public static function horses(array $filters = [], ?int $limit = null): array
    {
        $rows = array_values(self::fixtures()['horses']);
        if (!empty($filters['category'])) {
            $rows = array_values(array_filter($rows, fn ($h) => $h['category'] === $filters['category']));
        }
        $rows = self::sorted($rows);
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

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
        foreach (self::fixtures()['horses'] as $h) {
            if (strcasecmp($h['slug'], $slug) === 0) {
                return $h;
            }
        }
        return null;
    }

    public static function featured(): ?array
    {
        $id = (int) Settings::get('site.featured_horse_id', 0);
        $all = self::fixtures()['horses'];
        if ($id && isset($all[$id])) {
            return $all[$id];
        }
        $rows = array_values($all);
        usort($rows, fn ($a, $b) => [-(int) $a['titles'], -(int) $a['is_favorite'], $a['name_en']] <=> [-(int) $b['titles'], -(int) $b['is_favorite'], $b['name_en']]);
        return $rows[0] ?? null;
    }

    public static function homeSections(): array
    {
        $saved = array_map('trim', explode(',', (string) Settings::get('site.sections', implode(',', self::DEFAULT_SECTIONS))));
        return array_values(array_unique(array_intersect($saved, self::SECTIONS)));
    }

    public static function foals(int $limit = 6): array
    {
        $rows = array_values(array_filter(self::fixtures()['horses'], fn ($h) => $h['category'] === 'foal'));
        usort($rows, fn ($a, $b) => [$a['dob'] === null, $b['dob'] ?? '', $a['name_en']] <=> [$b['dob'] === null, $a['dob'] ?? '', $b['name_en']]);
        return array_slice($rows, 0, $limit);
    }

    public static function bloodlines(int $lines = 4, int $perLine = 4): array
    {
        $by = [];
        foreach (self::sorted(array_values(self::fixtures()['horses'])) as $h) {
            if (trim((string) $h['bloodline']) !== '') {
                $by[trim((string) $h['bloodline'])][] = $h;
            }
        }
        uasort($by, fn ($a, $b) => count($b) <=> count($a));
        $out = [];
        foreach (array_slice($by, 0, $lines, true) as $name => $list) {
            $out[] = ['name' => (string) $name, 'count' => count($list), 'horses' => array_slice($list, 0, $perLine)];
        }
        return $out;
    }

    public static function heroPhotos(int $limit = 5): array
    {
        $rows = array_values(array_filter(self::fixtures()['horses'], fn ($h) => $h['main_photo_id']));
        usort($rows, fn ($a, $b) => [-(int) $a['is_favorite'], array_search($a['category'], self::CATEGORIES, true), $a['name_en']] <=> [-(int) $b['is_favorite'], array_search($b['category'], self::CATEGORIES, true), $b['name_en']]);
        return array_slice($rows, 0, $limit);
    }

    public static function related(array $h, int $limit = 4): array
    {
        $rows = array_values(array_filter(self::horses(), fn ($o) => $o['category'] === $h['category'] && (int) $o['id'] !== (int) $h['id']));
        return array_slice($rows, 0, $limit);
    }

    public static function neighbours(int $horseId): array
    {
        $all = self::horses();
        $i = array_search($horseId, array_map(fn ($h) => (int) $h['id'], $all), true);
        if ($i === false) {
            return [null, null];
        }
        return [$all[$i - 1] ?? null, $all[$i + 1] ?? null];
    }

    public static function results(?int $horseId = null, ?int $limit = null): array
    {
        $rows = array_values(array_filter(self::fixtures()['results'], fn ($r) => self::inYears($r) && (!self::medalsOnly() || in_array($r['medal'], self::MEDALS, true)) && (!$horseId || $r['horse_id'] === $horseId)));
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

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
            usort($c['titles'], fn ($a, $b) => $rank[$a['medal']] <=> $rank[$b['medal']]);
        }
        unset($c);
        usort($out, fn ($a, $b) => [$b['medals']['gold'], $b['medals']['silver'], $b['medals']['bronze'], $b['results']]
            <=> [$a['medals']['gold'], $a['medals']['silver'], $a['medals']['bronze'], $a['results']]);
        return $out;
    }

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

    public static function counters(): array
    {
        return ['horses' => count(self::fixtures()['horses']), 'titles' => count(array_filter(self::fixtures()['results'], fn ($r) => in_array($r['medal'], self::MEDALS, true) && self::inYears($r)))];
    }

    public static function pedigree(?int $sireId, ?int $damId, int $generations): array
    {
        $nodes = self::fixtures()['nodes'];
        $build = function (?int $id, int $depth) use (&$build, $nodes, $generations): ?array {
            $h = $id ? ($nodes[$id] ?? null) : null;
            if (!$h) {
                return null;
            }
            $node = ['name_en' => $h['name_en'], 'name_ar' => $h['name_ar'], 'slug' => $h['slug']];
            if ($depth < $generations) {
                $node['sire'] = $build($h['sire_id'], $depth + 1);
                $node['dam'] = $build($h['dam_id'], $depth + 1);
            }
            return $node;
        };
        return ['sire' => $build($sireId, 1), 'dam' => $build($damId, 1)];
    }

    public static function photos(int $horseId): array
    {
        $h = self::fixtures()['horses'][$horseId] ?? null;
        $files = self::fixtures()['files'];
        return $h ? array_values(array_map(fn ($id) => $files[$id], $h['photos'])) : [];
    }

    public static function gallery(int $limit = 12): array
    {
        return array_slice(self::fixtures()['gallery'], 0, $limit);
    }

    public static function experts(): array
    {
        return self::fixtures()['experts'];
    }

    public static function news(?int $limit = null): array
    {
        $rows = self::fixtures()['news'];
        usort($rows, fn ($a, $b) => strcmp($b['published_at'], $a['published_at']));
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    /** A news item by slug (the controller reads the news table directly; the preview router patches that call). */
    public static function newsItem(string $slug): ?array
    {
        foreach (self::fixtures()['news'] as $n) {
            if ($n['slug'] === $slug) {
                return $n;
            }
        }
        return null;
    }

    public static function img(?int $fileId, string $size = 'full'): string
    {
        $f = $fileId ? (self::fixtures()['files'][$fileId] ?? null) : null;
        return $f ? url('/preview-media/' . $f['name'] . ($size === 'thumb' ? '-thumb' : '') . '.jpg') : asset('img/horse-placeholder.svg');
    }

    public static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-]+/u', preg_replace("/[^\\p{L}\\s\\-]/u", '', $name)) ?: []));
        $out = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $out ?: '·';
    }

    public static function pageOn(string $page): bool
    {
        if ($page === 'news' && !self::hasNews()) {
            return false;
        }
        return in_array($page, ['horses'], true) || (string) Settings::get('site.page_' . $page, '1') === '1';
    }

    public static function hasNews(): bool
    {
        return (bool) self::fixtures()['news'];
    }

    public static function show(string $what): bool
    {
        return (string) Settings::get('site.' . $what, '1') === '1';
    }
}
