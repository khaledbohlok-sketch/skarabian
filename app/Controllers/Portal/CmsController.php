<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Session;
use App\Services\Cache;
use App\Services\HorseService;
use App\Services\Settings;
use App\Services\SiteData;
use App\Services\SiteVisits;

/**
 * Website content: homepage texts and section order, which horses and experts appear, and contact details.
 * The horses themselves stay in the Horses module; this screen only decides what the public sees.
 */
class CmsController extends Controller
{
    /**
     * Homepage sections after the hero. Still no breeding content (no plans, pregnancies or embryos): "Latest foals" and
     * "Bloodlines" only regroup horses already on the website, and stay off until switched on here.
     */
    public const SECTIONS = SiteData::SECTIONS;

    /** Menu pages besides Home and Horses (which are always there). Third value: shown ticked until first saved. */
    private const PAGES = [
        'site.page_champions' => ['bool', 'cms.page_champions', '1'],
        'site.page_news'      => ['bool', 'cms.page_news', '1'],
        'site.page_about'     => ['bool', 'cms.page_about', '1'],
        'site.page_contact'   => ['bool', 'cms.page_contact', '1'],
    ];

    /** What visitors see on a horse's profile and card. */
    private const PROFILE = [
        'site.profile_age'      => ['bool', 'cms.p_age', '1'],
        'site.profile_colour'   => ['bool', 'cms.p_colour', '1'],
        'site.profile_breeder'  => ['bool', 'cms.p_breeder', '1'],
        'site.profile_country'  => ['bool', 'cms.p_country', '1'],
        'site.profile_story'    => ['bool', 'cms.p_story', '1'],
        'site.profile_video'    => ['bool', 'cms.p_video', '1'],
        'site.profile_pedigree' => ['bool', 'cms.p_pedigree', '1'],
        'site.profile_results'  => ['bool', 'cms.p_results', '1'],
        'site.profile_inquiry'  => ['bool', 'cms.p_inquiry', '1'],
        'site.card_titles'      => ['bool', 'cms.p_card_titles', '1'],
        'site.card_parents'     => ['bool', 'cms.p_card_parents', '1'],
    ];

    /** Which show results the website shows (Champions page, horse profiles, homepage); the portal keeps every result. */
    private const RESULTS = [
        'site.results_from_year'   => ['int', 'cms.results_from_year', '2025'],
        'site.results_medals_only' => ['bool', 'cms.results_medals_only', '1'],
        'site.results_show_class'  => ['bool', 'cms.results_show_class', '0'],
    ];

    /** The homepage texts (public: the ✨ Translate buttons check their English / Arabic pairs). */
    public const HOME = [
        'site.hero_title_en'    => ['text', 'cms.hero_title_en'],
        'site.hero_title_ar'    => ['text', 'cms.hero_title_ar'],
        'site.hero_sub_en'      => ['text', 'cms.hero_sub_en'],
        'site.hero_sub_ar'      => ['text', 'cms.hero_sub_ar'],
        'site.hero_video_url'   => ['url', 'cms.hero_video'],
        'site.established'      => ['int', 'cms.established'],
        'site.story_en'         => ['textarea', 'cms.story_en'],
        'site.story_ar'         => ['textarea', 'cms.story_ar'],
        'site.pillar1_title_en' => ['text', 'cms.pillar1_title_en'],
        'site.pillar1_title_ar' => ['text', 'cms.pillar1_title_ar'],
        'site.pillar1_text_en'  => ['textarea', 'cms.pillar1_text_en'],
        'site.pillar1_text_ar'  => ['textarea', 'cms.pillar1_text_ar'],
        'site.pillar2_title_en' => ['text', 'cms.pillar2_title_en'],
        'site.pillar2_title_ar' => ['text', 'cms.pillar2_title_ar'],
        'site.pillar2_text_en'  => ['textarea', 'cms.pillar2_text_en'],
        'site.pillar2_text_ar'  => ['textarea', 'cms.pillar2_text_ar'],
        'site.pillar3_title_en' => ['text', 'cms.pillar3_title_en'],
        'site.pillar3_title_ar' => ['text', 'cms.pillar3_title_ar'],
        'site.pillar3_text_en'  => ['textarea', 'cms.pillar3_text_en'],
        'site.pillar3_text_ar'  => ['textarea', 'cms.pillar3_text_ar'],
    ];

    private const CONTACT = [
        'company.mobile'      => ['text', 'cms.mobile'],
        'company.phone_intl'  => ['phone', 'cms.phone_intl'],
        'company.whatsapp'    => ['phone', 'cms.whatsapp'],
        'company.email'       => ['email', 'cms.email'],
        'company.po_box'      => ['text', 'cms.po_box'],
        'company.city_en'     => ['text', 'cms.city_en'],
        'company.city_ar'     => ['text', 'cms.city_ar'],
        'company.address_en'  => ['text', 'cms.address_en'],
        'company.address_ar'  => ['text', 'cms.address_ar'],
        'company.map_query'   => ['text', 'cms.map_query'],
        'company.instagram'   => ['url', 'cms.instagram'],
        'company.x'           => ['url', 'cms.x'],
        'company.youtube'     => ['url', 'cms.youtube'],
        'company.facebook'    => ['url', 'cms.facebook'],
        'company.tiktok'      => ['url', 'cms.tiktok'],
        // printed on letterheads and documents only (the website shows the ones above)
        'company.doc_mobile'  => ['text', 'cms.doc_mobile', '5536 6699'],
        'company.doc_email'   => ['email', 'cms.doc_email', 'sk.qa@hotmail.com'],
    ];

    public function index(): void
    {
        Auth::requirePerm('cms', 'view');
        $canEdit = Auth::can('cms', 'edit');
        if (Request::isPost()) {
            Auth::requirePerm('cms', 'edit');
            $errors = AdminController::saveSettings(self::HOME) + AdminController::saveSettings(self::PAGES) + AdminController::saveSettings(self::PROFILE)
                + AdminController::saveSettings(self::RESULTS);
            $this->saveOne('site.profile_pedigree_gens', (string) max(2, min(HorseService::PEDIGREE_GENERATIONS, (int) Request::post('profile_pedigree_gens', HorseService::PEDIGREE_GENERATIONS))));
            // Section order: checked sections sorted by their number
            $order = [];
            foreach (self::SECTIONS as $i => $s) {
                if (!empty($_POST['sec_on'][$s])) {
                    $order[$s] = (int) ($_POST['sec_pos'][$s] ?? $i + 1) * 100 + $i;
                }
            }
            asort($order);
            $this->saveOne('site.sections', implode(',', array_keys($order)));
            $fid = (int) Request::post('featured_horse_id');
            if ($fid && !DB::value('SELECT 1 FROM horses WHERE id = ? AND show_on_website = 1 AND deleted_at IS NULL', [$fid])) {
                $errors['featured_horse_id'] = __('cms.featured_not_public');
            } else {
                $this->saveOne('site.featured_horse_id', $fid ? (string) $fid : '');
            }
            Session::errors($errors);
            Cache::bump();
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'cms.published'));
            $this->redirect('/portal/cms');
        }
        $current = SiteData::homeSections();
        $public = DB::all("SELECT id, name_en, name_ar FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter') ORDER BY name_en");
        $stats = [
            'horses'  => (int) DB::value("SELECT COUNT(*) FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter')"),
            'news'    => (int) DB::value('SELECT COUNT(*) FROM news WHERE deleted_at IS NULL AND published = 1'),
            'gallery' => (int) DB::value('SELECT COUNT(*) FROM gallery_items WHERE deleted_at IS NULL AND published = 1'),
            'experts' => (int) DB::value("SELECT COUNT(*) FROM employees WHERE show_on_website = 1 AND deleted_at IS NULL AND status <> 'left'"),
            'inbox'   => Auth::can('inbox') ? (int) DB::value("SELECT COUNT(*) FROM inquiries WHERE status = 'new' AND deleted_at IS NULL") : null,
        ];
        $this->view('portal/cms/index', [
            'title' => __('nav.cms_home'), 'defs' => self::HOME, 'pages' => self::PAGES, 'profile' => self::PROFILE, 'results' => self::RESULTS,
            'gens' => (int) Settings::get('site.profile_pedigree_gens', HorseService::PEDIGREE_GENERATIONS), 'current' => $current, 'public' => $public,
            'featured' => (int) Settings::get('site.featured_horse_id', 0), 'canEdit' => $canEdit, 'stats' => $stats,
        ]);
    }

    /** Website → Visitors: visits counted without cookies (SiteVisits) in the last 7, 30 or 90 days. */
    public function visitors(): void
    {
        Auth::requirePerm('cms', 'view');
        $days = in_array((int) Request::query('days'), [7, 30, 90], true) ? (int) Request::query('days') : 30;
        $on = SiteVisits::on();
        $this->view('portal/cms/visitors', [
            'title' => __('visits.title'), 'days' => $days, 'on' => $on,
            'totals' => $on ? SiteVisits::totals($days) : null,
            'daily' => $on ? SiteVisits::daily($days) : [],
            'horses' => $on ? SiteVisits::horses($days) : [],
            'pages' => $on ? SiteVisits::pages($days) : [],
            'sources' => $on ? SiteVisits::sources($days) : [],
            'countries' => $on ? SiteVisits::countries($days) : [],
            'devices' => $on ? SiteVisits::split('device', $days) : [],
            'langs' => $on ? SiteVisits::split('lang', $days) : [],
        ]);
    }

    /** Which horses and experts appear on the website. */
    public function horses(): void
    {
        Auth::requirePerm('cms', 'view');
        if (Request::isPost()) {
            Auth::requirePerm('cms', 'edit');
            match ((string) Request::post('action')) {
                'order' => $this->saveOrder(),
                'bulk' => $this->bulk(),
                default => $this->toggle(),
            };
        }
        $tab = Request::query('tab') === 'experts' ? 'experts' : 'horses';
        $q = trim((string) Request::query('q'));
        $data = ['title' => __('nav.website_horses'), 'tab' => $tab, 'q' => $q, 'canEdit' => Auth::can('cms', 'edit')];
        if ($tab === 'experts') {
            $data['rows'] = DB::all("SELECT e.id, e.name_en, e.name_ar, e.photo_id, e.show_on_website, e.website_sort, e.public_title_en, e.public_bio_en, p.value_en AS position
                FROM employees e LEFT JOIN lookups p ON p.id = e.position_id WHERE e.deleted_at IS NULL AND e.status <> 'left'"
                . ($q !== '' ? ' AND (e.name_en LIKE :q1 OR e.name_ar LIKE :q2)' : '') . ' ORDER BY e.show_on_website DESC, e.website_sort, e.name_en', $q !== '' ? ['q1' => "%$q%", 'q2' => "%$q%"] : []);
        } else {
            $filter = Request::query('show');
            $where = "h.deleted_at IS NULL AND h.is_external = 0 AND h.owner_type = 'sk' AND h.status IN ('active','in_shelter')";
            $params = [];
            if ($q !== '') {
                $where .= ' AND (h.name_en LIKE :q1 OR h.name_ar LIKE :q2)';
                $params['q1'] = $params['q2'] = "%$q%";
            }
            if ($filter === 'on') {
                $where .= ' AND h.show_on_website = 1';
            } elseif ($filter === 'off') {
                $where .= ' AND h.show_on_website = 0';
            } elseif ($filter === 'waiting') {
                $where .= " AND EXISTS (SELECT 1 FROM approvals a WHERE a.type = 'foal_website' AND a.record_id = h.id AND a.status = 'pending')";
            }
            $sort = DB::hasColumn('horses', 'website_sort');
            $data['rows'] = DB::all("SELECT h.id, h.name_en, h.name_ar, h.category, h.sex, h.dob, h.main_photo_id, h.show_on_website, h.is_favorite,
                    h.born_at_sk, h.website_approved_at, h.story_en, h.archived_at, " . ($sort ? 'h.website_sort' : 'NULL AS website_sort') . ",
                    (SELECT 1 FROM approvals a WHERE a.type = 'foal_website' AND a.record_id = h.id AND a.status = 'pending' LIMIT 1) AS waiting,
                    (SELECT COUNT(*) FROM show_results r WHERE r.horse_id = h.id AND r.deleted_at IS NULL) AS results,
                    (SELECT COUNT(*) FROM show_results r WHERE r.horse_id = h.id AND r.deleted_at IS NULL AND r.is_public = 1) AS results_public
                FROM horses h WHERE $where ORDER BY h.show_on_website DESC, FIELD(h.category,'stallion','mare','filly','colt','foal'), "
                . ($sort ? 'h.website_sort IS NULL, h.website_sort, ' : '') . 'h.name_en', $params);
            $data['filter'] = $filter;
            $data['canOrder'] = $sort;
        }
        $this->view('portal/cms/horses', $data);
    }

    private function toggle(): never
    {
        $kind = Request::post('kind');
        $id = (int) Request::post('id');
        $field = (string) Request::post('field');
        $on = Request::post('value') === '1';
        if ($kind === 'expert') {
            $e = DB::row('SELECT id, name_en, show_on_website, website_sort FROM employees WHERE id = ? AND deleted_at IS NULL', [$id]) ?? $this->gone();
            if ($field === 'website_sort') {
                $sort = max(0, min(999, (int) Request::post('value')));
                DB::update('employees', ['website_sort' => $sort], 'id = :id', ['id' => $id]);
            } else {
                DB::update('employees', ['show_on_website' => $on ? 1 : 0], 'id = :id', ['id' => $id]);
                Audit::log('update', 'cms', 'employee', $id, ['show_on_website' => (int) $e['show_on_website']], ['show_on_website' => (int) $on], ($on ? 'Shown on website: ' : 'Hidden from website: ') . $e['name_en']);
            }
            Cache::bump();
            $this->flash('success', __('common.saved'));
            $this->redirect('/portal/cms/website-horses?tab=experts');
        }
        $h = DB::row("SELECT id, name_en, show_on_website, breeding_stallion, is_favorite, born_at_sk, website_approved_at, sex FROM horses WHERE id = ? AND deleted_at IS NULL AND is_external = 0", [$id]) ?? $this->gone();
        if (!in_array($field, ['show_on_website', 'breeding_stallion', 'is_favorite'], true) || ($field === 'breeding_stallion' && $h['sex'] !== 'male')) {
            $this->gone();
        }
        $msg = 'common.saved';
        if ($field === 'show_on_website' && $on && $h['born_at_sk'] && !$h['website_approved_at']) {
            // Foals born at SK go public only after the Owner approves
            HorseService::requestWebsite((int) $h['id']);
            $msg = Auth::isOwner() ? 'common.saved' : 'approvals.sent';
        } else {
            DB::update('horses', [$field => $on ? 1 : 0], 'id = :id', ['id' => $id]);
            Audit::log('update', 'cms', 'horse', $id, [$field => (int) $h[$field]], [$field => (int) $on], $h['name_en'] . ': ' . $field . ' = ' . (int) $on);
        }
        Cache::bump();
        $this->flash('success', __($msg));
        $this->redirect('/portal/cms/website-horses' . (($ret = (string) Request::post('return')) !== '' && str_starts_with($ret, '?') ? $ret : ''));
    }

    /** The order of the horses on the website within their category (1 = first; empty = after the numbered ones). */
    private function saveOrder(): never
    {
        $n = 0;
        if (DB::hasColumn('horses', 'website_sort')) {
            foreach ((array) Request::post('order', []) as $id => $v) {
                $v = trim((string) $v);
                $sort = $v === '' ? null : max(1, min(9999, (int) $v));
                $n += DB::update('horses', ['website_sort' => $sort], "id = :id AND is_external = 0 AND deleted_at IS NULL AND NOT (website_sort <=> :s)", ['id' => (int) $id, 's' => $sort]);
            }
            if ($n) {
                Audit::log('update', 'cms', 'horse', null, null, ['website_order_changed' => $n], 'Website order of horses changed');
            }
        }
        Cache::bump();
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/cms/website-horses' . (($ret = (string) Request::post('return')) !== '' && str_starts_with($ret, '?') ? $ret : ''));
    }

    /**
     * Show or hide all of SK's horses (or all their show results) on the website at once. A foal born at SK still needs the
     * Owner: when someone else shows all, those foals get an approval request instead (as with the single switch).
     */
    private function bulk(): never
    {
        $what = (string) Request::post('what');
        $sk = "is_external = 0 AND owner_type = 'sk' AND deleted_at IS NULL AND status IN ('active','in_shelter')";
        $n = 0;
        $msg = 'cms.bulk_done';
        if ($what === 'horses_on') {
            foreach (DB::all("SELECT id, born_at_sk, website_approved_at FROM horses WHERE $sk AND show_on_website = 0") as $h) {
                if ($h['born_at_sk'] && !$h['website_approved_at']) {
                    HorseService::requestWebsite((int) $h['id']); // the Owner's own request is approved at once
                    $msg = Auth::isOwner() ? $msg : 'cms.bulk_done_foals';
                    $n++;
                    continue;
                }
                $n += DB::update('horses', ['show_on_website' => 1], 'id = :id', ['id' => $h['id']]);
            }
        } elseif ($what === 'horses_off') {
            $n = DB::run("UPDATE horses SET show_on_website = 0 WHERE $sk AND show_on_website = 1")->rowCount();
        } elseif ($what === 'results_on' || $what === 'results_off') {
            $n = DB::run('UPDATE show_results r JOIN horses h ON h.id = r.horse_id SET r.is_public = ' . ($what === 'results_on' ? 1 : 0)
                . " WHERE r.deleted_at IS NULL AND r.is_public = " . ($what === 'results_on' ? 0 : 1) . " AND h.is_external = 0 AND h.owner_type = 'sk' AND h.deleted_at IS NULL")->rowCount();
        } else {
            $this->gone();
        }
        Audit::log('update', 'cms', null, null, null, ['bulk' => $what, 'changed' => $n], 'Website: ' . $what . ' (' . $n . ')');
        Cache::bump();
        $this->flash('success', __($msg, ['n' => $n]));
        $this->redirect('/portal/cms/website-horses');
    }

    public function contact(): void
    {
        Auth::requirePerm('cms', 'edit');
        if (Request::isPost()) {
            $errors = AdminController::saveSettings(self::CONTACT);
            Session::errors($errors);
            Cache::bump();
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'cms.published'));
            $this->redirect('/portal/cms/contact');
        }
        $this->view('portal/cms/contact', ['title' => __('nav.contact_details'), 'defs' => self::CONTACT]);
    }

    private function saveOne(string $key, string $value): void
    {
        $old = (string) Settings::get($key, '');
        if ($old !== $value) {
            Settings::set($key, $value);
            Audit::log('setting', 'cms', 'setting', null, [$key => $old], [$key => $value], 'Setting changed: ' . $key);
        }
    }

    private function gone(): never
    {
        \App\Core\Response::notFound();
    }
}
