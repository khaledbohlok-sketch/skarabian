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
use App\Services\Notifier;
use App\Services\SiteData;

/** Public website. Horses lead every page; all horse content comes from the Horses module. */
class SiteController extends Controller
{
    private function page(string $view, array $data = []): never
    {
        header('Cache-Control: public, max-age=120');
        $this->view('site/' . $view, $data, 'site');
    }

    public function root(): void
    {
        $pref = substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2) === 'ar' ? 'ar' : 'en';
        Response::redirect('/' . ($_COOKIE['lang'] ?? $pref), 302);
    }

    public function home(string $lang): void
    {
        $featured = SiteData::featured();
        [$sires, $dams] = SiteData::bloodlines();
        $this->page('home', [
            'title' => '',
            'solidHeader' => false,
            'featured' => $featured,
            'featuredResults' => $featured ? array_slice(SiteData::results((int) $featured['id']), 0, 4) : [],
            'horses' => SiteData::horses([], 24),
            'counters' => SiteData::counters(),
            'medals' => array_values(array_filter(SiteData::results(null, 30), fn ($r) => $r['medal'] !== 'none')),
            'foals' => SiteData::latestFoals(4),
            'sires' => $sires,
            'dams' => $dams,
            'pedigree' => $featured ? SiteData::pedigree($featured['sire_id'] ? (int) $featured['sire_id'] : null, $featured['dam_id'] ? (int) $featured['dam_id'] : null, 2) : null,
            'stud' => (int) DB::value("SELECT COUNT(*) FROM horses WHERE at_stud = 1 AND sex = 'male' AND show_on_website = 1 AND deleted_at IS NULL"),
            'embryos' => count(SiteData::embryosForSale()),
            'forSale' => (int) DB::value("SELECT COUNT(*) FROM horses WHERE for_sale = 1 AND show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter')"),
            'gallery' => SiteData::gallery(8),
            'experts' => SiteData::experts(),
            'jsonLd' => [
                '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'SK Arabians',
                'alternateName' => 'اس كي ارابيان للتجارة', 'url' => absolute_url('/'), 'logo' => absolute_url('/assets/img/sk-logo.png'),
                'telephone' => setting('company.phone_intl'), 'email' => setting('company.email'),
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Doha', 'addressCountry' => 'QA', 'postOfficeBoxNumber' => setting('company.po_box')],
            ],
        ]);
    }

    public function horses(string $lang): void
    {
        $filters = array_intersect_key($_GET, array_flip(['category', 'sex', 'age', 'bloodline']));
        $this->page('horses', [
            'title' => __('site.nav_horses'), 'description' => __('site.horses_intro'),
            'horses' => SiteData::horses($filters), 'filters' => $filters, 'bloodlines' => SiteData::bloodlineOptions(),
        ]);
    }

    public function horse(string $lang, string $slug): void
    {
        $h = SiteData::horse($slug);
        if (!$h) {
            Response::notFound();
        }
        $photos = SiteData::photos((int) $h['id']);
        $this->page('horse', [
            'title' => loc($h), 'description' => mb_substr(strip_tags((string) loc($h, 'story')), 0, 160) ?: __('site.horse_meta', ['name' => loc($h)]),
            'ogImage' => $h['main_photo_id'] ? absolute_url('/media/' . $h['main_photo_id'] . '/full') : null,
            'h' => $h, 'photos' => $photos, 'results' => SiteData::results((int) $h['id']), 'offspring' => SiteData::offspring((int) $h['id']),
            'pedigree' => SiteData::pedigree($h['sire_id'] ? (int) $h['sire_id'] : null, $h['dam_id'] ? (int) $h['dam_id'] : null, 4),
        ]);
    }

    public function champions(string $lang): void
    {
        $byYear = [];
        foreach (SiteData::results() as $r) {
            if ($r['medal'] === 'none' && !$r['title_en'] && !$r['placing']) {
                continue;
            }
            $byYear[substr($r['start_date'], 0, 4)][$r['show_en'] . '|' . $r['start_date']][] = $r;
        }
        $this->page('champions', ['title' => __('site.nav_champions'), 'byYear' => $byYear, 'counters' => SiteData::counters()]);
    }

    public function breeding(string $lang): void
    {
        $this->page('breeding', [
            'title' => __('site.nav_breeding'), 'stallions' => SiteData::horses(['at_stud' => 1]), 'embryos' => SiteData::embryosForSale(),
        ]);
    }

    public function forSale(string $lang): void
    {
        $this->page('for_sale', ['title' => __('site.nav_for_sale'), 'horses' => SiteData::horses(['for_sale' => 1]), 'embryos' => SiteData::embryosForSale()]);
    }

    public function news(string $lang): void
    {
        $this->page('news', ['title' => __('site.nav_news'), 'items' => SiteData::news()]);
    }

    public function newsItem(string $lang, string $slug): void
    {
        $n = DB::row('SELECT * FROM news WHERE slug = ? AND published = 1 AND deleted_at IS NULL AND published_at <= NOW()', [$slug]);
        if (!$n) {
            Response::notFound();
        }
        $this->page('news_item', ['title' => loc($n, 'title'), 'description' => loc($n, 'summary'), 'n' => $n]);
    }

    public function about(string $lang): void
    {
        $this->page('about', ['title' => __('site.nav_about'), 'experts' => SiteData::experts(), 'counters' => SiteData::counters()]);
    }

    public function contact(string $lang): void
    {
        $this->page('contact', ['title' => __('site.nav_contact')]);
    }

    /** Inquiry form: saved to the portal inbox, linked to the horse or embryo, Owner and Manager notified. */
    public function inquiry(string $lang): void
    {
        $back = (string) Request::post('back', site_url('contact'));
        $back = str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : site_url('contact');
        if (Request::post('website') !== '' && Request::post('website') !== null) {
            Response::redirect($back); // honeypot
        }
        $ip = Request::ip();
        if ((int) DB::value('SELECT COUNT(*) FROM inquiries WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip]) >= 5) {
            Session::flash('error', __('site.inquiry_limit'));
            Response::redirect($back);
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
        $embryoId = (int) Request::post('embryo_id') ?: null;
        if ($horseId && !DB::value('SELECT 1 FROM horses WHERE id = ? AND show_on_website = 1 AND deleted_at IS NULL', [$horseId])) {
            $horseId = null;
        }
        if ($embryoId && !DB::value('SELECT 1 FROM embryos WHERE id = ? AND for_sale = 1 AND deleted_at IS NULL', [$embryoId])) {
            $embryoId = null;
        }
        $type = in_array(Request::post('type'), ['general', 'horse', 'breeding', 'embryo', 'sale'], true) ? Request::post('type') : ($horseId ? 'horse' : ($embryoId ? 'embryo' : 'general'));
        $id = DB::insert('inquiries', [
            'type' => $type, 'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null,
            'country' => mb_substr((string) Request::post('country'), 0, 80) ?: null, 'message' => $msg,
            'horse_id' => $horseId, 'embryo_id' => $embryoId, 'lang' => Lang::current(), 'ip' => $ip,
        ]);
        $about = $horseId ? (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$horseId]) : ($embryoId ? (string) DB::value('SELECT code FROM embryos WHERE id = ?', [$embryoId]) : '');
        Notifier::roles(['owner', 'general_manager'], 'inquiry', __('notify.new_inquiry', ['name' => $name]), ($about ? $about . ' — ' : '') . mb_substr($msg, 0, 200), '/portal/inbox/' . $id, true);
        Audit::log('inquiry_received', 'inbox', 'inquiry', $id, null, ['type' => $type, 'horse_id' => $horseId, 'embryo_id' => $embryoId], 'Website inquiry', ['id' => null, 'name' => 'website']);
        Session::flash('success', __('site.inquiry_thanks'));
        Response::redirect($back . '#inquiry');
    }

    /** Public verification of a Studio document: reference, type and date only. */
    public function verify(string $token): void
    {
        Session::start();
        Lang::set($_COOKIE['lang'] ?? 'en');
        $doc = preg_match('/^[a-f0-9]{32}$/', $token) ? DB::row('SELECT ref_no, doc_type, doc_date, is_void FROM documents WHERE verify_token = ? AND deleted_at IS NULL', [$token]) : null;
        header('X-Robots-Tag: noindex');
        $this->view('site/verify', ['title' => __('verify.title'), 'doc' => $doc], 'site');
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
        $etag = '"' . md5($name . filemtime($path)) . '"';
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        Response::file($path, $mime, null, false, 604800);
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
        $paths = ['', 'horses', 'champions', 'breeding', 'for-sale', 'news', 'about', 'contact'];
        foreach (DB::column("SELECT slug FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter')") as $s) {
            $paths[] = 'horses/' . $s;
        }
        foreach (DB::column('SELECT slug FROM news WHERE published = 1 AND deleted_at IS NULL') as $s) {
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
