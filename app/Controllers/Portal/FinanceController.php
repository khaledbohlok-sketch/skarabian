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
use App\Services\FinancialReportService;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\Pickers;
use App\Services\Studio;
use App\Services\TrashService;

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

    /** GET /portal/bills/draft-reports — draft Financial Reports the current user may add bills to. */
    public function draftReports(): void
    {
        Auth::requireLogin();
        if (!Auth::can('finance') || !Auth::can('reports') || !Studio::canCreate('fin')) {
            Auth::deny('reports.studio.fin');
        }
        Response::json(['rows' => FinancialReportService::draftsFor()]);
    }

    /** POST /portal/bills/bulk-add-to-report — selected bill ids into an existing draft, or a brand-new one. */
    public function bulkAddToReport(): void
    {
        Auth::requireLogin();
        // Same rule as the Studio's own "Add bills": only roles allowed to make Financial Reports
        if (!Auth::can('finance') || !Auth::can('reports') || !Studio::canCreate('fin')) {
            Auth::deny('reports.studio.fin');
        }
        $ids = array_map('intval', (array) Request::post('ids', []));
        if (!$ids) {
            $this->flash('error', __('bills.select_some'));
            $this->redirect('/portal/bills');
        }
        // "New report" makes a new draft even when a draft is still chosen in the hidden list of existing ones
        if (Request::post('target') === 'existing') {
            $documentId = (int) Request::post('document_id');
            // Only the draft's creator (or the Owner) adds to it
            $draft = DB::row("SELECT * FROM documents WHERE id = ? AND doc_type = 'fin' AND status = 'draft' AND deleted_at IS NULL", [$documentId]) ?? Response::notFound();
            if (!StudioController::ownsDraft($draft)) {
                Auth::deny('studio.fin.draft');
            }
        } else {
            $kind = (string) Request::post('report_kind', 'outstanding');
            $from = (string) Request::post('period_from', date('Y-m-01'));
            $to = (string) Request::post('period_to', date('Y-m-t'));
            $documentId = FinancialReportService::createDraft([
                'lang' => lang(), 'currency' => 'QAR', 'reportKind' => $kind, 'groupBy' => 'none', 'periodFrom' => $from, 'periodTo' => $to,
                'titleEn' => '', 'titleAr' => '', 'subEn' => '', 'subAr' => '', 'prepared' => '', 'approved' => '',
            ]);
        }
        FinancialReportService::addBills($documentId, $ids);
        $this->redirect('/portal/studio/' . $documentId);
    }

    /** POST /portal/bills/bulk-pay — each selected open bill is paid what it still owes, from one account. Salary bills
     * are paid from Payroll (that also marks the payslip paid and takes the loan deduction), so they are skipped. */
    public function bulkPay(): void
    {
        Auth::requireLogin();
        if (!Auth::can('finance', 'create') && !Auth::isOwner()) {
            Auth::deny('finance.create');
        }
        $ids = array_values(array_unique(array_map('intval', (array) Request::post('ids', []))));
        $account = (int) Request::post('account_id');
        $method = (int) Request::post('payment_method_id') ?: null;
        $date = (string) Request::post('date');
        try {
            if (!$ids) {
                throw new \DomainException(__('bills.select_some'));
            }
            if (!Pickers::valid('accounts', $account)) {
                throw new \DomainException(__('bills.account_required'));
            }
            if ($method && !DB::value("SELECT 1 FROM lookups WHERE id = ? AND type = 'payment_method'", [$method])) {
                $method = null;
            }
            if (!valid_date($date) || $date > date('Y-m-d', strtotime('+1 day'))) {
                throw new \DomainException(__('validation.date'));
            }
            $res = Registry::get('bills');
            $paid = 0;
            $total = 0.0;
            $skipped = [];
            DB::transaction(function () use ($ids, $res, $account, $method, $date, &$paid, &$total, &$skipped) {
                foreach ($ids as $id) {
                    $b = $res->find($id);
                    if (!$b || !$res->canView($b)) {
                        continue;
                    }
                    if ($b['payroll_run_id']) {
                        $skipped[] = $b['number'] . ' — ' . __('bills.skip_salary');
                    } elseif (!in_array($b['status'], FinanceService::OPEN, true)) {
                        $skipped[] = $b['number'] . ' — ' . __('bills.skip_not_open', ['status' => enum_label('status', $b['status'])]);
                    } elseif (FinanceService::payInFull((int) $b['id'], $account, $method, $date)) {
                        $paid++;
                        $total += FinanceService::remaining($b);
                    }
                }
            });
            Cache::bump();
            $this->flash($paid ? 'success' : 'error', $paid ? __('bills.bulk_paid', ['n' => $paid, 'amount' => money($total, 'QAR', false)]) : __('bills.bulk_none_paid'));
            if ($skipped) {
                $this->flash('info', __('bills.bulk_skipped') . ' ' . implode('; ', array_slice($skipped, 0, 15)) . (count($skipped) > 15 ? ' …' : ''));
            }
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->back('/portal/bills');
    }

    /** POST /portal/bills/bulk-delete — each selected bill as if deleted on its own page: the Owner's go to the Trash,
     * anyone else's become requests for the Owner. Bills that cannot be deleted are listed with the reason. */
    public function bulkDelete(): void
    {
        Auth::requirePerm('finance', 'delete');
        $ids = array_values(array_unique(array_map('intval', (array) Request::post('ids', []))));
        if (!$ids) {
            $this->flash('error', __('bills.select_some'));
            $this->back('/portal/bills');
        }
        $res = Registry::get('bills');
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $b = $res->find($id);
            if (!$b || !$res->canView($b)) {
                continue;
            }
            if (!$res->canDelete($b)) {
                $skipped[] = $b['number'] . ' — ' . __($b['payroll_run_id'] ? 'bills.skip_salary_delete' : 'bills.skip_invoice_delete');
                continue;
            }
            try {
                $how = TrashService::delete($res, $b); // 'deleted', or 'approval' (waits for an Owner)
                $done++;
            } catch (\DomainException $e) {
                $skipped[] = $b['number'] . ' — ' . $e->getMessage();
            }
        }
        Cache::bump();
        if ($done) {
            $this->flash('success', __(($how ?? '') === 'deleted' ? 'bills.bulk_deleted' : 'bills.bulk_delete_requested', ['n' => $done]));
        }
        if ($skipped) {
            $this->flash($done ? 'info' : 'error', __('bills.bulk_skipped') . ' ' . implode('; ', array_slice($skipped, 0, 15)) . (count($skipped) > 15 ? ' …' : ''));
        }
        $this->back('/portal/bills');
    }

    /** Payments list: delete the ticked payments. The Owner's go to the Trash at once (each bill's paid amount is
     * updated, a salary is unpaid again); anyone else's become delete requests for the Owner. */
    public function bulkDeletePayments(): void
    {
        Auth::requirePerm('finance', 'delete');
        $ids = array_values(array_unique(array_map('intval', (array) Request::post('ids', []))));
        if (!$ids) {
            $this->flash('error', __('payments.select_some'));
            $this->back('/portal/bill-payments');
        }
        $res = Registry::get('bill-payments');
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $p = $res->find($id);
            if (!$p || !$res->canView($p) || !$res->canDelete($p)) {
                continue;
            }
            try {
                $how = TrashService::delete($res, $p); // 'deleted', or 'approval' (waits for an Owner)
                $done++;
            } catch (\DomainException $e) {
                $skipped[] = $res->label($p) . ' — ' . $e->getMessage();
            }
        }
        Cache::bump();
        if ($done) {
            $this->flash('success', __(($how ?? '') === 'deleted' ? 'payments.bulk_deleted' : 'payments.bulk_delete_requested', ['n' => $done]));
        }
        if ($skipped) {
            $this->flash($done ? 'info' : 'error', __('bills.bulk_skipped') . ' ' . implode('; ', array_slice($skipped, 0, 15)) . (count($skipped) > 15 ? ' …' : ''));
        }
        if (!$done && !$skipped) {
            $this->flash('error', __('payments.select_some'));
        }
        $this->back('/portal/bill-payments');
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
