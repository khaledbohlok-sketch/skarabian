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

/**
 * Website content: homepage texts and section order, which horses and experts appear, and contact details.
 * The horses themselves stay in the Horses module; this screen only decides what the public sees.
 */
class CmsController extends Controller
{
    public const SECTIONS = ['featured', 'horses', 'achievements', 'foals', 'bloodlines', 'breeding', 'gallery', 'story', 'experts', 'contact'];

    private const HOME = [
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
    ];

    public function index(): void
    {
        Auth::requirePerm('cms', 'view');
        $canEdit = Auth::can('cms', 'edit');
        if (Request::isPost()) {
            Auth::requirePerm('cms', 'edit');
            $errors = AdminController::saveSettings(self::HOME);
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
        $current = array_values(array_filter(array_map('trim', explode(',', (string) Settings::get('site.sections', implode(',', self::SECTIONS))))));
        $public = DB::all("SELECT id, name_en, name_ar FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter') ORDER BY name_en");
        $stats = [
            'horses'  => (int) DB::value("SELECT COUNT(*) FROM horses WHERE show_on_website = 1 AND deleted_at IS NULL AND status IN ('active','in_shelter')"),
            'news'    => (int) DB::value('SELECT COUNT(*) FROM news WHERE deleted_at IS NULL AND published = 1'),
            'gallery' => (int) DB::value('SELECT COUNT(*) FROM gallery_items WHERE deleted_at IS NULL AND published = 1'),
            'experts' => (int) DB::value("SELECT COUNT(*) FROM employees WHERE show_on_website = 1 AND deleted_at IS NULL AND status <> 'left'"),
            'inbox'   => Auth::can('inbox') ? (int) DB::value("SELECT COUNT(*) FROM inquiries WHERE status = 'new' AND deleted_at IS NULL") : null,
        ];
        $this->view('portal/cms/index', [
            'title' => __('nav.cms_home'), 'defs' => self::HOME, 'current' => $current, 'public' => $public,
            'featured' => (int) Settings::get('site.featured_horse_id', 0), 'canEdit' => $canEdit, 'stats' => $stats,
        ]);
    }

    /** Which horses and experts appear on the website. */
    public function horses(): void
    {
        Auth::requirePerm('cms', 'view');
        if (Request::isPost()) {
            Auth::requirePerm('cms', 'edit');
            $this->toggle();
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
            $data['rows'] = DB::all("SELECT h.id, h.name_en, h.name_ar, h.category, h.sex, h.dob, h.main_photo_id, h.show_on_website, h.breeding_stallion, h.is_favorite,
                    h.born_at_sk, h.website_approved_at, h.story_en, h.archived_at,
                    (SELECT 1 FROM approvals a WHERE a.type = 'foal_website' AND a.record_id = h.id AND a.status = 'pending' LIMIT 1) AS waiting
                FROM horses h WHERE $where ORDER BY h.show_on_website DESC, FIELD(h.category,'stallion','mare','colt','filly','foal','gelding'), h.name_en", $params);
            $data['filter'] = $filter;
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
