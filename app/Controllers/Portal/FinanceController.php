<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Resources\Registry;
use App\Services\Cache;
use App\Services\FinanceService;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\Pickers;

/** Finance workflows that are more than create / edit: submit, cancel, line editors, receive, issue. */
class FinanceController extends Controller
{
    private function record(string $key, string $id): array
    {
        Auth::requireLogin();
        $res = Registry::get($key);
        $row = $res?->find((int) $id);
        if (!$res || !$row) {
            Response::notFound();
        }
        if (!$res->canView($row)) {
            Auth::deny($res->module . '.view');
        }
        return $row;
    }

    /** Runs a workflow step and returns to the record with a message. */
    private function step(string $back, callable $fn, string $okKey): never
    {
        try {
            $msg = $fn();
            Cache::bump();
            $this->flash('success', __(is_string($msg) && $msg !== '' ? $msg : $okKey));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect($back);
    }

    // ------------------------------------------------------------------ bills

    public function submit(string $id): void
    {
        $b = $this->record('bills', $id);
        if (!Registry::get('bills')->canEdit($b)) {
            Auth::deny('finance.edit');
        }
        $this->step('/portal/bills/' . $b['id'], fn () => FinanceService::submit((int) $b['id']) === 'pending' ? 'approvals.sent' : 'bills.approved_msg', 'common.saved');
    }

    public function cancel(string $id): void
    {
        Auth::requirePerm('finance', 'edit');
        $b = $this->record('bills', $id);
        if ($b['payroll_run_id'] || $b['invoice_id']) {
            Auth::deny('finance.edit');
        }
        $this->step('/portal/bills/' . $b['id'], fn () => FinanceService::cancel((int) $b['id']), 'bills.cancelled_msg');
    }

    // ------------------------------------------------------------------ purchase orders

    public function poLines(string $id): void
    {
        $po = $this->record('purchase-orders', $id);
        if (!Registry::get('purchase-orders')->canEdit($po)) {
            Auth::deny('finance.edit');
        }
        $back = '/portal/purchase-orders/' . $po['id'];
        if (Request::isPost()) {
            $lines = [];
            foreach ((array) ($_POST['lines'] ?? []) as $l) {
                if (!is_array($l) || empty($l['item_id'])) {
                    continue;
                }
                $qty = Money::parse($l['quantity'] ?? null);
                $price = Money::parse($l['unit_price'] ?? null);
                if (!Pickers::valid('items', $l['item_id']) || $qty === null || $qty <= 0 || $price === null || $price < 0) {
                    $this->linesError($back . '/lines', 'lines.invalid_po');
                }
                $lines[] = [(int) $l['item_id'], $qty, round($price, 2)];
            }
            $this->step($back, fn () => InventoryService::savePoLines((int) $po['id'], $lines), 'common.saved');
        }
        $lines = DB::all('SELECT item_id, quantity, unit_price FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY id', [$po['id']]);
        $this->view('portal/finance/lines_edit', ['title' => __('po.edit_lines'), 'kind' => 'po', 'row' => $po, 'lines' => $lines]);
    }

    public function submitPo(string $id): void
    {
        $po = $this->record('purchase-orders', $id);
        if (!Registry::get('purchase-orders')->canEdit($po)) {
            Auth::deny('finance.edit');
        }
        $this->step('/portal/purchase-orders/' . $po['id'], fn () => InventoryService::submitPo((int) $po['id']) === 'pending' ? 'approvals.sent' : 'po.approved_msg', 'common.saved');
    }

    public function receivePo(string $id): void
    {
        if (!Auth::can('inventory', 'create') && !Auth::can('finance', 'create')) {
            Auth::deny('inventory.create');
        }
        $po = $this->record('purchase-orders', $id);
        $this->step('/portal/purchase-orders/' . $po['id'], function () use ($po) {
            InventoryService::receivePo((int) $po['id']);
            return 'po.received_msg';
        }, 'common.saved');
    }

    public function cancelPo(string $id): void
    {
        Auth::requirePerm('finance', 'edit');
        $po = $this->record('purchase-orders', $id);
        $this->step('/portal/purchase-orders/' . $po['id'], fn () => InventoryService::cancelPo((int) $po['id']), 'po.cancelled_msg');
    }

    // ------------------------------------------------------------------ invoices

    public function invoiceLines(string $id): void
    {
        $inv = $this->record('invoices', $id);
        if (!Registry::get('invoices')->canEdit($inv)) {
            Auth::deny('finance.edit');
        }
        $back = '/portal/invoices/' . $inv['id'];
        if (Request::isPost()) {
            $lines = [];
            foreach ((array) ($_POST['lines'] ?? []) as $l) {
                $desc = is_array($l) ? trim((string) ($l['description'] ?? '')) : '';
                if ($desc === '') {
                    continue;
                }
                $qty = Money::parse($l['quantity'] ?? '1') ?? 1.0;
                $price = Money::parse($l['unit_price'] ?? null);
                if ($qty <= 0 || $price === null || $price < 0 || mb_strlen($desc) > 255) {
                    $this->linesError($back . '/lines', 'lines.invalid_invoice');
                }
                $lines[] = [$desc, $qty, round($price, 2)];
            }
            $this->step($back, fn () => FinanceService::saveInvoiceLines((int) $inv['id'], $lines), 'common.saved');
        }
        $lines = DB::all('SELECT description, quantity, unit_price FROM invoice_items WHERE invoice_id = ? ORDER BY id', [$inv['id']]);
        $this->view('portal/finance/lines_edit', ['title' => __('invoices.edit_lines'), 'kind' => 'invoice', 'row' => $inv, 'lines' => $lines]);
    }

    public function issueInvoice(string $id): void
    {
        $inv = $this->record('invoices', $id);
        if (!Registry::get('invoices')->canEdit($inv)) {
            Auth::deny('finance.edit');
        }
        $this->step('/portal/invoices/' . $inv['id'], function () use ($inv) {
            FinanceService::issueInvoice((int) $inv['id']);
            return 'invoices.issued_msg';
        }, 'common.saved');
    }

    public function cancelInvoice(string $id): void
    {
        Auth::requirePerm('finance', 'edit');
        $inv = $this->record('invoices', $id);
        $this->step('/portal/invoices/' . $inv['id'], fn () => FinanceService::cancelInvoice((int) $inv['id']), 'invoices.cancelled_msg');
    }

    private function linesError(string $back, string $key): never
    {
        Session::errors(['lines' => __($key)]);
        $this->flash('error', __('validation.fix_errors'));
        $this->redirect($back);
    }
}
