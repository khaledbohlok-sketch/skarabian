<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\DocumentMail;
use App\Services\FinancialReportService;
use App\Services\Pickers;
use App\Services\PrintoutService;
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
    private const FIN = 'fin';
    /** A Financial Report's own settings, never taken from (or kept in) its template: each report starts on this month, in the user's language. */
    private const FIN_PER_REPORT = ['lang', 'periodMode', 'periodMonth', 'periodFrom', 'periodTo', 'rows'];

    /** Letterhead details, image paths and the few interface texts the Studio script shows. */
    private function lh(): array
    {
        return [
            'i18n' => ['refPending' => __('studio.ref_pending'), 'refPh' => __('studio.ref_placeholder'), 'preparing' => __('studio.preparing'),
                       'pdfFailed' => __('studio.pdf_failed'), 'verifyAt' => __('studio.verify_at'),
                       'missingFields' => __('studio.missing_fields'), 'missingSubject' => __('studio.missing_subject'), 'pedigreeWarn' => __('studio.pedigree_incomplete_warn'),
                       'sumType' => __('studio.type'), 'sumRecord' => __('studio.fill_from'), 'sumLetterhead' => __('studio.letterhead'), 'sumLang' => __('inbox.language'),
                       'draftSaved' => __('studio.draft_saved'), 'confirmIssueFin' => __('studio.confirm_issue_fin'), 'finNotDraft' => __('studio.fin_not_draft'),
                       'finAddBills' => __('studio.fin_add_bills'), 'finMonthlyReport' => __('studio.fin_monthly_report'), 'finRefresh' => __('studio.fin_refresh'),
                       'finRefreshed' => __('studio.fin_refreshed'), 'finRefreshChanged' => __('studio.fin_refresh_changed'), 'finNoChanges' => __('studio.fin_no_changes'),
                       'finRemoveConfirm' => __('studio.fin_remove_line_confirm'), 'finPickerTitle' => __('studio.fin_picker_title'),
                       'finSelectAllFiltered' => __('studio.fin_select_all_filtered'), 'finClearSelection' => __('studio.fin_clear_selection'),
                       'finAddSelected' => __('studio.fin_add_selected'), 'finAlreadyAdded' => __('studio.fin_already_added'), 'finShowCancelled' => __('studio.fin_show_cancelled'),
                       'finAddManual' => __('studio.fin_add_manual'), 'finManualLine' => __('studio.fin_manual_line'),
                       'finManualNameEn' => __('studio.fin_manual_name_en'), 'finManualNameAr' => __('studio.fin_manual_name_ar'),
                       'finReportKind' => __('studio.fin_report_kind'), 'finKindOutstanding' => __('studio.fin_kind_outstanding'), 'finKindExpenses' => __('studio.fin_kind_expenses'),
                       'finKindIncome' => __('studio.fin_kind_income'), 'finKindFull' => __('studio.fin_kind_full'), 'finPeriod' => __('studio.fin_period'),
                       'finPeriodMonth' => __('studio.fin_period_month'), 'finPeriodCustom' => __('studio.fin_period_custom'), 'finPeriodFrom' => __('studio.fin_period_from'),
                       'finPeriodTo' => __('studio.fin_period_to'), 'finGroupBy' => __('studio.fin_group_by'), 'finGroupNone' => __('studio.fin_group_none'),
                       'finGroupCategory' => __('studio.fin_group_category'), 'finGroupParty' => __('studio.fin_group_party'), 'finGroupType' => __('studio.fin_group_type'),
                       'finNoBills' => __('studio.fin_no_bills'), 'finLoading' => __('studio.fin_loading'),
                       // the editor's own screen texts (preview tabs, zoom, PDF ready)
                       'tabForm' => __('studio.form'), 'tabPreview' => __('studio.preview'), 'tabDetails' => __('studio.tab_details'), 'zoomIn' => __('studio.zoom_in'),
                       'zoomOut' => __('studio.zoom_out'), 'zoomFit' => __('studio.zoom_fit'), 'pdfReady' => __('studio.pdf_ready'), 'pdfDownload' => __('studio.pdf_download'),
                       'close' => __('common.close')],
            'csrf' => csrf_token(), 'logo' => asset('img/sk-logo.png'), 'mark' => asset('img/sk-mark.png'),
            // the DOCUMENT contact details (not the website's): Website → Contact details → "on documents"
            'lh' => ['cr' => doc_contact('cr'), 'mob' => doc_contact('mobile'), 'email' => doc_contact('email'),
                     'pobox' => doc_contact('po_box') . ', ' . doc_contact('city')],
            // the editor's form labels are written in English; in Arabic it swaps them using this list
            'uiAr' => lang() === 'ar' ? require APP_ROOT . '/lang/studio-ui-ar.php' : null,
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
        $type = (string) Request::query('type', '');
        $show = (string) Request::query('show', '');
        $show = in_array($show, ['void', 'all'], true) ? $show : ''; // valid ones by default; voided ones stay in the archive
        $q = trim((string) Request::query('q', ''));
        $per = in_array((int) Request::query('per'), [25, 50, 100], true) ? (int) Request::query('per') : 25; // the list's 25 / 50 / 100 buttons
        [$sql, $p] = $this->issuedSql($type, $q, $show);
        $total = (int) DB::value("SELECT COUNT(*) FROM ($sql) x", $p);
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, (int) Request::query('page', 1)), $pages);
        $docs = DB::all("SELECT * FROM ($sql) x ORDER BY issued_at DESC, id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        [$vSql, $vp] = $this->issuedSql($type, $q, 'void');
        $voided = (int) DB::value("SELECT COUNT(*) FROM ($vSql) x", $vp);
        $drafts = Studio::canCreate(self::FIN) ? FinancialReportService::draftsFor() : [];
        $this->view('portal/studio/index', ['title' => __('nav.studio'), 'docs' => $docs, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'groups' => $this->types(), 'drafts' => $drafts,
            'show' => $show, 'voided' => $voided, 'canVoid' => Auth::isOwner() || Auth::can('studio', 'delete'), 'printouts' => PrintoutService::available(),
            'styles' => ['css/studio-ed.css'], 'scripts' => ['js/studio-home.js']]); // the gallery's look and its search (presentation only)
    }

    /**
     * The issued documents: Studio documents and the printed lists and reports (they carry a reference and QR code
     * too), as one list. Drafts have no reference yet: they are listed on their own. $type '' = both, 'printout' =
     * printouts only, a Studio type = that type. $show '' = valid, 'void', 'all'. Returns [SQL, parameters].
     */
    private function issuedSql(string $type, string $q, string $show): array
    {
        $p = [];
        $void = fn (string $col) => match ($show) { 'all' => '1 = 1', 'void' => "$col = 1", default => "$col = 0" };
        $parts = [];
        if ($type !== 'printout') {
            // only document types whose module the user may see
            $visible = array_keys(array_filter(StudioDocs::TYPES, fn ($d, $t) => Auth::isOwner() || (Auth::can($d['module'], 'view')
                && (!in_array($t, StudioDocs::SENSITIVE, true) || Auth::can($d['module'], 'sensitive'))), ARRAY_FILTER_USE_BOTH));
            $w = ['d.deleted_at IS NULL', "d.status <> 'draft'", 'd.doc_type IN ' . DB::in($visible ?: ['-'], 'v', $p), $void('d.is_void')];
            if ($type !== '' && StudioDocs::exists($type)) {
                $w[] = 'd.doc_type = :t';
                $p['t'] = $type;
            }
            if ($q !== '') {
                $w[] = '(d.ref_no LIKE :q1 OR d.title LIKE :q2)';
                $p['q1'] = $p['q2'] = '%' . $q . '%';
            }
            $parts[] = "SELECT 'doc' AS src, d.id, d.ref_no, d.title, d.lang, d.doc_type AS kind, d.doc_date AS ddate, d.created_at AS issued_at, d.is_void, u.name AS author, NULL AS token
                FROM documents d LEFT JOIN users u ON u.id = d.created_by WHERE " . implode(' AND ', $w);
        }
        if (($type === '' || $type === 'printout') && PrintoutService::available()) {
            $isVoid = PrintoutService::hasVoid() ? 'p.is_void' : '0';
            $w = array_merge(PrintoutService::visibleWhere($p), [$void($isVoid)]);
            if ($q !== '') {
                $w[] = '(p.ref_no LIKE :q3 OR p.title LIKE :q4)';
                $p['q3'] = $p['q4'] = '%' . $q . '%';
            }
            $parts[] = "SELECT 'prt' AS src, p.id, p.ref_no, p.title, p.lang, p.kind, DATE(p.printed_at) AS ddate, p.printed_at AS issued_at, $isVoid AS is_void, u.name AS author, p.verify_token AS token
                FROM printouts p LEFT JOIN users u ON u.id = p.printed_by WHERE " . implode(' AND ', $w);
        }
        return [$parts ? implode(' UNION ALL ', $parts) : "SELECT 'doc' AS src, 0 AS id, '' AS ref_no, '' AS title, '' AS lang, '' AS kind, NULL AS ddate, NULL AS issued_at, 0 AS is_void, NULL AS author, NULL AS token FROM DUAL WHERE 1 = 0", $p];
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
        if ($type === self::FIN) {
            // A Financial Report is built from picked bills, not typed by hand: it needs a document row to
            // attach bills to from the very first moment, so "new" starts a draft and hands off to show()
            // (which renders the same editor for any 'fin' document still in draft status). The user's empty
            // draft of this month is continued instead, so repeated clicks don't pile up blank drafts.
            if ($id = FinancialReportService::emptyDraftOf((int) Auth::id())) {
                $this->redirect('/portal/studio/' . $id);
            }
            $t = StudioTemplates::get($type);
            $tpl = array_diff_key($t['defaults'] ?? [], array_flip(self::FIN_PER_REPORT));
            $settings = array_merge(['lang' => lang(), 'currency' => 'QAR', 'reportKind' => 'outstanding', 'groupBy' => 'none', '_letterhead' => $t['letterhead'],
                'periodFrom' => date('Y-m-01'), 'periodTo' => date('Y-m-t'), 'titleEn' => '', 'titleAr' => '', 'subEn' => '', 'subAr' => '', 'prepared' => '', 'approved' => ''], $tpl);
            $id = FinancialReportService::createDraft($settings);
            $this->redirect('/portal/studio/' . $id);
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
            // named by the record's own kind: a covering certificate is linked to the breeding record, a receipt to the payment
            $label = $kind && $rid ? (Studio::prefill($type, (string) $kind, $rid)[3] ?? '#' . $rid) : null;
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
                'docTypeLabel' => __('studio.type_' . $type),
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
        $ref = (string) DB::value('SELECT ref_no FROM documents WHERE id = ?', [$id]);
        $this->flash('success', __('studio.issued') . ' ' . __('studio.issued_ref', ['ref' => $ref]));
        $this->redirect('/portal/studio/' . $id);
    }

    // ------------------------------------------------------------------ Horse Profile: several horses at once

    /** The parts of a horse profile ("what to print", as in the editor): key => label. */
    public const PROFILE_PARTS = ['details' => 'studio.part_details', 'photo' => 'studio.part_photo', 'pedigree' => 'studio.part_pedigree',
        'shows' => 'studio.part_shows', 'progeny' => 'studio.part_progeny', 'plan' => 'studio.part_plan', 'covers' => 'studio.part_covers',
        'embryos' => 'studio.part_embryos', 'health' => 'studio.part_health', 'notes' => 'studio.part_notes'];

    /** At most this many profiles are issued and printed together. */
    private const BATCH_MAX = 100;

    /** Horse Profile → "Print several horses": which horses (all, favourites or a choice), what to print, language, letterhead. */
    public function profiles(): void
    {
        Auth::requireLogin();
        $type = 'profile';
        if (!Studio::canCreate($type)) {
            Auth::deny('studio.' . $type);
        }
        if (Request::isPost()) {
            $this->issueProfiles();
        }
        $horses = array_values(array_filter(DB::all("SELECT id, name_en, name_ar, category, dob, is_favorite, main_photo_id FROM horses
            WHERE deleted_at IS NULL AND archived_at IS NULL AND is_external = 0 AND status IN ('active','in_shelter')
            ORDER BY is_favorite DESC, FIELD(category,'stallion','mare','colt','filly','foal'), name_en"), fn ($h) => Auth::canSeeHorse((int) $h['id'])));
        $tpl = StudioTemplates::get('profile');
        $this->view('portal/studio/profiles', [
            'title' => __('studio.profiles_title'), 'horses' => $horses, 'parts' => self::PROFILE_PARTS, 'max' => self::BATCH_MAX,
            'cfg' => ['type' => 'profile', 'tpl' => $tpl['defaults'] ?: new \stdClass(), 'letterhead' => $tpl['letterhead']] + $this->lh(),
            'scripts' => ['js/studio-cfg.js', 'vendor/studio-libs.js', 'js/studio-render.js', 'js/studio-batch.js'],
        ]);
    }

    /**
     * Issues one Horse Profile per ticked horse — each with its own reference and QR code, kept on the horse's Documents
     * tab, exactly as when issued one by one — then opens them together in one print view. The document skeleton
     * ("base") is the editor's own empty profile, built in the browser by studio-batch.js; the horse's values are
     * filled in here from the records, so roles without Studio "edit" print what the records say.
     */
    private function issueProfiles(): never
    {
        $back = '/portal/studio/profiles';
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) Request::post('horses', [])))));
        if (!$ids) {
            $this->flash('error', __('studio.profiles_none_ticked'));
            $this->redirect($back);
        }
        if (count($ids) > self::BATCH_MAX) {
            $this->flash('error', __('studio.profiles_too_many', ['n' => self::BATCH_MAX]));
            $this->redirect($back);
        }
        $raw = (string) Request::post('base');
        $base = strlen($raw) <= 200_000 ? json_decode($raw, true) : null;
        if (!is_array($base) || ($base !== [] && array_is_list($base))) {
            $this->flash('error', __('studio.profiles_needs_js'));
            $this->redirect($back);
        }
        $ticked = array_values(array_intersect(array_keys(self::PROFILE_PARTS), array_map('strval', (array) Request::post('parts', []))));
        if (!$ticked) {
            $this->flash('error', __('studio.profiles_no_parts'));
            $this->redirect($back);
        }
        $hide = array_values(array_diff(array_keys(self::PROFILE_PARTS), $ticked));
        $lang = Request::post('lang') === 'ar' ? 'ar' : 'en';
        $lh = (int) Request::post('letterhead');
        $lh = in_array($lh, [1, 2], true) ? $lh : StudioTemplates::get('profile')['letterhead'];
        $docs = DB::transaction(function () use ($ids, $base, $hide, $lang, $lh) {
            $out = [];
            foreach ($ids as $id) {
                [$prefill, $kind, $rid] = Studio::prefill('profile', 'horse', $id);
                if ($kind === null || !$rid) {
                    continue; // not a horse this user may see
                }
                $out[] = Studio::create('profile', array_merge($base, $prefill, ['lang' => $lang, 'hide' => $hide, '_letterhead' => $lh]), $lang, 'horse', $rid);
            }
            return $out;
        });
        if (!$docs) {
            $this->flash('error', __('studio.profiles_none_ticked'));
            $this->redirect($back);
        }
        Audit::log('create', 'studio', 'document', null, null, ['documents' => $docs, 'parts' => $ticked, 'lang' => $lang], 'Horse profiles issued together: ' . count($docs));
        $this->flash('success', __('studio.profiles_issued', ['n' => count($docs)]));
        $this->redirect('/portal/studio/print-batch?ids=' . implode(',', $docs));
    }

    /** Several issued documents in one print view (e.g. the horse profiles just issued together); each keeps its own reference and QR code. */
    public function printBatch(): void
    {
        Auth::requireLogin();
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string) Request::query('ids', '')))))), 0, self::BATCH_MAX);
        $docs = [];
        foreach ($ids as $id) {
            $d = DB::row("SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL AND status <> 'draft'", [$id]);
            if ($d && (Studio::canView($d) || Studio::isOwnDoc($d))) {
                $docs[] = ['id' => (int) $d['id'], 'type' => $d['doc_type'], 'saved' => json_decode((string) $d['data'], true) ?: new \stdClass(), 'ref' => $d['ref_no'],
                    'issued' => $d['doc_date'], 'letterhead' => (int) $d['letterhead_version'], 'void' => (bool) $d['is_void'],
                    'verifyUrl' => absolute_url('/verify/' . $d['verify_token']), 'logUrl' => url('/portal/studio/' . $d['id'] . '/log'),
                    'ctx' => Studio::context($d['doc_type'], json_decode((string) $d['data'], true) ?: [])];
            }
        }
        if (!$docs) {
            Response::notFound();
        }
        $this->view('portal/studio/print_batch', ['title' => __('studio.profiles_title') . ' (' . count($docs) . ')', 'cfg' => ['docs' => $docs] + $this->lh(),
            'docLang' => is_array($docs[0]['saved']) && ($docs[0]['saved']['lang'] ?? null) === 'ar' ? 'ar' : 'en'], 'bare');
    }

    // ------------------------------------------------------------------ Financial Report (bills-backed draft)

    /** Same editor the "new" flow renders, but loaded from an in-progress draft: its settings (from
     * documents.data) plus its current bill lines (from document_bills) are both handed to the page. */
    private function renderFinDraft(array $d): never
    {
        if (!Studio::canCreate(self::FIN) || !self::ownsDraft($d)) {
            Auth::deny('studio.' . self::FIN);
        }
        $saved = json_decode((string) $d['data'], true) ?: [];
        $this->view('portal/studio/editor', [
            'title' => __('studio.type_fin'), 'styles' => self::STYLES, 'scripts' => self::SCRIPTS, 'bodyClass' => 'studio-body',
            'cfg' => [
                // built from bills by whoever may create a Financial Report (its draft actions ask no more than that)
                'mode' => 'draft', 'type' => self::FIN, 'numbered' => true, 'canEdit' => true,
                'tpl' => new \stdClass(), 'letterhead' => (int) $d['letterhead_version'], 'prefill' => new \stdClass(), 'saved' => $saved,
                'ctx' => Studio::context(self::FIN), 'lang' => lang(), 'saveUrl' => url('/portal/studio/' . $d['id'] . '/save-draft'), 'logUrl' => url('/portal/studio/log'),
                'docTypeLabel' => __('studio.type_fin'), 'docId' => (int) $d['id'], 'finLines' => FinancialReportService::linesFor((int) $d['id']),
                'discardUrl' => Auth::isOwner() || (int) $d['created_by'] === Auth::id() ? url('/portal/studio/' . $d['id'] . '/discard') : null,
                'finApi' => [
                    'bills' => url('/portal/studio/fin/bills'), 'addBills' => url('/portal/studio/' . $d['id'] . '/add-bills'),
                    'addManual' => url('/portal/studio/' . $d['id'] . '/add-manual'), 'removeLine' => url('/portal/studio/' . $d['id'] . '/remove-line'),
                    'reorder' => url('/portal/studio/' . $d['id'] . '/reorder-lines'), 'refresh' => url('/portal/studio/' . $d['id'] . '/refresh-bills'),
                    'issue' => url('/portal/studio/' . $d['id'] . '/issue'),
                ],
                'record' => null,
            ] + $this->lh(),
        ]);
    }

    /** Loads a 'fin' draft the user may edit, or denies/404s. Shared by every AJAX action below. */
    private function finDraft(string $id): array
    {
        Auth::requireLogin();
        if (!Studio::canCreate(self::FIN)) {
            Auth::deny('studio.' . self::FIN);
        }
        $d = DB::row('SELECT * FROM documents WHERE id = ? AND doc_type = ? AND deleted_at IS NULL', [(int) $id, self::FIN]);
        if (!$d) {
            Response::notFound();
        }
        if (!self::ownsDraft($d)) {
            Auth::deny('studio.fin.draft');
        }
        if ($d['status'] !== 'draft') {
            Response::json(['ok' => false, 'error' => __('studio.fin_not_draft')], 409);
        }
        return $d;
    }

    /** A Financial Report draft is changed only by the person who started it, or the Owner. */
    public static function ownsDraft(array $d): bool
    {
        return Auth::isOwner() || (int) $d['created_by'] === Auth::id();
    }

    /** GET /portal/studio/fin/bills — the "Add bills" picker's filtered/paginated rows (JSON). */
    public function finBills(): void
    {
        Auth::requireLogin();
        if (!Studio::canCreate(self::FIN)) {
            Auth::deny('studio.' . self::FIN);
        }
        Response::json(FinancialReportService::billsForPicker($_GET));
    }

    /** Every mutating fin action below replies with the fresh line list (one cheap extra SELECT) so the
     * client can just replace its state wholesale instead of hand-reconciling an optimistic update. */
    public function finAddBills(string $id): void
    {
        $d = $this->finDraft($id);
        $ids = array_map('intval', (array) Request::post('bill_ids', []));
        $result = FinancialReportService::addBills((int) $d['id'], $ids);
        Response::json(['ok' => true] + $result + ['lines' => FinancialReportService::linesFor((int) $d['id'])]);
    }

    public function finAddManual(string $id): void
    {
        $d = $this->finDraft($id);
        $lineId = FinancialReportService::addManualLine((int) $d['id'], [
            'en' => (string) Request::post('en', ''), 'ar' => (string) Request::post('ar', ''), 'amount' => Request::post('amount', 0),
            'type' => (string) Request::post('type', 'expense'), 'status' => (string) Request::post('status', 'pending'),
        ]);
        Response::json(['ok' => true, 'id' => $lineId, 'lines' => FinancialReportService::linesFor((int) $d['id'])]);
    }

    public function finRemoveLine(string $id): void
    {
        $d = $this->finDraft($id);
        FinancialReportService::removeLine((int) $d['id'], (int) Request::post('line_id'));
        Response::json(['ok' => true, 'lines' => FinancialReportService::linesFor((int) $d['id'])]);
    }

    public function finReorder(string $id): void
    {
        $d = $this->finDraft($id);
        FinancialReportService::reorder((int) $d['id'], array_map('intval', (array) Request::post('ids', [])));
        Response::json(['ok' => true, 'lines' => FinancialReportService::linesFor((int) $d['id'])]);
    }

    public function finRefresh(string $id): void
    {
        $d = $this->finDraft($id);
        Response::json(['ok' => true, 'changed' => FinancialReportService::refresh((int) $d['id']), 'lines' => FinancialReportService::linesFor((int) $d['id'])]);
    }

    /** Saves the report's own settings (title/subtitle/period/type/currency/...) without issuing, plus
     * any edits to manual lines' own fields (those live in document_bills, not this settings blob). */
    public function finSaveDraft(string $id): void
    {
        $d = $this->finDraft($id);
        $raw = (string) Request::post('data');
        $data = strlen($raw) <= 2_000_000 ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            Response::json(['ok' => false, 'error' => __('studio.bad_data')], 422);
        }
        $data = $this->finLetterhead($data);
        FinancialReportService::saveDraftSettings((int) $d['id'], $data);
        $manual = is_array($data['rows'] ?? null) ? array_values(array_filter($data['rows'], fn ($r) => !empty($r['isManual']))) : [];
        if ($manual) {
            FinancialReportService::syncManualLines((int) $d['id'], $manual);
        }
        Response::json(['ok' => true]);
    }

    /** The letterhead chosen above the preview, posted beside the report's settings (as store() takes it for the other types). */
    private function finLetterhead(array $data): array
    {
        $lh = (int) Request::post('letterhead');
        if (in_array($lh, [1, 2], true)) {
            $data['_letterhead'] = $lh;
        }
        return $data;
    }

    /** Locks the draft: assigns the reference number, same as every other Studio type gets on its one save. */
    public function finIssue(string $id): void
    {
        $d = $this->finDraft($id);
        $raw = (string) Request::post('data');
        $data = strlen($raw) <= 2_000_000 ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            Response::json(['ok' => false, 'error' => __('studio.bad_data')], 422);
        }
        try {
            $result = FinancialReportService::issue((int) $d['id'], $this->finLetterhead($data), (bool) Request::post('override'));
        } catch (\DomainException $e) {
            // e.g. the same draft issued from two tabs at once: the first one won, this one just reports it
            Response::json(['ok' => false, 'error' => __($e->getMessage())], 409);
        }
        Response::json($result + ['redirect' => $result['ok'] ? url('/portal/studio/' . $d['id']) : null]);
    }

    private function doc(string $id, bool $ownOk = false): array
    {
        Auth::requireLogin();
        $d = DB::row('SELECT d.*, u.name AS author FROM documents d LEFT JOIN users u ON u.id = d.created_by WHERE d.id = ? AND d.deleted_at IS NULL', [(int) $id]);
        if ($d && $ownOk && Studio::isOwnDoc($d)) {
            return $d;
        }
        Auth::requirePerm('studio', 'view');
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
        $saved = json_decode((string) $d['data'], true) ?: [];
        return [
            'mode' => $mode, 'type' => $d['doc_type'], 'id' => (int) $d['id'], 'saved' => $saved ?: new \stdClass(),
            'ref' => $d['ref_no'], 'issued' => $d['doc_date'], 'letterhead' => (int) $d['letterhead_version'], 'void' => (bool) $d['is_void'],
            'verifyUrl' => absolute_url('/verify/' . $d['verify_token']), 'logUrl' => url('/portal/studio/' . $d['id'] . '/log'),
            'fileName' => preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $d['ref_no'] . ' ' . $d['title']), 'ctx' => Studio::context($d['doc_type'], $saved),
        ] + $this->lh();
    }

    public function show(string $id): void
    {
        $d = $this->doc($id);
        if ($d['doc_type'] === self::FIN && $d['status'] === 'draft') {
            $this->renderFinDraft($d);
        }
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
        $d = $this->doc($id, true);
        if ($d['status'] === 'draft') {
            $this->redirect('/portal/studio/' . $d['id']); // no reference yet: it is printed once issued
        }
        $this->view('portal/studio/print', ['title' => $d['ref_no'] . ' — ' . $d['title'], 'cfg' => $this->viewCfg($d, 'print'), 'doc' => $d, 'docLang' => $d['lang']], 'bare');
    }

    /** Print / PDF download / share events are recorded in the Activity Log. */
    public function log(string $id): void
    {
        $d = $this->doc($id, true);
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

    /** E-mails the PDF (made in the browser from the archived document) to one address, in the email of DocumentMail. */
    public function share(string $id): void
    {
        $d = $this->doc($id);
        if (!Studio::canEmail()) {
            Auth::deny('studio.send_email');
        }
        $to = trim((string) Request::post('email'));
        $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) Request::post('name'))), 0, 120); // optional: "Dear …"
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
        $ok = DocumentMail::send($d, $to, $name, preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $d['ref_no']) . '.pdf', (string) file_get_contents($file['tmp_name']));
        Audit::log('share', 'studio', 'document', $d['id'], null, ['via' => 'email', 'to' => $to, 'sent' => $ok], $d['ref_no'] . ' — ' . $d['title']);
        Response::json(['ok' => $ok, 'message' => $ok ? __('studio.mail_sent', ['email' => $to]) : __('studio.mail_failed')]);
    }

    /** Discards a Financial Report draft: its author's or, for the Owner, anyone's. */
    public function discard(string $id): void
    {
        Auth::requireLogin();
        $d = DB::row("SELECT * FROM documents WHERE id = ? AND doc_type = ? AND status = 'draft' AND deleted_at IS NULL", [(int) $id, self::FIN]);
        if (!$d) {
            Response::notFound();
        }
        if (!Studio::canCreate(self::FIN) || (!Auth::isOwner() && (int) $d['created_by'] !== Auth::id())) {
            Auth::deny('studio.' . self::FIN);
        }
        FinancialReportService::discard((int) $d['id']);
        $this->flash('success', __('studio.discarded'));
        $this->redirect('/portal/studio');
    }

    /** Discards the drafts that have no lines yet (the same drafts the Studio page lists for this user). */
    public function discardEmpty(): void
    {
        Auth::requireLogin();
        if (!Studio::canCreate(self::FIN)) {
            Auth::deny('studio.' . self::FIN);
        }
        $n = 0;
        foreach (FinancialReportService::draftsFor() as $d) {
            if (!(int) $d['line_count']) {
                FinancialReportService::discard((int) $d['id']);
                $n++;
            }
        }
        $this->flash('success', __('studio.discarded_n', ['n' => $n]));
        $this->redirect('/portal/studio');
    }

    /** A void document stays in the archive; the verification page says it is no longer valid. Voided from the
     * Studio list, it goes back to that list ("back"); from its own page, back to the page. */
    public function void(string $id): void
    {
        $d = $this->doc($id);
        if (!Auth::isOwner() && !Auth::can('studio', 'delete')) {
            Auth::deny('studio.delete');
        }
        $back = local_path(Request::post('back'), '/portal/studio/' . $d['id']);
        if ($d['is_void']) {
            $this->flash('info', __('studio.already_void'));
            $this->redirect($back);
        }
        DB::update('documents', ['is_void' => 1], 'id = :id', ['id' => $d['id']]);
        Audit::log('void', 'studio', 'document', $d['id'], ['is_void' => 0], ['is_void' => 1, 'reason' => mb_substr((string) Request::post('reason'), 0, 255)], $d['ref_no']);
        $this->flash('success', __('studio.voided_ref', ['ref' => $d['ref_no']]));
        $this->redirect($back);
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
            if ($type === self::FIN) {
                $data = array_diff_key($data, array_flip(self::FIN_PER_REPORT));
            }
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
