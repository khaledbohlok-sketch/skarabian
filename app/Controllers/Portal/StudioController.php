<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Mailer;
use App\Services\Pickers;
use App\Services\Studio;
use App\Services\StudioDocs;
use App\Services\StudioTemplates;

/**
 * SK Arabian Studio inside the Management System: archive, editor (filled from linked records), issued
 * documents with reference + verification QR, print / PDF / share (all logged), Owner template settings.
 */
class StudioController extends Controller
{
    private const STYLES = ['css/studio-doc.css', 'css/studio-ed.css'];
    private const SCRIPTS = ['js/studio-cfg.js', 'vendor/studio-libs.js', 'js/studio-render.js', 'js/studio-editor.js'];

    /** Letterhead details, image paths and the few interface texts the Studio script shows. */
    private function lh(): array
    {
        return [
            'i18n' => ['refPending' => __('studio.ref_pending'), 'refPh' => __('studio.ref_placeholder'), 'preparing' => __('studio.preparing'),
                       'pdfFailed' => __('studio.pdf_failed'), 'verifyAt' => __('studio.verify_at')],
            'csrf' => csrf_token(), 'logo' => asset('img/sk-logo.png'), 'mark' => asset('img/sk-mark.png'),
            'lh' => ['cr' => setting('company.cr', '231961'), 'mob' => setting('company.mobile', '5536 6699'), 'email' => setting('company.email', 'sk.qa@hotmail.com'),
                     'pobox' => setting('company.po_box', '6657') . ', ' . setting('company.city_en', 'Doha - Qatar')],
        ];
    }

    private function types(): array
    {
        $groups = [];
        foreach (StudioDocs::TYPES as $t => $def) {
            if (Studio::canCreate($t)) {
                $groups[$def['group']][] = $t;
            }
        }
        return $groups;
    }

    public function index(): void
    {
        Auth::requirePerm('studio', 'view');
        $where = ['d.deleted_at IS NULL'];
        $p = [];
        $type = (string) Request::query('type', '');
        if ($type !== '' && StudioDocs::exists($type)) {
            $where[] = 'd.doc_type = :t';
            $p['t'] = $type;
        }
        if (($q = trim((string) Request::query('q', ''))) !== '') {
            $where[] = '(d.ref_no LIKE :q OR d.title LIKE :q2)';
            $p['q'] = $p['q2'] = '%' . $q . '%';
        }
        // Only document types whose module the user may see
        $visible = array_keys(array_filter(StudioDocs::TYPES, fn ($d) => Auth::isOwner() || Auth::can($d['module'], 'view')));
        $where[] = 'd.doc_type IN ' . DB::in($visible ?: ['-'], 'v', $p);
        $page = max(1, (int) Request::query('page', 1));
        $total = (int) DB::value('SELECT COUNT(*) FROM documents d WHERE ' . implode(' AND ', $where), $p);
        $docs = DB::all('SELECT d.*, u.name AS author FROM documents d LEFT JOIN users u ON u.id = d.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30), $p);
        $this->view('portal/studio/index', ['title' => __('nav.studio'), 'docs' => $docs, 'total' => $total, 'page' => $page, 'groups' => $this->types()]);
    }

    /** Editor for a new document (GET) and issuing it (POST). */
    public function create(string $type): void
    {
        Auth::requireLogin();
        if (!StudioDocs::exists($type)) {
            Response::notFound();
        }
        if (!Studio::canCreate($type)) {
            Auth::deny('studio.' . $type);
        }
        if (Request::isPost()) {
            $this->store($type);
        }
        $kind = (string) Request::query('record_type', Studio::PICK[$type][0] ?? '');
        $rid = (int) Request::query('record_id', 0) ?: null;
        $from = (int) Request::query('from', 0);
        $saved = null;
        if ($from && ($src = DB::row('SELECT * FROM documents WHERE id = ? AND doc_type = ? AND deleted_at IS NULL', [$from, $type])) && Studio::canView($src)) {
            // "New version": start from an issued document (a new reference is issued on save)
            $saved = json_decode((string) $src['data'], true) ?: [];
            foreach (['ref', 'no', 'rno'] as $k) {
                if (($saved[$k] ?? null) === $src['ref_no']) {
                    $saved[$k] = '';
                }
            }
            [$kind, $rid] = [$src['record_type'], $src['record_id'] ? (int) $src['record_id'] : null];
            $prefill = [];
            $label = null;
        } else {
            [$prefill, $kind, $rid, $label] = Studio::prefill($type, $kind ?: null, $rid, $_GET);
        }
        $tpl = StudioTemplates::get($type);
        $pick = Studio::PICK[$type] ?? null;
        $this->view('portal/studio/editor', [
            'title' => __('studio.type_' . $type), 'styles' => self::STYLES, 'scripts' => self::SCRIPTS, 'bodyClass' => 'studio-body',
            'cfg' => [
                'mode' => 'new', 'type' => $type, 'numbered' => !in_array($type, StudioDocs::UNNUMBERED, true), 'canEdit' => Studio::canEditFields(),
                'tpl' => $tpl['defaults'] ?: new \stdClass(), 'letterhead' => $tpl['letterhead'], 'prefill' => $prefill ?: new \stdClass(), 'saved' => $saved,
                'ctx' => Studio::context($type), 'lang' => lang(), 'saveUrl' => url('/portal/studio/new/' . $type), 'logUrl' => url('/portal/studio/log'),
                'record' => ['kind' => $kind, 'id' => $rid, 'label' => $label ?? ($rid ? Pickers::label($pick[1] ?? '', $rid) : null), 'source' => $pick[1] ?? null,
                             'base' => url('/portal/studio/new/' . $type) . '?record_type=' . ($pick[0] ?? '') . '&record_id='],
            ] + $this->lh(),
        ]);
    }

    private function store(string $type): never
    {
        $raw = (string) Request::post('data');
        $data = strlen($raw) <= 2_000_000 ? json_decode($raw, true) : null;
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            $this->flash('error', __('studio.bad_data'));
            $this->back('/portal/studio');
        }
        if (in_array($type, StudioDocs::UNNUMBERED, true)) {
            Response::notFound();
        }
        $kind = (string) Request::post('record_type');
        $rid = (int) Request::post('record_id') ?: null;
        if ($kind !== '' && $rid) {
            // The linked record must be one the user may see (and of a kind this document is filled from)
            $ok = in_array($kind, Studio::SOURCES[$type] ?? [], true) && Studio::prefill($type, $kind, $rid)[1] !== null;
            if (!$ok) {
                Auth::deny('studio.record');
            }
        } else {
            [$kind, $rid] = [null, null];
        }
        if (!Studio::canEditFields() && $rid) {
            // Roles without Studio "edit" print what the records say: the record values are re-applied on the server
            $data = array_merge($data, Studio::prefill($type, $kind, $rid, $_POST)[0]);
        }
        $lang = Request::post('lang') === 'ar' ? 'ar' : 'en';
        $lh = (int) Request::post('letterhead');
        $data['_letterhead'] = in_array($lh, [1, 2], true) ? $lh : StudioTemplates::get($type)['letterhead'];
        $data['lang'] = $data['lang'] ?? $lang;
        $id = Studio::create($type, $data, (string) ($data['lang'] ?? $lang), $kind, $rid);
        $this->flash('success', __('studio.issued'));
        $this->redirect('/portal/studio/' . $id);
    }

    private function doc(string $id): array
    {
        Auth::requirePerm('studio', 'view');
        $d = DB::row('SELECT d.*, u.name AS author FROM documents d LEFT JOIN users u ON u.id = d.created_by WHERE d.id = ? AND d.deleted_at IS NULL', [(int) $id]);
        if (!$d) {
            Response::notFound();
        }
        if (!Studio::canView($d)) {
            Auth::deny('studio.view');
        }
        return $d;
    }

    private function viewCfg(array $d, string $mode): array
    {
        return [
            'mode' => $mode, 'type' => $d['doc_type'], 'id' => (int) $d['id'], 'saved' => json_decode((string) $d['data'], true) ?: new \stdClass(),
            'ref' => $d['ref_no'], 'issued' => $d['doc_date'], 'letterhead' => (int) $d['letterhead_version'], 'void' => (bool) $d['is_void'],
            'verifyUrl' => absolute_url('/verify/' . $d['verify_token']), 'logUrl' => url('/portal/studio/' . $d['id'] . '/log'),
            'fileName' => preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $d['ref_no'] . ' ' . $d['title']), 'ctx' => Studio::context($d['doc_type']),
        ] + $this->lh();
    }

    public function show(string $id): void
    {
        $d = $this->doc($id);
        $record = null;
        if ($d['record_type'] && $d['record_id']) {
            $res = Studio::KINDS[$d['record_type']][0] ?? null;
            $record = $res ? ['url' => '/portal/' . $res . '/' . $d['record_id'], 'label' => __('studio.open_record')] : null;
        }
        $this->view('portal/studio/show', [
            'title' => $d['ref_no'], 'doc' => $d, 'record' => $record, 'styles' => self::STYLES, 'scripts' => self::SCRIPTS, 'bodyClass' => 'studio-body',
            'cfg' => $this->viewCfg($d, 'view'), 'canNew' => Studio::canCreate($d['doc_type']),
            'canVoid' => !$d['is_void'] && (Auth::isOwner() || Auth::can('studio', 'delete')),
        ]);
    }

    /** Print view: only the pages; opens the print dialog. */
    public function print(string $id): void
    {
        $d = $this->doc($id);
        $this->view('portal/studio/print', ['title' => $d['ref_no'] . ' — ' . $d['title'], 'cfg' => $this->viewCfg($d, 'print'), 'doc' => $d], 'bare');
    }

    /** Print / PDF download / share events are recorded in the Activity Log. */
    public function log(string $id): void
    {
        $d = $this->doc($id);
        $action = (string) Request::post('action');
        if (in_array($action, ['print', 'download', 'share'], true)) {
            Audit::log($action, 'studio', 'document', $d['id'], null, ['via' => mb_substr((string) Request::post('via'), 0, 20)], $d['ref_no'] . ' — ' . $d['title']);
        }
        Response::json(['ok' => true]);
    }

    /** Lists and tools (all horses, reminders...) are printed without being archived; the print is still logged. */
    public function logUnsaved(): void
    {
        Auth::requirePerm('studio', 'view');
        $type = (string) Request::post('type');
        if (StudioDocs::exists($type) && Studio::canCreate($type)) {
            Audit::log((string) Request::post('action') === 'download' ? 'download' : 'print', 'studio', 'document', null, null, ['type' => $type], __('studio.type_' . $type));
        }
        Response::json(['ok' => true]);
    }

    /** E-mails the PDF (made in the browser from the archived document) to one address. */
    public function share(string $id): void
    {
        $d = $this->doc($id);
        if (!Auth::can('studio', 'print') && !Auth::isOwner()) {
            Auth::deny('studio.print');
        }
        $to = trim((string) Request::post('email'));
        $file = $_FILES['pdf'] ?? null;
        $err = null;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $err = __('validation.email');
        } elseif (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > 12 * 1048576 || !is_uploaded_file($file['tmp_name'])
            || (string) file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            $err = __('studio.pdf_failed');
        }
        if ($err) {
            Response::json(['ok' => false, 'error' => $err], 422);
        }
        $body = '<p>' . e(__('studio.mail_body', ['title' => $d['title'], 'ref' => $d['ref_no']])) . '</p><p><a href="' . e(absolute_url('/verify/' . $d['verify_token'])) . '">' . e(__('studio.mail_verify')) . '</a></p>';
        $ok = Mailer::send($to, $d['ref_no'] . ' — ' . $d['title'], $body, [['name' => preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $d['ref_no']) . '.pdf', 'mime' => 'application/pdf', 'content' => file_get_contents($file['tmp_name'])]]);
        Audit::log('share', 'studio', 'document', $d['id'], null, ['via' => 'email', 'to' => $to, 'sent' => $ok], $d['ref_no'] . ' — ' . $d['title']);
        Response::json(['ok' => $ok, 'message' => $ok ? __('studio.mail_sent', ['email' => $to]) : __('studio.mail_failed')]);
    }

    /** A void document stays in the archive; the verification page says it is no longer valid. */
    public function void(string $id): void
    {
        $d = $this->doc($id);
        if (!Auth::isOwner() && !Auth::can('studio', 'delete')) {
            Auth::deny('studio.delete');
        }
        DB::update('documents', ['is_void' => 1], 'id = :id', ['id' => $d['id']]);
        Audit::log('void', 'studio', 'document', $d['id'], ['is_void' => 0], ['is_void' => 1, 'reason' => mb_substr((string) Request::post('reason'), 0, 255)], $d['ref_no']);
        $this->flash('success', __('studio.voided'));
        $this->redirect('/portal/studio/' . $d['id']);
    }

    // ------------------------------------------------------------------ Owner settings

    public function templates(): void
    {
        $this->requireOwner();
        $rows = DB::all('SELECT t.*, u.name AS editor FROM studio_templates t LEFT JOIN users u ON u.id = t.updated_by');
        $this->view('portal/studio/templates', ['title' => __('studio.templates'), 'rows' => array_column($rows, null, 'doc_type')]);
    }

    /** Edits a template's standard wording (terms, clauses, greetings...) and letterhead version. */
    public function editTemplate(string $type): void
    {
        $this->requireOwner();
        if (!StudioDocs::exists($type) || in_array($type, StudioDocs::UNNUMBERED, true)) {
            Response::notFound();
        }
        if (Request::isPost()) {
            $data = json_decode((string) Request::post('data'), true);
            if (!is_array($data)) {
                $this->flash('error', __('studio.bad_data'));
                $this->redirect('/portal/studio/templates/' . $type);
            }
            unset($data['_letterhead']);
            StudioTemplates::save($type, $data, (int) Request::post('letterhead'));
            Audit::log('update', 'studio', 'template', null, null, ['type' => $type, 'letterhead' => (int) Request::post('letterhead')], __('studio.type_' . $type));
            $this->flash('success', __('studio.template_saved'));
            $this->redirect('/portal/studio/templates');
        }
        $tpl = StudioTemplates::get($type);
        $this->view('portal/studio/editor', [
            'title' => __('studio.template_of', ['name' => __('studio.type_' . $type)]), 'styles' => self::STYLES, 'scripts' => self::SCRIPTS, 'bodyClass' => 'studio-body',
            'cfg' => ['mode' => 'template', 'type' => $type, 'numbered' => true, 'canEdit' => true, 'tpl' => $tpl['defaults'] ?: new \stdClass(), 'letterhead' => $tpl['letterhead'],
                      'prefill' => new \stdClass(), 'saved' => null, 'ctx' => [], 'lang' => lang(), 'saveUrl' => url('/portal/studio/templates/' . $type), 'record' => null] + $this->lh(),
        ]);
    }

    /** Which roles may create each document type. */
    public function permissions(): void
    {
        $this->requireOwner();
        $roles = DB::all("SELECT id, name_en, name_ar, slug FROM roles WHERE slug <> 'owner' ORDER BY id");
        if (Request::isPost()) {
            $in = (array) ($_POST['perm'] ?? []);
            DB::transaction(function () use ($roles, $in) {
                foreach ($roles as $r) {
                    DB::run('DELETE FROM studio_doc_permissions WHERE role_id = ?', [$r['id']]);
                    foreach (array_keys(StudioDocs::TYPES) as $t) {
                        if (!empty($in[$r['id']][$t])) {
                            DB::insert('studio_doc_permissions', ['role_id' => $r['id'], 'doc_type' => $t]);
                        }
                    }
                }
            });
            Audit::log('update', 'studio', 'permissions', null, null, ['matrix' => array_map(fn ($x) => array_keys(array_filter((array) $x)), $in)], 'Studio document permissions');
            $this->flash('success', __('common.saved'));
            $this->redirect('/portal/studio/permissions');
        }
        $have = [];
        foreach (DB::all('SELECT role_id, doc_type FROM studio_doc_permissions') as $p) {
            $have[$p['role_id']][$p['doc_type']] = true;
        }
        $this->view('portal/studio/permissions', ['title' => __('studio.permissions'), 'roles' => $roles, 'have' => $have]);
    }

    private function requireOwner(): void
    {
        Auth::requireLogin();
        if (!Auth::isOwner()) {
            Auth::deny('studio.templates');
        }
    }
}
