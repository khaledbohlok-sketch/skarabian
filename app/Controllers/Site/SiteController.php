<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FileStore;
use App\Services\HorseService;
use App\Services\Notifier;
use App\Services\SiteData;
use App\Services\SiteVisits;

/** Public website. Horses lead every page; all horse content comes from the Horses module. */
class SiteController extends Controller
{
    private function page(string $view, array $data = []): never
    {
        // "private": pages carry the visitor's own form token and messages, so a shared cache (CDN/proxy) must
        // never hand one visitor's copy to another; a page showing a message is not cached at all. "no-cache":
        // the browser asks again each time, so a visit always reaches the server, which remembers the language
        // in the lang cookie (a copy served from the browser cache left the cookie on the previous language).
        // "no-transform": the hosting's servers must not change the page (GoDaddy adds its own tracking scripts).
        header('Cache-Control: ' . (empty($_SESSION['_flash']) && empty($_SESSION['_old_current']) ? 'private, no-cache' : 'no-store') . ', no-transform');
        SiteVisits::record($view, $view === 'horse' ? (int) $data['h']['id'] : null); // Website → Visitors (no cookies)
        $this->view('site/' . $view, $data, 'site');
    }

    public function root(): void
    {
        $pref = substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2) === 'ar' ? 'ar' : 'en';
        $lang = in_array($_COOKIE['lang'] ?? null, Lang::SUPPORTED, true) ? $_COOKIE['lang'] : $pref;
        Response::redirect('/' . $lang, 302);
    }

    public function home(string $lang): void
    {
        $featured = SiteData::featured();
        $horses = SiteData::horses();
        $featuredResults = $featured ? SiteData::results((int) $featured['id']) : [];
        usort($featuredResults, fn ($a, $b) => [$a['medal'] === 'none', array_search($a['medal'], ['gold', 'silver', 'bronze', 'none'], true)] <=> [$b['medal'] === 'none', array_search($b['medal'], ['gold', 'silver', 'bronze', 'none'], true)]);
        $sections = SiteData::homeSections();
        $this->page('home', [
            'sections' => $sections,
            // foals and bloodlines: read only when switched on in Website → Homepage & texts
            'foals' => in_array('foals', $sections, true) ? SiteData::foals() : [],
            'bloodlines' => in_array('bloodlines', $sections, true) ? SiteData::bloodlines() : [],
            'title' => '', 'seoTitle' => __('site.seo_home_title'),
            'solidHeader' => false,
            'featured' => $featured,
            'featuredResults' => array_slice($featuredResults, 0, 4),
            'horses' => $horses,
            'groups' => SiteData::grouped($horses),
            'heroPhotos' => SiteData::heroPhotos(),
            'counters' => SiteData::counters(),
            'medals' => array_values(array_filter(SiteData::results(null, 40), fn ($r) => $r['medal'] !== 'none')),
            'news' => SiteData::pageOn('news') ? SiteData::news(3) : [],
            'gallery' => SiteData::gallery(8),
            'experts' => SiteData::experts(),
            'jsonLd' => self::businessLd(),
        ]);
    }

    public function horses(string $lang): void
    {
        // every group is on the page; ?category=… only picks the tab shown first (the others are hidden until chosen)
        $groups = SiteData::grouped(SiteData::horses());
        $cat = (string) ($_GET['category'] ?? '');
        $this->page('horses', [
            'title' => __('site.nav_horses'), 'seoTitle' => __('site.seo_horses_title'), 'description' => __('site.seo_horses_desc'),
            'groups' => $groups, 'active' => isset($groups[$cat]) ? $cat : '',
        ]);
    }

    public function horse(string $lang, string $slug): void
    {
        $h = SiteData::horse($slug);
        if (!$h) {
            // a horse that was on the website and was taken off (hidden, archived, sold, deceased or deleted): 410 Gone, so
            // search engines drop the address sooner; an address that never existed stays 404
            if (DB::value('SELECT 1 FROM horses WHERE slug = ? AND is_external = 0', [$slug])) {
                Response::gone();
            }
            Response::notFound();
        }
        // one address per horse: /en/horses/D-MAJD (the database ignores letter case) goes on to /en/horses/d-majd
        if ($h['slug'] !== $slug) {
            Response::redirect('/' . $lang . '/horses/' . rawurlencode((string) $h['slug']), 301);
        }
        $photos = SiteData::photos((int) $h['id']);
        $results = SiteData::show('profile_results') ? SiteData::results((int) $h['id']) : [];
        $gens = max(2, min(HorseService::PEDIGREE_GENERATIONS, (int) setting('site.profile_pedigree_gens', HorseService::PEDIGREE_GENERATIONS)));
        $this->page('horse', [
            // the story only when the profile shows it (Website → Horse profiles → Story)
            'title' => loc($h), 'seoTitle' => __('site.seo_horse_title', ['name' => loc($h), 'kind' => horse_kind($h)]), 'description' => (SiteData::show('profile_story') ? self::metaText((string) loc($h, 'story')) : '') ?: self::horseMeta($h),
            'ogImage' => $h['main_photo_id'] ? absolute_url('/media/' . $h['main_photo_id'] . '/full') : null, 'ogAlt' => loc($h) . ' – ' . horse_kind($h),
            'h' => $h, 'photos' => $photos, 'results' => $results, 'medals' => SiteData::medalCounts($results),
            'pedigree' => SiteData::show('profile_pedigree') ? SiteData::pedigree($h['sire_id'] ? (int) $h['sire_id'] : null, $h['dam_id'] ? (int) $h['dam_id'] : null, $gens) : null,
            'gens' => $gens, 'related' => SiteData::related($h), 'neighbours' => SiteData::neighbours((int) $h['id']),
        ]);
    }

    /**
     * What search engines read about the stud (schema.org): a local business in Doha, Qatar — name in both languages,
     * the website's phone and email, the map pin of Website → Contact details, logo and social links. No offers: the
     * stud does not sell horses.
     */
    private static function businessLd(): array
    {
        $ld = [
            '@context' => 'https://schema.org', '@type' => 'LocalBusiness', '@id' => absolute_url('/') . '#stud',
            'name' => 'SK Arabians', 'alternateName' => ['اس كي ارابيان', 'Sk.Arabian'],
            'description' => __('site.meta_description'),
            'url' => absolute_url('/'), 'logo' => absolute_url('/assets/img/sk-logo.png'), 'image' => absolute_url('/assets/img/og-default.jpg'),
            'telephone' => site_contact('phone'), 'email' => site_contact('email'),
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Doha', 'addressCountry' => 'QA', 'postOfficeBoxNumber' => (string) (setting('company.po_box', '') ?: '6657')],
            'areaServed' => ['@type' => 'Country', 'name' => 'Qatar'],
            'knowsAbout' => ['Purebred Arabian horses', 'Arabian horse shows', 'الخيل العربية الأصيلة'],
        ];
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', (string) setting('company.map_query', ''), $m)) {
            $ld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
            $ld['hasMap'] = 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2];
        }
        $social = array_values(array_filter(array_map(fn ($k) => trim((string) setting('company.' . $k, '')), ['instagram', 'x', 'youtube', 'facebook', 'tiktok'])));
        if ($social) {
            $ld['sameAs'] = $social;
        }
        return $ld;
    }

    /** A text for search results and link previews: one line, cut at a word to about 160 characters ("" when empty). */
    private static function metaText(string $text, int $max = 160): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $cut = mb_substr($t, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:—-،") . '…';
    }

    /** A horse without a story: "D SAMED — Stallion, Grey, 9 years. By FA EL RASHEEM out of D FITOON. Purebred Arabian …" */
    private static function horseMeta(array $h): string
    {
        $facts = [__('horse.cat_' . $h['category'])];
        if (SiteData::show('profile_colour') && ($c = loc(['name_en' => $h['color_en'], 'name_ar' => $h['color_ar']]))) {
            $facts[] = $c;
        }
        if ($h['dob'] && SiteData::show('profile_age')) {
            $facts[] = horse_age($h['dob']);
        }
        $sire = loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']]);
        $dam = loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']]);
        return __('site.meta_horse', ['name' => loc($h), 'facts' => implode(lang() === 'ar' ? '، ' : ', ', $facts)])
            . ($sire && $dam ? ' ' . __('site.meta_horse_parents', ['sire' => $sire, 'dam' => $dam]) : '')
            . ' ' . __('site.meta_horse_end');
    }

    /** A website page the Owner switched off (Website → Homepage & texts → Menu pages) goes to the homepage. */
    private function pageOn(string $page, string $lang): void
    {
        if (!SiteData::pageOn($page)) {
            Response::redirect('/' . $lang, 302); // a path: redirect() adds the install base itself
        }
    }

    public function champions(string $lang): void
    {
        $this->pageOn('champions', $lang);
        // medals, titles and placings (a result with none of them tells a visitor nothing)
        $results = array_values(array_filter(SiteData::results(), fn ($r) => $r['medal'] !== 'none' || $r['title_en'] || $r['placing']));
        $byYear = [];
        foreach ($results as $r) {
            $byYear[substr((string) $r['start_date'], 0, 4)][] = $r; // newest season first
        }
        // the season shown first: ?year=… (a year tab without JavaScript), "all", or the latest season
        $year = (string) ($_GET['year'] ?? '');
        $this->page('champions', [
            'title' => __('site.nav_champions'), 'seoTitle' => __('site.seo_champions_title'), 'description' => __('site.seo_champions_desc'),
            'byYear' => $byYear, 'latest' => (string) array_key_first($byYear),
            'active' => $year === 'all' || isset($byYear[$year]) ? $year : (string) array_key_first($byYear),
            'champions' => SiteData::champions($results), 'tally' => SiteData::medalCounts($results),
        ]);
    }

    /** The breeding page is gone (the website shows no breeding plans or breedings): old links go to the horses. */
    public function breeding(string $lang): void
    {
        Response::redirect('/' . $lang . '/horses', 301);
    }

    public function news(string $lang): void
    {
        $this->pageOn('news', $lang);
        $this->page('news', ['title' => __('site.nav_news'), 'description' => __('site.meta_news'), 'items' => SiteData::news()]);
    }

    public function newsItem(string $lang, string $slug): void
    {
        $this->pageOn('news', $lang);
        $n = DB::row('SELECT * FROM news WHERE slug = ? AND published = 1 AND deleted_at IS NULL AND published_at <= NOW()', [$slug]);
        if (!$n) {
            // withdrawn or deleted news: 410 Gone (a story not published yet, or an address that never existed: 404)
            $was = DB::row('SELECT published, deleted_at, published_at FROM news WHERE slug = ?', [$slug]);
            if ($was && ($was['deleted_at'] !== null || ((int) $was['published'] === 0 && $was['published_at'] !== null && $was['published_at'] <= date('Y-m-d H:i:s')))) {
                Response::gone();
            }
            Response::notFound();
        }
        if ($n['slug'] !== $slug) {
            Response::redirect('/' . $lang . '/news/' . rawurlencode((string) $n['slug']), 301);
        }
        $this->page('news_item', [
            'title' => loc($n, 'title'), 'description' => self::metaText((string) loc($n, 'summary')) ?: __('site.meta_news'), 'n' => $n,
            'ogImage' => $n['image_id'] ? absolute_url('/media/' . $n['image_id'] . '/full') : null, 'ogType' => 'article',
        ]);
    }

    public function about(string $lang): void
    {
        $this->pageOn('about', $lang);
        $this->page('about', [
            'title' => __('site.nav_about'), 'seoTitle' => __('site.seo_about_title'), 'description' => self::metaText((string) setting('site.story_' . $lang, '')) ?: __('site.meta_about'),
            'experts' => SiteData::experts(),
            'photos' => SiteData::heroPhotos(2), // the photo the story scrolls over (public horse photos only)
        ]);
    }

    public function contact(string $lang): void
    {
        $this->pageOn('contact', $lang);
        $this->page('contact', ['title' => __('site.nav_contact'), 'seoTitle' => __('site.seo_contact_title'), 'description' => __('site.meta_contact'), 'jsonLd' => self::businessLd()]);
    }

    /** Contact form: saved to the portal inbox (linked to the horse it is about), Owner and Manager notified. */
    public function inquiry(string $lang): void
    {
        $back = local_path(Request::post('back'), site_url('contact'));
        if (Request::post('website') !== '' && Request::post('website') !== null) {
            Response::redirect($back); // honeypot
        }
        $ip = Request::ip();
        // every message is shown in the form itself (#inquiry), where the visitor comes back to
        if ((int) DB::value('SELECT COUNT(*) FROM inquiries WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip]) >= 5) {
            Session::flash('error', __('site.inquiry_limit'));
            Response::redirect($back . '#inquiry');
        }
        $name = mb_substr(trim((string) Request::post('name')), 0, 120);
        $email = trim((string) Request::post('email'));
        $phone = mb_substr(preg_replace('/[^\d+ ]/', '', (string) Request::post('phone')), 0, 40);
        $msg = mb_substr(trim((string) Request::post('message')), 0, 4000);
        if ($name === '' || $msg === '' || ($email === '' && $phone === '') || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            Session::flash('error', __('site.inquiry_invalid'));
            Session::keepOld($_POST);
            Response::redirect($back . '#inquiry');
        }
        $horseId = (int) Request::post('horse_id') ?: null;
        if ($horseId && !DB::value('SELECT 1 FROM horses WHERE id = ? AND show_on_website = 1 AND deleted_at IS NULL', [$horseId])) {
            $horseId = null;
        }
        $type = $horseId ? 'horse' : (in_array(Request::post('type'), ['general', 'visit', 'media'], true) ? Request::post('type') : 'general');
        $id = DB::insert('inquiries', [
            'type' => $type, 'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null,
            'country' => mb_substr((string) Request::post('country'), 0, 80) ?: null, 'message' => $msg,
            'horse_id' => $horseId, 'lang' => Lang::current(), 'ip' => $ip,
        ]);
        $about = $horseId ? (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$horseId]) : '';
        // the title in each reader's own language (not the visitor's)
        Notifier::roles(['owner', 'general_manager'], 'inquiry', Notifier::msg('notify.new_inquiry', ['name' => $name]), ($about ? $about . ' — ' : '') . mb_substr($msg, 0, 200), '/portal/inbox/' . $id, true);
        Audit::log('inquiry_received', 'inbox', 'inquiry', $id, null, ['type' => $type, 'horse_id' => $horseId], 'Website inquiry', ['id' => null, 'name' => 'website']);
        Session::flash('success', __('site.inquiry_thanks'));
        Response::redirect($back . '#inquiry');
    }

    /** Public verification of a Studio document: reference, type and date only. */
    public function verify(string $token): void
    {
        Session::start();
        Lang::set($_COOKIE['lang'] ?? 'en');
        $doc = preg_match('/^[a-f0-9]{32}$/', $token) ? DB::row('SELECT ref_no, doc_type, doc_date, is_void FROM documents WHERE verify_token = ? AND deleted_at IS NULL', [$token]) : null;
        // or a printed list / report (its QR code is on every page)
        $printout = $doc ? null : \App\Services\PrintoutService::find($token);
        header('X-Robots-Tag: noindex');
        $this->view('site/verify', ['title' => __('verify.title'), 'doc' => $doc, 'printout' => $printout, 'token' => $token], 'site');
    }

    /** Public images (horse photos marked for the website, news, gallery, experts). */
    public function media(string $id, string $size): void
    {
        $f = DB::row('SELECT * FROM files WHERE id = ?', [(int) $id]);
        if (!$f || !FileStore::isPublic($f)) {
            Response::notFound();
        }
        $name = ($size === 'thumb' && $f['thumb_name']) ? $f['thumb_name'] : $f['stored_name'];
        $mime = ($size === 'thumb' && $f['thumb_name']) ? 'image/webp' : $f['mime'];
        $path = FileStore::path($name);
        if (!is_file($path)) {
            Response::notFound();
        }
        $etag = '"' . md5($name . filemtime($path)) . '"';
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        // a video is sent in parts when the player asks (iPhone Safari plays a video only that way)
        Response::file($path, $mime, null, false, 604800, str_starts_with($mime, 'video/') ? $etag : null);
    }

    public function robots(): void
    {
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /portal\nDisallow: /verify\nAllow: /\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
        exit;
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $paths = array_merge(['', 'horses'], array_values(array_filter(['champions', 'news', 'about', 'contact'], [SiteData::class, 'pageOn'])));
        foreach (DB::column("SELECT slug FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND archived_at IS NULL AND status IN ('active','in_shelter')") as $s) {
            $paths[] = 'horses/' . $s;
        }
        foreach (DB::column('SELECT slug FROM news WHERE published = 1 AND deleted_at IS NULL AND published_at <= NOW()') as $s) {
            $paths[] = 'news/' . $s;
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        foreach ($paths as $p) {
            foreach (['en', 'ar'] as $l) {
                $u = absolute_url('/' . $l . ($p !== '' ? '/' . $p : ''));
                echo '<url><loc>' . e($u) . '</loc>';
                foreach (['en', 'ar'] as $alt) {
                    echo '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . e(absolute_url('/' . $alt . ($p !== '' ? '/' . $p : ''))) . '"/>';
                }
                echo '</url>';
            }
        }
        echo '</urlset>';
        exit;
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json');
        echo json_encode([
            'name' => 'SK Arabians Management System', 'short_name' => 'SK Arabians', 'start_url' => url('/portal'), 'scope' => url('/portal'),
            'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => '#213562',
            'icons' => [
                ['src' => url('/assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => url('/assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
