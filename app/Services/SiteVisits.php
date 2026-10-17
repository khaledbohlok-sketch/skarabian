<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;

/**
 * Website visitor numbers without cookies or kept addresses (Website → Visitors and the dashboard). Every page a visitor
 * opens on the public website is one row: the page (and horse), language, phone or computer, where the visit came from
 * (Google, Instagram, WhatsApp …), the country, and a visitor code made from the address and the browser with a key
 * that is replaced every day — so a visitor counts once a day but cannot be followed from day to day or traced back.
 * Not counted: staff who are logged in, search engines, link previews and prefetches. Needs database update 025.
 */
final class SiteVisits
{
    /** The website's pages, in menu order. */
    public const PAGES = ['home', 'horses', 'horse', 'champions', 'news', 'news_item', 'about', 'contact'];

    /** Robots, link previews and tools: a real browser never says these, nor gives a web address ("+http://…/bot.html"). */
    private const BOTS = '/https?:\/\/|(?<!cu)bot\b|bot\/|crawl|spider|slurp|mediapartners|preview|whatsapp|facebookexternalhit|facebookcatalog|telegram|'
        . 'discord|slack|skype|headless|lighthouse|pagespeed|gtmetrix|python|curl|wget|java\/|okhttp|go-http|httpclient|axios|node-fetch|guzzle|'
        . 'monitor|uptime|pingdom|scanner|feedfetcher|validator|phantom|selenium|puppeteer|playwright/i';

    /** Where a visit came from, by the address of the page before it (checked in this order). */
    private const REFERRERS = [
        'email'     => ['mail.', 'outlook.', 'webmail', 'com.google.android.gm'],
        'google'    => ['google.', 'com.google.android.googlequicksearchbox'],
        'bing'      => ['bing.com'],
        'instagram' => ['instagram.com'],
        'facebook'  => ['facebook.com', 'fb.com', 'fb.me', 'messenger.com'],
        'whatsapp'  => ['whatsapp.', 'com.whatsapp', 'wa.me'],
        'x'         => ['t.co', 'twitter.com', 'x.com'],
        'youtube'   => ['youtube.com', 'youtu.be'],
        'tiktok'    => ['tiktok.com'],
        'snapchat'  => ['snapchat.com'],
        'linkedin'  => ['linkedin.com', 'lnkd.in'],
        'search'    => ['yahoo.', 'duckduckgo.', 'yandex.', 'baidu.', 'ecosia.', 'qwant.', 'naver.'],
    ];

    /** Whether visits are counted: database update 025 has been applied. */
    public static function on(): bool
    {
        return DB::hasColumn('site_visits', 'visitor');
    }

    /** Counts this page view (called by the website's page controller just before the page is sent). */
    public static function record(string $page, ?int $horseId = null): void
    {
        if (!in_array($page, self::PAGES, true) || Request::method() !== 'GET' || Session::get('uid') || !self::on()) {
            return;
        }
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
        $purpose = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_X_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_X_MOZ'] ?? ''));
        if (!self::isBrowser($ua) || str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
            return;
        }
        try {
            $ip = Request::ip();
            DB::insert('site_visits', [
                'visited_at' => date('Y-m-d H:i:s'), 'visit_day' => date('Y-m-d'), 'page' => $page, 'horse_id' => $horseId,
                'lang' => lang() === 'ar' ? 'ar' : 'en', 'device' => self::device($ua),
                'source' => self::source((string) ($_SERVER['HTTP_REFERER'] ?? ''), $ua),
                'country' => Geo::country($ip), 'visitor' => self::visitor($ip, $ua),
            ]);
        } catch (\Throwable $e) {
            ErrorHandler::log('Website visit not counted: ' . $e->getMessage()); // the page itself is still shown
        }
    }

    /** A person's browser (not a robot, a link preview or a tool). */
    public static function isBrowser(string $ua): bool
    {
        return $ua !== '' && str_starts_with($ua, 'Mozilla/') && !preg_match(self::BOTS, $ua);
    }

    public static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Kindle|Silk\/|Android(?!.*Mobile)/i', $ua)) {
            return 'tablet';
        }
        return preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua) ? 'phone' : 'computer';
    }

    /**
     * Where the visit came from: "site" for the next pages of a visit, the app for links opened inside Instagram,
     * Facebook, Snapchat or TikTok (their browsers send no address), the site of the page before (an Android app
     * sends its own name: "android-app://com.whatsapp/"), or "direct" (typed, a bookmark, or a link from WhatsApp or
     * another app that sends no address).
     */
    public static function source(string $referrer, string $ua = ''): string
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        $own = strtolower((string) parse_url(absolute_url('/'), PHP_URL_HOST));
        if ($host !== '' && ($host === $own || $host === 'www.' . $own || strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) === $host)) {
            return 'site';
        }
        foreach (['instagram' => 'Instagram', 'facebook' => 'FBAN|FBAV|FB_IAB', 'snapchat' => 'Snapchat', 'tiktok' => 'TikTok|musical_ly|BytedanceWebview'] as $app => $sign) {
            if (preg_match('/' . $sign . '/', $ua)) {
                return $app;
            }
        }
        if ($host === '') {
            return 'direct';
        }
        foreach (self::REFERRERS as $src => $needles) {
            foreach ($needles as $n) {
                if (str_starts_with($host, $n) || str_contains($host, '.' . $n)) {
                    return $src;
                }
            }
        }
        return 'other';
    }

    /** The visitor's code for today; the day's key is replaced at the first visit of each day (yesterday's is gone). */
    private static function visitor(string $ip, string $ua): string
    {
        $day = date('Y-m-d');
        $salt = (string) Settings::get('stats.salt', '');
        if ($salt === '' || (string) Settings::get('stats.salt_day', '') !== $day) {
            $salt = bin2hex(random_bytes(16));
            Settings::set('stats.salt', $salt);
            Settings::set('stats.salt_day', $day);
        }
        return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
    }

    // ---- Numbers for Website → Visitors and the dashboard. A "visit" = one visitor on one day.

    /** The first day of the last $days days (today included). */
    public static function since(int $days, int $back = 0): string
    {
        return date('Y-m-d', strtotime('-' . ($days * ($back + 1) - 1) . ' days'));
    }

    /** Visits and page views of the last $days days and of the $days before them, and the website messages. */
    public static function totals(int $days): array
    {
        $from = self::since($days);
        $prev = self::since($days, 1);
        $now = DB::row('SELECT COUNT(*) AS views, COUNT(DISTINCT visit_day, visitor) AS visits FROM site_visits WHERE visit_day >= ?', [$from]);
        $before = DB::row('SELECT COUNT(*) AS views, COUNT(DISTINCT visit_day, visitor) AS visits FROM site_visits WHERE visit_day >= ? AND visit_day < ?', [$prev, $from]);
        return [
            'visits' => (int) $now['visits'], 'views' => (int) $now['views'],
            'prev_visits' => (int) $before['visits'], 'prev_views' => (int) $before['views'],
            'inquiries' => (int) DB::value('SELECT COUNT(*) FROM inquiries WHERE deleted_at IS NULL AND created_at >= ?', [$from . ' 00:00:00']),
        ];
    }

    /** Visits per day, oldest first, days without a visit included: ['Y-m-d' => n]. */
    public static function daily(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0;
        }
        foreach (DB::all('SELECT visit_day, COUNT(DISTINCT visitor) AS n FROM site_visits WHERE visit_day >= ? GROUP BY visit_day', [self::since($days)]) as $r) {
            $out[(string) $r['visit_day']] = (int) $r['n'];
        }
        return $out;
    }

    /** The most viewed horses: id, names, slug, visits, views. */
    public static function horses(int $days, int $limit = 10): array
    {
        return DB::all('SELECT h.id, h.name_en, h.name_ar, h.slug, h.show_on_website, t.visits, t.views
            FROM (SELECT horse_id, COUNT(DISTINCT visit_day, visitor) AS visits, COUNT(*) AS views FROM site_visits
                  WHERE page = \'horse\' AND horse_id IS NOT NULL AND visit_day >= ? GROUP BY horse_id) t
            JOIN horses h ON h.id = t.horse_id
            ORDER BY t.visits DESC, t.views DESC, h.name_en LIMIT ' . max(1, $limit), [self::since($days)]);
    }

    /** Visits and views per page, most viewed first. */
    public static function pages(int $days): array
    {
        return DB::all('SELECT page, COUNT(DISTINCT visit_day, visitor) AS visits, COUNT(*) AS views FROM site_visits WHERE visit_day >= ?
            GROUP BY page ORDER BY views DESC, page', [self::since($days)]);
    }

    /** Visits by where they came from (the first page of a visit), most first. */
    public static function sources(int $days): array
    {
        return DB::all("SELECT source, COUNT(DISTINCT visit_day, visitor) AS visits FROM site_visits WHERE visit_day >= ? AND source <> 'site'
            GROUP BY source ORDER BY visits DESC, source", [self::since($days)]);
    }

    /** Visits by country (code, names), most first; a visit from an unknown place has no code. */
    public static function countries(int $days, int $limit = 10): array
    {
        return DB::all('SELECT t.country, c.name_en, c.name_ar, t.visits
            FROM (SELECT country, COUNT(DISTINCT visit_day, visitor) AS visits FROM site_visits WHERE visit_day >= ? GROUP BY country) t
            LEFT JOIN countries c ON c.id = t.country ORDER BY t.visits DESC, t.country LIMIT ' . max(1, $limit), [self::since($days)]);
    }

    /** Visits by a column with few values (device or lang): [value => visits]. */
    public static function split(string $col, int $days): array
    {
        if (!in_array($col, ['device', 'lang'], true)) {
            throw new \InvalidArgumentException($col);
        }
        return DB::pairs("SELECT $col, COUNT(DISTINCT visit_day, visitor) AS visits FROM site_visits WHERE visit_day >= ? GROUP BY $col ORDER BY visits DESC", [self::since($days)]);
    }

    /** Removes visits older than 400 days (nightly clean-up); the number removed. */
    public static function purge(): int
    {
        return self::on() ? DB::run('DELETE FROM site_visits WHERE visit_day < ?', [date('Y-m-d', strtotime('-400 days'))])->rowCount() : 0;
    }
}
