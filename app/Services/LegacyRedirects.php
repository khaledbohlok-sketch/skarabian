<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Addresses search engines, other sites and old bookmarks still use (the previous website, short or common names) are
 * sent on to the page that took their place with a permanent redirect (301), so they stop showing as "Not found (404)"
 * in Search Console. Only asked when no route matched, so it can never hide a real page. Unknown addresses stay 404.
 */
final class LegacyRedirects
{
    /** first path segment (after an optional /en or /ar) => where it lives now ('' = the homepage) */
    private const PAGES = [
        'home' => '', 'index' => '', 'index.php' => '', 'index.html' => '', 'index.htm' => '', 'home.php' => '', 'default.aspx' => '',
        'horses' => 'horses', 'horse' => 'horses', 'our-horses' => 'horses', 'ourhorses' => 'horses', 'collection' => 'horses', 'horses.php' => 'horses',
        'breeding' => 'horses', 'for-sale' => 'horses', 'sale' => 'horses',
        'about' => 'about', 'about-us' => 'about', 'aboutus' => 'about', 'about.php' => 'about', 'our-story' => 'about', 'story' => 'about', 'team' => 'about', 'our-team' => 'about',
        'contact' => 'contact', 'contact-us' => 'contact', 'contactus' => 'contact', 'contact.php' => 'contact', 'visit' => 'contact', 'location' => 'contact', 'enquire' => 'contact', 'inquiry' => 'contact',
        'news' => 'news', 'events' => 'news', 'news-events' => 'news', 'news-and-events' => 'news', 'blog' => 'news', 'news.php' => 'news',
        'champions' => 'champions', 'achievements' => 'champions', 'results' => 'champions', 'awards' => 'champions', 'shows' => 'champions',
        'gallery' => '#gallery', 'photos' => '#gallery', 'media' => '#gallery', 'videos' => '#gallery',
    ];

    /** a category name in the address => the horses page opened on that tab */
    private const CATEGORIES = ['stallions' => 'stallion', 'stallion' => 'stallion', 'mares' => 'mare', 'mare' => 'mare', 'fillies' => 'filly', 'filly' => 'filly',
        'colts' => 'colt', 'colt' => 'colt', 'foals' => 'foal', 'foal' => 'foal'];

    /** The new address for $path (a path like "/about-us" or "/en/horse/d-majd"), or null when there is none. */
    public static function find(string $path): ?string
    {
        $parts = array_values(array_filter(explode('/', strtolower(trim($path, '/'))), 'strlen'));
        if (!$parts) {
            return null;
        }
        $lang = in_array($parts[0], ['en', 'ar'], true) ? array_shift($parts) : self::visitorLang();
        if (!$parts) {
            return null;
        }
        $first = $parts[0];
        // a horse or a news item under its old or singular folder: /horse/d-majd, /horses/d-majd (no language), /news/x
        if (count($parts) === 2 && in_array($first, ['horse', 'horses', 'our-horses'], true)) {
            return '/' . $lang . '/horses/' . rawurlencode($parts[1]);
        }
        if (count($parts) === 2 && in_array($first, ['news', 'events', 'blog'], true)) {
            return '/' . $lang . '/news/' . rawurlencode($parts[1]);
        }
        if (count($parts) === 1 && isset(self::CATEGORIES[$first])) {
            return '/' . $lang . '/horses?category=' . self::CATEGORIES[$first];
        }
        if (count($parts) === 1 && array_key_exists($first, self::PAGES)) {
            $to = self::PAGES[$first];
            return '/' . $lang . ($to === '' ? '' : ($to[0] === '#' ? $to : '/' . $to));
        }
        return null;
    }

    /** No language in the address: the visitor's last choice (cookie), else the browser's, else English (search engines). */
    private static function visitorLang(): string
    {
        $c = $_COOKIE['lang'] ?? null;
        if (in_array($c, ['en', 'ar'], true)) {
            return $c;
        }
        return substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2) === 'ar' ? 'ar' : 'en';
    }
}
