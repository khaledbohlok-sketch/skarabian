<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Resources\Registry;
use App\Services\BackupService;
use App\Services\Cache;
use App\Services\HorseCardsImport;
use App\Services\HorseImport;
use App\Services\HorseService;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\PedigreeImportService;
use App\Services\PrintoutService;
use App\Services\QrCode;

/** Horse actions beyond plain editing. Every action checks permissions on the server. */
class HorsesController extends Controller
{
    private function horse(string $id, string $module = 'horses', string $action = 'view'): array
    {
        Auth::requirePerm($module, $action);
        $h = DB::row('SELECT * FROM horses WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$h) {
            Response::notFound();
        }
        if (!Auth::canSeeHorse((int) $h['id'])) {
            Auth::deny('horses.scope');
        }
        return $h;
    }

    /** Printable QR sticker for the stable door. Scanning opens the horse's portal page (after login). */
    public function qr(string $id): void
    {
        $h = $this->horse($id, 'horses', 'print');
        Audit::log('print', 'horses', 'horse', $h['id'], null, null, 'QR sticker');
        $this->view('portal/horses/qr', ['title' => __('horses.qr_sticker'), 'horses' => [$h]], 'bare');
    }

    public function qrSheet(): void
    {
        Auth::requirePerm('horses', 'print');
        $params = [];
        $where = "deleted_at IS NULL AND is_external = 0 AND status IN ('active','in_shelter')";
        if (Auth::horseScopeAssigned()) {
            $where .= ' AND id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'a', $params);
        }
        $horses = DB::all("SELECT * FROM horses WHERE $where ORDER BY name_en", $params);
        Audit::log('print', 'horses', 'horse', null, null, ['count' => count($horses)], 'QR sticker sheet');
        $this->view('portal/horses/qr', ['title' => __('nav.qr_sheet'), 'horses' => $horses], 'bare');
    }

    /** Target of the stable-door QR code: a phone-friendly page with the quick actions. */
    public function scan(string $id): void
    {
        Auth::requireLogin();
        $h = $this->horse($id);
        $this->redirect('/portal/horses/' . $h['id'] . (Auth::can('horse_diet', 'create') ? '?tab=diet' : ''));
    }

    public function pedigree(string $id): void
    {
        $h = $this->horse($id);
        $this->redirect('/portal/studio/new/profile?record_id=' . $h['id']);
    }

    /** The full 5-generation (62-ancestor) pedigree editor: one screen for the whole tree. */
    public function pedigreeEdit(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        if (Request::isPost()) {
            $tree = [];
            foreach (HorseService::pedigreePaths() as $path) {
                $pick = (string) Request::post('pick_' . $path);
                $newName = trim((string) Request::post('new_' . $path . '_en'));
                $touched = (string) Request::post('touched_' . $path) === '1';
                if ($newName !== '') {
                    $tree[$path] = ['name_en' => $newName, 'name_ar' => (string) Request::post('new_' . $path . '_ar'), 'country' => (string) Request::post('new_' . $path . '_country')];
                } elseif ($pick !== '') {
                    $tree[$path] = (int) $pick;
                } elseif ($touched) {
                    $tree[$path] = null;
                }
            }
            try {
                $result = HorseService::savePedigree((int) $h['id'], $tree, true); // the editor's boxes: only what changed is written
                Cache::bump();
                $msg = __('horses.pedigree_saved') . ' ' . __('horses.pedigree_complete', ['n' => $result['completeness'][0], 'total' => $result['completeness'][1]]);
                if ($result['inbreeding']) {
                    $msg .= ' ' . __('horses.pedigree_inbreeding', ['n' => $result['inbreeding']]);
                }
                $this->flash('success', $msg);
            } catch (\DomainException $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('/portal/horses/' . $h['id'] . '/pedigree-edit');
        }
        $tree = HorseService::pedigree((int) $h['id'], HorseService::PEDIGREE_GENERATIONS);
        $this->view('portal/horses/pedigree_edit', [
            'title' => __('horses.pedigree_editor_title', ['name' => $h['name_en']]), 'h' => $h, 'tree' => $tree, 'scripts' => ['js/pedigree.js'],
        ]);
    }

    /** JSON: the immediate sire/dam of a horse, used to auto-fill the next generation when picking an ancestor. */
    public function ancestors(string $id): void
    {
        Auth::requirePerm('horses', 'edit');
        if (!Auth::canSeeHorse((int) $id)) {
            Auth::deny('horses.scope');
        }
        $t = HorseService::pedigree((int) $id, 1);
        $fmt = fn (?array $n) => $n ? ['id' => (int) $n['id'], 'name' => $n['name_en'], 'is_external' => (bool) $n['is_external']] : null;
        Response::json(['sire' => $fmt($t['sire'] ?? null), 'dam' => $fmt($t['dam'] ?? null)]);
    }

    public function pedigreeImport(): void
    {
        Auth::requirePerm('horses', 'edit');
        if (Request::isPost()) {
            $file = $_FILES['file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $this->flash('error', __('files.upload_failed'));
                $this->redirect('/portal/horses/pedigree-import');
            }
            [$updated, $errors] = PedigreeImportService::run($file['tmp_name'], (string) ($file['name'] ?? ''));
            Cache::bump();
            if ($updated) {
                $this->flash('success', __('horses.pedigree_import_done', ['n' => $updated]));
            }
            foreach (array_slice($errors, 0, 20) as $err) {
                $this->flash('error', $err);
            }
            $this->redirect('/portal/horses');
        }
        $this->view('portal/horses/pedigree_import', ['title' => __('horses.pedigree_import_title')]);
    }

    /**
     * Owner: loads the horse information cards of 7 October 2026 (see HorseCardsImport). The page shows exactly what an
     * import would add (the same run, rolled back); importing makes a backup first and is all-or-nothing.
     */
    public function cardsImport(): void
    {
        Auth::requireOwner();
        $back = '/portal/horses/cards-import';
        if (Request::isPost()) {
            if (!HorseCardsImport::hasFile() || !HorseCardsImport::ready()) {
                $this->redirect($back);
            }
            try {
                BackupService::run('before horse cards import');
            } catch (\Throwable $e) {
                \App\Core\ErrorHandler::log($e);
                $this->flash('error', __('cards.no_backup'));
                $this->redirect($back);
            }
            try {
                $r = HorseCardsImport::import((bool) Request::post('publish'));
                $this->flash('success', HorseCardsImport::hasWork($r)
                    ? __('cards.done', ['sk' => $r['horses']['new'], 'others' => $r['others']['new'], 'results' => $r['results']['new'], 'notes' => $r['notes']['new'], 'foals' => $r['embryos']['new'] + $r['carried']['new']])
                    : __('cards.nothing'));
            } catch (\Throwable $e) {
                \App\Core\ErrorHandler::log($e);
                $this->flash('error', __('cards.failed') . ' ' . $e->getMessage());
            }
            $this->redirect($back);
        }
        $ready = HorseCardsImport::hasFile() && HorseCardsImport::ready();
        $this->view('portal/horses/cards_import', [
            'title' => __('cards.title'), 'hasFile' => HorseCardsImport::hasFile(), 'ready' => $ready,
            'preview' => $ready ? HorseCardsImport::preview() : null, 'done' => HorseCardsImport::done(),
        ]);
    }

    /**
     * Horses → Import from Excel. GET: the template and the upload form or, once a file is uploaded, exactly what the
     * import will do (HorseImport::preview: nothing is saved). POST: the upload, kept under storage/ until the import.
     */
    public function import(): void
    {
        $this->importAllowed();
        if (Request::isPost()) {
            HorseImport::discard($this->importState());
            Session::forget('horse_import');
            try {
                Session::set('horse_import', HorseImport::store($_FILES['file'] ?? []));
            } catch (\DomainException $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('/portal/horses/import');
        }
        $state = $this->importState();
        $preview = $error = null;
        if ($state) {
            try {
                $preview = HorseImport::preview($state);
            } catch (\Throwable $e) {
                // a file that cannot be checked is dropped, so the page always opens again for another file
                $error = $e instanceof \DomainException ? $e->getMessage() : __('himport.failed_preview', ['ref' => \App\Core\ErrorHandler::log($e)]);
                HorseImport::discard($state);
                Session::forget('horse_import');
                $state = null;
            }
        }
        $result = Session::get('horse_import_result');
        Session::forget('horse_import_result');
        $this->view('portal/horses/import', [
            'title' => __('himport.title'), 'state' => $state, 'preview' => $preview, 'error' => $error, 'result' => is_array($result) ? $result : null,
            'columns' => HorseImport::columns(), 'canExport' => Auth::can('horses', 'export'), 'canPedigree' => Auth::can('horses', 'edit'),
        ]);
    }

    /** The Excel template (?lang=en|ar); ?with=horses adds the stud's horses to complete their details — an export, so logged. */
    public function importTemplate(): void
    {
        $this->importAllowed();
        $lang = in_array(Request::query('lang'), ['en', 'ar'], true) ? (string) Request::query('lang') : \App\Core\Lang::current();
        $with = Request::query('with') === 'horses';
        if ($with) {
            Auth::requirePerm('horses', 'export');
        }
        $count = 0;
        $data = HorseImport::template($lang, $with, $count);
        if ($with) {
            Audit::log('export', 'horses', 'horse', null, null, ['format' => 'xlsx', 'rows' => $count, 'for' => 'import template'], 'Horses for the Excel import (' . $count . ')');
        }
        Response::download($data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ($with ? 'sk-horses-' . date('Y-m-d') : 'sk-horses-import-template') . ($lang === 'ar' ? '-ar' : '') . '.xlsx');
    }

    /** The import itself: a backup first, then one transaction (all or nothing). */
    public function importRun(): void
    {
        $this->importAllowed();
        $back = '/portal/horses/import';
        $state = $this->importState();
        if (!HorseImport::path($state)) {
            Session::forget('horse_import');
            $this->flash('error', __('himport.expired'));
            $this->redirect($back);
        }
        try {
            BackupService::run('before horse import');
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
            $this->flash('error', __('himport.no_backup'));
            $this->redirect($back);
        }
        try {
            $report = HorseImport::import($state, ['publish' => (bool) Request::post('publish')]);
            HorseImport::discard($state);
            Session::forget('horse_import');
            Session::set('horse_import_result', HorseImport::summary($report));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('error', __('himport.failed', ['ref' => \App\Core\ErrorHandler::log($e)]));
        }
        $this->redirect($back);
    }

    /** "Upload a different file": the waiting upload is deleted. */
    public function importCancel(): void
    {
        $this->importAllowed();
        HorseImport::discard($this->importState());
        Session::forget('horse_import');
        $this->redirect('/portal/horses/import');
    }

    /** Whoever may add horses — and is not limited to assigned horses: the import looks at every horse to find duplicates. */
    private function importAllowed(): void
    {
        Auth::requirePerm('horses', 'create');
        if (Auth::horseScopeAssigned()) {
            Auth::deny('horses.create');
        }
    }

    private function importState(): ?array
    {
        $s = Session::get('horse_import');
        return is_array($s) ? $s : null;
    }

    /** One tap "fed" from the diet plan: logs the planned feed, stock goes down, cost added to the horse. */
    public function quickFeed(string $id): void
    {
        $h = $this->horse($id, 'horse_diet', 'create');
        $plan = DB::row('SELECT * FROM diet_plans WHERE id = ? AND horse_id = ? AND deleted_at IS NULL', [(int) Request::post('plan_id'), $h['id']]);
        if (!$plan) {
            Response::notFound();
        }
        try {
            DB::transaction(function () use ($h, $plan) {
                $logId = DB::insert('diet_logs', [
                    'horse_id' => $h['id'], 'log_date' => date('Y-m-d'), 'feeding' => $plan['feeding'], 'item_id' => $plan['item_id'],
                    'quantity' => $plan['quantity'], 'notes' => $plan['notes'], 'created_by' => Auth::id(),
                ]);
                InventoryService::syncUsage('diet', $logId, (int) $h['id'], (int) $plan['item_id'], (float) $plan['quantity'], date('Y-m-d'));
                Audit::log('create', 'horse_diet', 'diet_log', $logId, null, ['horse_id' => $h['id'], 'item_id' => $plan['item_id'], 'quantity' => $plan['quantity']], 'Feeding logged');
            });
            $this->flash('success', __('diet.logged'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        Cache::bump();
        $this->redirect('/portal/horses/' . $h['id'] . '?tab=diet');
    }

    public function assign(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        $emp = (int) Request::post('employee_id');
        if (!DB::value('SELECT 1 FROM employees WHERE id = ? AND deleted_at IS NULL', [$emp])) {
            $this->flash('error', __('validation.pick_from_list'));
            $this->back();
        }
        DB::run('INSERT IGNORE INTO horse_assignments (horse_id, employee_id) VALUES (?, ?)', [$h['id'], $emp]);
        Audit::log('update', 'horses', 'horse', $h['id'], null, ['assigned_employee' => $emp], 'Staff assigned');
        $this->flash('success', __('common.saved'));
        $this->back();
    }

    public function unassign(string $id, string $empId): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        DB::run('DELETE FROM horse_assignments WHERE horse_id = ? AND employee_id = ?', [$h['id'], (int) $empId]);
        Audit::log('update', 'horses', 'horse', $h['id'], ['assigned_employee' => (int) $empId], null, 'Staff unassigned');
        $this->back();
    }

    public function requestWebsite(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        HorseService::requestWebsite((int) $h['id']);
        $this->flash('success', __(Auth::isOwner() ? 'common.saved' : 'approvals.sent'));
        $this->back();
    }

    public function recalc(): void
    {
        Auth::requirePerm('horses', 'edit');
        $n = HorseService::recalcCategories();
        $this->flash('success', __('horses.recalculated', ['n' => $n]));
        $this->redirect('/portal/horses');
    }

    /** Record a foaling from the mare (optionally a specific breeding record). */
    public function foaling(string $id): void
    {
        $mare = $this->horse($id, 'horse_breeding', 'create');
        if ($mare['sex'] !== 'female') {
            Response::notFound();
        }
        $records = DB::all("SELECT b.*, s.name_en AS stallion, e.code FROM breeding_records b LEFT JOIN horses s ON s.id = b.stallion_id LEFT JOIN embryos e ON e.id = b.embryo_id
            WHERE b.mare_id = ? AND b.status IN ('open','pregnant') AND b.deleted_at IS NULL ORDER BY b.start_date DESC", [$mare['id']]);
        if (Request::isPost()) {
            $brId = (int) Request::post('breeding_record_id') ?: null;
            // the sire picker counts only with "No breeding record" (a record brings its own stallion)
            $this->saveFoaling(['breeding_record_id' => $brId, 'mare_id' => (int) $mare['id'], 'stallion_id' => $brId ? null : ((int) Request::post('stallion_id') ?: null)], '/portal/horses/' . $mare['id'] . '/foaling');
        }
        $this->view('portal/horses/foaling', ['title' => __('horses.record_foaling') . ': ' . $mare['name_en'], 'mare' => $mare, 'records' => $records, 'embryo' => null]);
    }

    /** Record a foaling from an embryo (the foal's dam is the donor mare, not the recipient). */
    public function embryoFoaling(string $id): void
    {
        Auth::requirePerm('embryos', 'edit');
        $e = DB::row('SELECT * FROM embryos WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$e) {
            Response::notFound();
        }
        if (Request::isPost()) {
            $this->saveFoaling(['embryo_id' => (int) $e['id']], '/portal/embryos/' . $e['id'] . '/foaling');
        }
        $mare = DB::row('SELECT * FROM horses WHERE id = ?', [$e['recipient_mare_id'] ?: $e['donor_mare_id']]);
        $this->view('portal/horses/foaling', ['title' => __('horses.record_foaling') . ': ' . $e['code'], 'mare' => $mare, 'records' => [], 'embryo' => $e]);
    }

    private function saveFoaling(array $source, string $back): never
    {
        $dob = (string) Request::post('dob');
        $sex = (string) Request::post('sex');
        $errors = [];
        if (!self::isDate($dob) || $dob > date('Y-m-d')) {
            $errors['dob'] = __('validation.date');
        }
        if (!in_array($sex, ['male', 'female'], true)) {
            $errors['sex'] = __('validation.required');
        }
        $colorId = (int) Request::post('color_id') ?: null;
        if ($colorId && !DB::value("SELECT 1 FROM lookups WHERE id = ? AND type = 'color'", [$colorId])) {
            $colorId = null;
        }
        $locId = (int) Request::post('location_id') ?: null;
        if ($locId && !DB::value("SELECT 1 FROM lookups WHERE id = ? AND type = 'location'", [$locId])) {
            $locId = null;
        }
        if ($errors) {
            Session::errors($errors);
            Session::keepOld($_POST);
            $this->redirect($back);
        }
        try {
            $id = HorseService::recordFoaling($source, [
                'name_en' => mb_substr((string) Request::post('name_en'), 0, 120), 'name_ar' => mb_substr((string) Request::post('name_ar'), 0, 120) ?: null,
                'dob' => $dob, 'sex' => $sex, 'color_id' => $colorId, 'location_id' => $locId, 'notes' => mb_substr((string) Request::post('notes'), 0, 2000) ?: null,
                'website' => (bool) Request::post('website'),
            ]);
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($back);
        }
        $this->flash('success', __('horses.foal_created'));
        $this->redirect('/portal/horses/' . $id);
    }

    /**
     * Transfer of ownership (sale, purchase, gift, lease, breeding share): closes the current owner in the history,
     * opens the new one, updates the horse's owner and status and optionally creates the bill — HorseService::transferOwnership().
     */
    /** The favourite star: marks or unmarks the horse (favourites come first in the lists, and lead on the website). */
    public function favorite(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        $on = $h['is_favorite'] ? 0 : 1;
        DB::update('horses', ['is_favorite' => $on, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $h['id']]);
        Audit::log('update', 'horses', 'horse', (int) $h['id'], ['is_favorite' => (int) $h['is_favorite']], ['is_favorite' => $on],
            ($on ? 'Marked as favourite: ' : 'Removed from favourites: ') . $h['name_en']);
        Cache::bump();
        if (Request::wantsJson()) {
            Response::json(['ok' => true, 'on' => (bool) $on]);
        }
        $this->flash('success', __($on ? 'horses.favorited' : 'horses.unfavorited', ['name' => $h['name_en']]));
        $this->back('/portal/horses');
    }

    public function transfer(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        if (!HorseService::ownershipAvailable() || $h['is_external'] || $h['status'] === 'deceased') {
            Response::notFound();
        }
        $back = '/portal/horses/' . $h['id'] . '/transfer';
        if (Request::isPost()) {
            $owner = (string) Request::post('owner_type');
            // "A client" with nothing picked but a name typed: an owner who is not in Clients
            if ($owner === 'client' && !(int) Request::post('owner_party_id') && trim((string) Request::post('owner_name')) !== '') {
                $owner = 'other';
            }
            try {
                $r = HorseService::transferOwnership((int) $h['id'], [
                    'owner_type' => $owner, 'owner_party_id' => Request::post('owner_party_id'), 'owner_name' => Request::post('owner_name'),
                    'date' => Request::post('date'), 'transfer_type' => Request::post('transfer_type'), 'status' => (string) Request::post('status'),
                    'price' => Request::post('price'), 'currency' => Request::post('currency', Money::BASE), 'create_bill' => (bool) Request::post('create_bill'),
                    'notes' => Request::post('notes'),
                ]);
            } catch (ValidationException $e) {
                Session::errors($e->errors);
                Session::keepOld($_POST);
                $this->flash('error', __('validation.fix_errors'));
                $this->redirect($back);
            } catch (\DomainException $e) {
                Session::keepOld($_POST);
                $this->flash('error', $e->getMessage());
                $this->redirect($back);
            }
            $msg = __('ownership.done', ['status' => __('horse.status_' . $r['status'])]);
            if ($r['bill_id']) {
                $msg .= ' ' . __($r['bill_status'] === 'pending' ? 'ownership.bill_pending' : 'ownership.bill_created', ['number' => (string) DB::value('SELECT number FROM bills WHERE id = ?', [$r['bill_id']])]);
            } elseif (Request::post('create_bill') && !Auth::can('finance', 'create')) {
                $msg .= ' ' . __('ownership.bill_no_permission');
            }
            $this->flash('success', $msg);
            $this->redirect('/portal/horses/' . $h['id'] . '?tab=ownership');
        }
        $rows = HorseService::ownership((int) $h['id']);
        $this->view('portal/horses/transfer', [
            'title' => __('ownership.transfer') . ': ' . $h['name_en'], 'h' => $h, 'current' => $rows[0] ?? null,
            'currencies' => Money::currencies(), 'canBill' => Auth::can('finance', 'create'),
            'canPrice' => Auth::can('horses', 'sensitive') || Auth::can('finance', 'sensitive'),
        ]);
    }

    /** Printable certificate of a transfer of ownership, on the letterhead with a reference and verification QR code (PrintoutService). */
    public function ownershipCertificate(string $id, string $oid): void
    {
        $h = $this->horse($id, 'horses', 'print');
        $rows = HorseService::ownership((int) $h['id']);
        $o = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === (int) $oid) {
                $o = $r;
            }
        }
        if (!$o || !$o['previous']) {
            Response::notFound(); // only a change of owner has a certificate (not the opening / birth record)
        }
        $prev = $o['previous'];
        $look = fn ($lid) => $lid ? loc(DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$lid]) ?: [], 'value') : '';
        $name = fn ($hid) => $hid ? (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$hid]) : '';
        $price = ($o['price'] !== null && (Auth::can('horses', 'sensitive') || Auth::can('finance', 'sensitive')));
        $doc = PrintoutService::doc(__('ownership.certificate_title'));
        $doc['hide_count'] = true;
        $doc['count'] = 1;
        $doc['period'] = $h['name_en'] . ($h['name_ar'] ? ' — ' . $h['name_ar'] : '');
        $doc['cards'] = [[__('ownership.date'), fmt_date($o['from_date']), false], [__('ownership.transfer_type'), __('ownership.type_' . $o['transfer_type']), false]];
        $kv = fn (array $pairs) => array_values(array_map(fn ($k, $v) => [$k, $v], array_keys($pairs), $pairs));
        $ident = array_filter([
            __('horses.name_en') => $h['name_en'], __('horses.name_ar') => (string) $h['name_ar'], __('horse.sex') => __('horse.sex_' . $h['sex']),
            __('horses.dob') => $h['dob'] ? fmt_date($h['dob']) : '', __('horse.color') => $look($h['color_id']), __('horse.breed') => $look($h['breed_id']),
            __('horse.sire') => $name($h['sire_id']), __('horse.dam') => $name($h['dam_id']),
            __('horses.registration_no') => (string) $h['registration_no'], __('horses.microchip') => (string) $h['microchip'],
            __('horses.ueln') => (string) ($h['ueln'] ?? ''), __('horses.passport_no') => (string) $h['passport_no'],
        ], fn ($v) => $v !== '');
        $from = HorseService::ownerLabel($prev);
        $to = HorseService::ownerLabel($o);
        $deal = [__('ownership.from') => $from, __('ownership.to') => $to, __('ownership.date') => fmt_date($o['from_date']), __('ownership.transfer_type') => __('ownership.type_' . $o['transfer_type'])];
        if ($price) {
            $deal[__('ownership.price')] = money($o['price'], $o['currency'], false) . ($o['currency'] !== Money::BASE ? ' (' . money($o['price_qar'], 'QAR', false) . ')' : '');
        }
        if ($o['bill_number'] && Auth::can('finance')) {
            $deal[__('bills.singular')] = $o['bill_number'];
        }
        $cols = [[__('ownership.field'), 's', ''], [__('ownership.value'), 's', '']];
        $doc['sections'] = [
            ['title' => __('ownership.horse_details'), 'cols' => $cols, 'rows' => $kv($ident)],
            ['title' => __('ownership.transfer_details'), 'note' => __('ownership.declaration', ['horse' => $h['name_en'], 'from' => $from, 'to' => $to, 'date' => fmt_date($o['from_date'])]), 'cols' => $cols, 'rows' => $kv($deal)],
            ['title' => __('ownership.signatures'), 'cols' => [[__('ownership.party'), 's', ''], [__('common.name'), 's', ''], [__('ownership.signature'), 's', ''], [__('common.date'), 's', '']],
             'rows' => [[__('ownership.transferor'), $from, '', ''], [__('ownership.transferee'), $to, '', ''], [__('ownership.for_sk'), '', '', '']]],
        ];
        if ($o['notes']) {
            $doc['meta'] = [[__('common.notes'), (string) $o['notes']]];
        }
        $stamp = PrintoutService::issue('ownership', 'horses', $doc, $price, PrintoutService::sourceUrl());
        Audit::log('print', 'horses', 'horse', $h['id'], null, ['ownership_id' => (int) $o['id'], 'ref' => $stamp['ref'] ?? null], 'Transfer of ownership certificate: ' . $h['name_en'] . ($stamp ? ' — ' . $stamp['ref'] : ''));
        PrintoutService::output($doc, $stamp);
    }

    public function addCheck(string $id): void
    {
        Auth::requirePerm('horse_breeding', 'create');
        $br = DB::row('SELECT * FROM breeding_records WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$br || !Auth::canSeeHorse((int) $br['mare_id'])) {
            Response::notFound();
        }
        $date = (string) Request::post('check_date');
        $result = (string) Request::post('result');
        if (!self::isDate($date) || !in_array($result, ['scheduled', 'positive', 'negative', 'inconclusive'], true)) {
            $this->flash('error', __(self::isDate($date) ? 'validation.fix_errors' : 'validation.date'));
            $this->back();
        }
        DB::transaction(function () use ($br, $date, $result) {
            $cid = DB::insert('pregnancy_checks', [
                'breeding_record_id' => $br['id'], 'check_date' => $date, 'result' => $result,
                'days_pregnant' => Request::post('days_pregnant') !== '' ? max(0, min(400, (int) Request::post('days_pregnant'))) : null,
                'notes' => mb_substr((string) Request::post('notes'), 0, 255) ?: null, 'created_by' => Auth::id(),
            ]);
            // Result updates the pregnancy status (and the embryo, if any)
            $status = match ($result) { 'positive' => 'pregnant', 'negative' => 'not_pregnant', default => null };
            if ($status) {
                DB::update('breeding_records', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
                if ($br['embryo_id']) {
                    DB::update('embryos', ['status' => $status === 'pregnant' ? 'pregnant' : 'failed'], "id = :id AND status IN ('transferred','pregnant')", ['id' => $br['embryo_id']]);
                }
            }
            Audit::log('create', 'horse_breeding', 'breeding', $br['id'], null, ['check_id' => $cid, 'result' => $result, 'date' => $date], 'Pregnancy check');
        });
        Cache::bump();
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/breeding-records/' . $br['id']);
    }

    /** A real calendar day as YYYY-MM-DD: the pattern alone lets 2026-02-30 through to the database, which refuses it. */
    private static function isDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }
}
