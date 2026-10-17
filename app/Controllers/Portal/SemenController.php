<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Resources\Registry;
use App\Services\Cache;
use App\Services\PrintoutService;
use App\Services\SemenStockService;

class SemenController extends Controller
{
    private function stock(string $id): array
    {
        Auth::requireLogin();
        $row = DB::row('SELECT * FROM semen_stock WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$row) {
            Response::notFound();
        }
        if (!Auth::can('semen_stock')) {
            Auth::deny('semen_stock.view');
        }
        return $row;
    }

    public function movementForm(string $id): void
    {
        $row = $this->stock($id);
        $res = Registry::get('semen-stock');
        $this->view('portal/semen/movement', [
            'title' => __('semen_stock.record_movement'), 'row' => $row, 'label' => $res->label($row),
        ]);
    }

    public function recordMovement(string $id): void
    {
        $row = $this->stock($id);
        if (!Auth::can('semen_stock', 'edit')) {
            Auth::deny('semen_stock.edit');
        }
        $type = (string) Request::post('type');
        $date = (string) Request::post('movement_date', date('Y-m-d'));
        // Each section of the form has its own straws field (straws_in / straws_out / straws_transfer): hidden
        // sections are still submitted, so one shared name would always read the last (transfer) box.
        $straws = (int) (Request::post('straws_' . $type) ?? Request::post('straws'));
        try {
            if (!self::isDate($date)) {
                throw new \DomainException(__('validation.date'));
            }
            switch ($type) {
                case 'in':
                    SemenStockService::recordIn((int) $row['id'], $straws, $date, Request::post('notes') ?: null);
                    break;
                case 'out':
                    $mareId = (int) Request::post('mare_id') ?: null;
                    if ($mareId && !\App\Services\Pickers::valid('mares', $mareId)) {
                        throw new \DomainException(__('validation.pick_from_list'));
                    }
                    $doses = (string) Request::post('doses', '');
                    SemenStockService::recordOut((int) $row['id'], $straws, $date, $doses !== '' ? (int) $doses : null,
                        (string) Request::post('recipient'), $mareId, Request::post('notes') ?: null);
                    break;
                case 'transfer':
                    $destCanisterId = (int) Request::post('dest_canister_id');
                    // One transaction: a refused transfer (e.g. not enough straws) also undoes a just-created destination row
                    DB::transaction(function () use ($row, $destCanisterId, $straws, $date) {
                        $destId = SemenStockService::findOrCreateDestination($row, $destCanisterId);
                        SemenStockService::recordTransfer((int) $row['id'], $destId, $straws, $date, Request::post('notes') ?: null);
                    });
                    break;
                case 'adjustment':
                    // an empty box is not a count of 0: it would empty the stock
                    $counted = trim((string) Request::post('counted_total'));
                    if (!preg_match('/^\d+$/', $counted)) {
                        throw new \DomainException(__('semen_stock.counted_total_required'));
                    }
                    SemenStockService::recordAdjustment((int) $row['id'], (int) $counted, $date, (string) Request::post('reason'), Request::post('notes') ?: null);
                    break;
                default:
                    throw new \DomainException(__('validation.invalid'));
            }
        } catch (\DomainException $e) {
            if (Request::wantsJson()) {
                Response::json(['error' => $e->getMessage()], 422);
            }
            $this->flash('error', $e->getMessage());
            $this->redirect('/portal/semen-stock/' . $row['id']);
        }
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/semen-stock/' . $row['id']);
    }

    // ------------------------------------------------------------------ physical count

    public function countForm(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'approve')) {
            Auth::deny('semen_stock.approve');
        }
        $rows = DB::all("SELECT t.*, COALESCE(st.name_en, t.stallion_name_free) AS stallion, tk.name AS tank, c.name AS canister
            FROM semen_stock t LEFT JOIN horses st ON st.id = t.stallion_id JOIN canisters c ON c.id = t.canister_id JOIN tanks tk ON tk.id = c.tank_id
            WHERE t.deleted_at IS NULL AND t.archived_at IS NULL ORDER BY stallion, tank, canister");
        $this->view('portal/semen/count', ['title' => __('semen_stock.count_title'), 'rows' => $rows, 'date' => date('Y-m-d')]);
    }

    public function countSubmit(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'approve')) {
            Auth::deny('semen_stock.approve');
        }
        $date = (string) Request::post('count_date', date('Y-m-d'));
        if (!self::isDate($date)) {
            $this->flash('error', __('validation.date'));
            $this->redirect('/portal/semen-stock/count');
        }
        $counted = $_POST['counted'] ?? [];
        $changed = 0;
        foreach ($counted as $stockId => $value) {
            if ($value === '') {
                continue;
            }
            $stockId = (int) $stockId;
            $newCount = (int) $value;
            $current = (int) DB::value('SELECT straws FROM semen_stock WHERE id = ?', [$stockId]);
            if ($newCount === $current) {
                DB::update('semen_stock', ['report_date' => $date], 'id = :id', ['id' => $stockId]);
                continue;
            }
            try {
                SemenStockService::recordAdjustment($stockId, $newCount, $date, __('semen_stock.count_reason'), null);
                $changed++;
            } catch (\DomainException) {
                // no_change / invalid: skip silently, the row's report_date was already set above when equal
            }
        }
        Cache::bump();
        $this->flash('success', __('semen_stock.count_done', ['n' => $changed]));
        $this->redirect('/portal/semen-stock?report_date=' . $date);
    }

    // ------------------------------------------------------------------ tank map

    public function tankMap(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock')) {
            Auth::deny('semen_stock.view');
        }
        $tanks = DB::all('SELECT * FROM tanks WHERE deleted_at IS NULL ORDER BY name');
        foreach ($tanks as &$tank) {
            $tank['canisters'] = DB::all('SELECT * FROM canisters WHERE tank_id = ? AND deleted_at IS NULL ORDER BY name', [$tank['id']]);
            foreach ($tank['canisters'] as &$can) {
                $can['stock'] = DB::all("SELECT t.*, COALESCE(st.name_en, t.stallion_name_free) AS stallion FROM semen_stock t
                    LEFT JOIN horses st ON st.id = t.stallion_id WHERE t.canister_id = ? AND t.deleted_at IS NULL AND t.archived_at IS NULL AND t.straws > 0", [$can['id']]);
            }
        }
        $this->view('portal/semen/tank_map', ['title' => __('tanks.map'), 'tanks' => $tanks]);
    }

    public function addRefill(string $id): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'edit')) {
            Auth::deny('semen_stock.edit');
        }
        $tank = DB::row('SELECT * FROM tanks WHERE id = ? AND deleted_at IS NULL', [(int) $id]) ?? Response::notFound();
        $date = (string) Request::post('refill_date', date('Y-m-d'));
        if (!self::isDate($date)) {
            $this->flash('error', __('validation.date'));
            $this->redirect('/portal/tanks/' . $tank['id']);
        }
        DB::insert('tank_refills', ['tank_id' => $tank['id'], 'refill_date' => $date, 'notes' => Request::post('notes') ?: null, 'created_by' => Auth::id()]);
        DB::update('tanks', ['last_refill_date' => $date, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $tank['id']]);
        Audit::log('update', 'semen_stock', 'tank', $tank['id'], ['last_refill_date' => $tank['last_refill_date']], ['last_refill_date' => $date], __('tanks.refill_logged', ['name' => $tank['name']]));
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/tanks/' . $tank['id']);
    }

    // ------------------------------------------------------------------ import

    public function importForm(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'create')) {
            Auth::deny('semen_stock.create');
        }
        $this->view('portal/semen/import', ['title' => __('semen_stock.import_title')]);
    }

    public function importSubmit(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'create')) {
            Auth::deny('semen_stock.create');
        }
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', __('files.upload_failed'));
            $this->redirect('/portal/semen-stock/import');
        }
        [$created, $errors] = \App\Services\SemenImportService::run($file['tmp_name']);
        Cache::bump();
        if ($created) {
            $this->flash('success', __('semen_stock.import_done', ['n' => $created]));
        }
        foreach (array_slice($errors, 0, 20) as $err) {
            $this->flash('error', $err);
        }
        $this->redirect('/portal/semen-stock');
    }

    // ------------------------------------------------------------------ printable report

    public function report(): void
    {
        Auth::requireLogin();
        if (!Auth::can('semen_stock', 'print') && !Auth::can('semen_stock', 'export')) {
            Auth::deny('semen_stock.print');
        }
        $rows = DB::all("SELECT t.*, COALESCE(st.name_en, t.stallion_name_free) AS stallion, tk.name AS tank, c.name AS canister,
                COALESCE(sc.name_en,'') AS color, CASE WHEN t.owner_type='client' THEN p.name_en ELSE NULL END AS owner_party
            FROM semen_stock t LEFT JOIN horses st ON st.id = t.stallion_id JOIN canisters c ON c.id = t.canister_id JOIN tanks tk ON tk.id = c.tank_id
                LEFT JOIN straw_colors sc ON sc.id = t.color_id LEFT JOIN parties p ON p.id = t.owner_party_id
            WHERE t.deleted_at IS NULL AND t.archived_at IS NULL ORDER BY stallion, tank, canister");
        $from = (string) Request::query('from', date('Y-m-01'));
        $to = (string) Request::query('to', date('Y-m-d'));
        $exits = DB::all("SELECT m.*, COALESCE(st.name_en, s.stallion_name_free) AS stallion FROM semen_movements m
                JOIN semen_stock s ON s.id = m.stock_id LEFT JOIN horses st ON st.id = s.stallion_id
            WHERE m.type = 'out' AND m.movement_date BETWEEN ? AND ? ORDER BY m.movement_date DESC", [$from, $to]);
        // On letterhead pages with a reference number and verification QR code (PrintoutService)
        $doc = PrintoutService::doc(__('semen_stock.report_title'));
        $stock = ['title' => __('nav.semen_stock'), 'note' => null, 'rows' => [], 'totals' => null, 'cols' => [
            [__('horse.sire'), 's', ''], [__('tanks.singular'), 's', ''], [__('canisters.singular'), 's', ''], [__('semen_stock.color'), 's', ''],
            [__('semen_stock.straws'), 'e', ''], [__('semen_stock.doses'), 'e', ''], [__('horses.owner_type'), 's', '']]];
        $straws = 0;
        foreach ($rows as $row) {
            $straws += (int) $row['straws'];
            $stock['rows'][] = [($row['stallion'] ?: '—') . ($row['batch_label'] ? ' · ' . $row['batch_label'] : ''), (string) $row['tank'], (string) $row['canister'],
                $row['color'] ?: '—', (string) (int) $row['straws'], (string) SemenStockService::doses((int) $row['straws'], $row),
                $row['owner_party'] ?: __('horses.owner_sk')];
        }
        if ($rows) {
            $stock['totals'] = [__('common.total'), '', '', '', (string) $straws, '', ''];
        }
        $out = ['title' => __('semen_stock.exits_title') . ' — ' . fmt_date($from) . ' – ' . fmt_date($to), 'note' => null, 'rows' => [], 'totals' => null, 'cols' => [
            [__('common.date'), 's', ''], [__('horse.sire'), 's', ''], [__('semen_stock.straws'), 'e', ''], [__('semen_stock.recipient'), 's', ''], [__('common.notes'), 's', '']]];
        foreach ($exits as $ex) {
            $out['rows'][] = [fmt_date($ex['movement_date']), $ex['stallion'] ?: '—', (string) abs((int) $ex['straws_delta']), $ex['recipient'] ?: '—', (string) ($ex['notes'] ?? '')];
        }
        $doc['sections'] = [$stock, $out];
        $doc['count'] = count($rows) + count($exits);
        $doc['hide_count'] = true; // two different lists: no single number of records
        $stamp = PrintoutService::issue('semen_report', 'semen_stock', $doc, false, PrintoutService::sourceUrl());
        Audit::log('export', 'semen_stock', 'semen_stock', null, null, ['rows' => count($rows), 'ref' => $stamp['ref'] ?? null], 'Semen inventory report printed' . ($stamp ? ' — ' . $stamp['ref'] : ''));
        PrintoutService::output($doc, $stamp);
    }

    /** A real calendar day as YYYY-MM-DD: the pattern alone lets 2026-02-30 through to the database, which refuses it. */
    private static function isDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }
}
