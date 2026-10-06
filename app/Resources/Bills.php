<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Approvals;
use App\Services\FileStore;
use App\Services\FinanceService;
use App\Services\Money;
use App\Services\Sequence;

/**
 * Bills / transactions (income, expense, liability). Amounts are entered in any active currency with the rate
 * "1 unit = X QAR" saved on the bill; the QAR value is calculated once, 2 decimals.
 */
class Bills extends Resource
{
    public string $key = 'bills';
    public string $table = 'bills';
    public string $module = 'finance';
    public string $recordType = 'bill';
    public string $title = 'nav.bills';
    public string $singular = 'bills.singular';
    public array $search = ['t.number', 't.description', 't.reference_no', 'p.name_en', 'c.name_en'];
    public string $sort = 't.bill_date DESC, t.id DESC';
    public bool $financial = true;

    public const TYPES = ['expense' => 'bills.type_expense', 'income' => 'bills.type_income', 'liability' => 'bills.type_liability'];
    public const STATUSES = ['draft' => 'status.draft', 'pending' => 'status.pending', 'approved' => 'status.approved', 'partially_paid' => 'status.partially_paid',
                             'paid' => 'status.paid', 'overdue' => 'status.overdue', 'cancelled' => 'status.cancelled'];

    public function sections(): array
    {
        return ['main' => 'bills.sec_bill', 'amount' => 'bills.sec_amount', 'pay' => 'bills.sec_payment', 'links' => 'bills.sec_links'];
    }

    public function fields(): array
    {
        return [
            'number'          => ['type' => 'text', 'label' => 'bills.number', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'type'            => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'expense', 'col' => 4],
            'bill_date'       => ['type' => 'date', 'label' => 'bills.bill_date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'category_id'     => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'finance.category', 'required' => true, 'depends' => ['type' => 'type'], 'col' => 6, 'no_add' => true],
            'subcategory_id'  => ['type' => 'picker', 'source' => 'subcategories', 'label' => 'bills.subcategory', 'depends' => ['parent_id' => 'category_id'], 'col' => 6, 'no_add' => true],
            'party_id'        => ['type' => 'picker', 'source' => 'parties', 'label' => 'bills.party', 'col' => 6, 'help' => 'bills.party_help'],
            'description'     => ['type' => 'text', 'label' => 'common.description', 'max' => 255, 'col' => 6, 'no_phone' => true],

            'currency'        => ['type' => 'currency', 'label' => 'finance.currency', 'section' => 'amount', 'default' => 'QAR', 'required' => true, 'col' => 3],
            'exchange_rate'   => ['type' => 'decimal', 'label' => 'finance.exchange_rate', 'section' => 'amount', 'default' => 1, 'col' => 3, 'help' => 'bills.rate_help'],
            'amount_original' => ['type' => 'money', 'label' => 'bills.amount', 'section' => 'amount', 'required' => true, 'min' => 0.01, 'col' => 3, 'currency_field' => 'currency'],
            'qar_preview'     => ['type' => 'display', 'label' => 'bills.amount_qar', 'section' => 'amount', 'col' => 3,
                                  'render' => fn ($r) => '<output data-qar-preview class="qar-preview">' . ($r ? e(number_format((float) $r['amount_qar'], 2)) . ' QAR' : '—') . '</output>'],
            'due_date'        => ['type' => 'date', 'label' => 'bills.due_date', 'section' => 'amount', 'col' => 4, 'help' => 'bills.due_help'],
            'reference_no'    => ['type' => 'text', 'label' => 'bills.reference', 'section' => 'amount', 'max' => 80, 'col' => 8],

            'account_id'        => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'section' => 'pay', 'col' => 6],
            'payment_method_id' => ['type' => 'lookup', 'lookup' => 'payment_method', 'label' => 'bills.method', 'section' => 'pay', 'col' => 6],
            'paid_now'          => ['type' => 'checkbox', 'label' => 'bills.paid_now', 'section' => 'pay', 'virtual' => true, 'create_only' => true, 'col' => 6, 'help' => 'bills.paid_now_help'],
            'save_draft'        => ['type' => 'checkbox', 'label' => 'bills.save_draft', 'section' => 'pay', 'virtual' => true, 'create_only' => true, 'col' => 6, 'help' => 'bills.save_draft_help'],
            'receipt'           => ['type' => 'file', 'label' => 'bills.receipt', 'section' => 'pay', 'create_only' => true, 'accept' => 'image/*,application/pdf', 'col' => 12, 'help' => 'bills.receipt_help'],

            'horse_id'    => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'section' => 'links', 'col' => 6],
            'embryo_id'   => ['type' => 'picker', 'source' => 'embryos', 'label' => 'bills.embryo', 'section' => 'links', 'col' => 6],
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'bills.employee', 'section' => 'links', 'col' => 6],
            'item_id'     => ['type' => 'picker', 'source' => 'items', 'label' => 'bills.item', 'section' => 'links', 'col' => 6],
            'notes'       => ['type' => 'textarea', 'label' => 'common.notes', 'section' => 'links', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`bills` t LEFT JOIN finance_categories c ON c.id = t.category_id LEFT JOIN parties p ON p.id = t.party_id';
    }

    public function columns(): array
    {
        return [
            'number'   => ['label' => 'bills.number', 'sql' => 't.number', 'sort' => true],
            'date'     => ['label' => 'common.date', 'sql' => 't.bill_date', 'fmt' => 'date', 'sort' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'bills.type'],
            'category' => ['label' => 'finance.category', 'sql' => 'c.name_en', 'sort' => true],
            'party'    => ['label' => 'bills.party', 'sql' => 'p.name_en', 'sort' => true],
            'desc'     => ['label' => 'common.description', 'sql' => 't.description'],
            'amount'   => ['label' => 'bills.amount_qar', 'sql' => 't.amount_qar', 'fmt' => 'money', 'sort' => true],
            'paid'     => ['label' => 'bills.paid', 'sql' => 't.paid_qar', 'fmt' => 'money'],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge'],
            'due'      => ['label' => 'bills.due_date', 'sql' => 't.due_date', 'fmt' => 'date', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'type'        => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.type', 'options' => self::TYPES],
            'status'      => ['type' => 'raw', 'label' => 'common.status', 'options' => ['unpaid' => 'bills.f_unpaid', 'pending_all' => 'bills.f_pending'] + self::STATUSES,
                              'sql' => 't.status = :value', 'sqlFor' => ['unpaid' => "t.status IN ('approved','partially_paid','overdue')", 'pending_all' => "t.status IN ('draft','pending')"]],
            'category_id' => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'finance.category', 'sql' => 't.category_id'],
            'party_id'    => ['type' => 'picker', 'source' => 'parties', 'label' => 'bills.party', 'sql' => 't.party_id'],
            'horse_id'    => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'sql' => 't.horse_id'],
            'embryo_id'   => ['type' => 'picker', 'source' => 'embryos', 'label' => 'bills.embryo', 'sql' => 't.embryo_id'],
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'bills.employee', 'sql' => 't.employee_id'],
            'account_id'  => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'sql' => 't.account_id'],
            'from'        => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.bill_date'],
            'to'          => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.bill_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['number'];
    }

    public function links(): array
    {
        return [['bill_payments', 'bill_id', 'nav.payments'], ['purchase_orders', 'bill_id', 'nav.purchase_orders'], ['invoices', 'bill_id', 'nav.invoices']];
    }

    public function beforeList(): void
    {
        FinanceService::refreshOverdue();
    }

    public function rowClass(array $row): string
    {
        return match ($row['status'] ?? '') { 'overdue' => 'row-alert', 'cancelled' => 'row-muted', 'draft', 'pending' => 'row-pending', default => '' };
    }

    /** Totals only make sense for one type at a time (income and expenses are never added together). */
    public function listTotals(ListQuery $q): array
    {
        if (empty($q->activeFilters['type'])) {
            return [];
        }
        $q2 = clone $q;
        $q2->where[] = "t.status <> 'cancelled'";
        return ['amount' => $q2->sum('t.amount_qar'), 'paid' => $q2->sum('t.paid_qar')];
    }

    public function headerBadges(array $row): string
    {
        return status_badge($row['status']);
    }

    /** Bills created by payroll, purchase orders or invoices change through those records; paid and cancelled bills are closed. */
    public function canEdit(array $row): bool
    {
        if (in_array($row['status'], ['paid', 'cancelled'], true) || $row['payroll_run_id'] || $row['purchase_order_id'] || $row['invoice_id']) {
            return false;
        }
        return parent::canEdit($row);
    }

    public function prepare(array $data, ?array $old): array
    {
        $errors = [];
        $rate = $data['currency'] === Money::BASE ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
        if ($rate <= 0) {
            $errors['exchange_rate'] = __('bills.rate_required');
        }
        $data['exchange_rate'] = $rate;
        $data['amount_qar'] = Money::toQar((float) $data['amount_original'], $rate);
        $cat = DB::row('SELECT type, parent_id FROM finance_categories WHERE id = ?', [$data['category_id']]);
        if ($cat && !in_array($cat['type'], [$data['type'], 'any'], true)) {
            $errors['category_id'] = __('bills.category_type');
        }
        if (!empty($data['subcategory_id']) && (int) DB::value('SELECT parent_id FROM finance_categories WHERE id = ?', [$data['subcategory_id']]) !== (int) $data['category_id']) {
            $errors['subcategory_id'] = __('bills.subcategory_mismatch');
        }
        if (!empty($data['due_date']) && $data['due_date'] < $data['bill_date']) {
            $errors['due_date'] = __('bills.due_before_date');
        }
        if (!empty($this->extra['paid_now']) && empty($data['account_id'])) {
            $errors['account_id'] = __('bills.account_required');
        }
        if ($old) {
            if ($data['amount_qar'] < (float) $old['paid_qar']) {
                $errors['amount_original'] = __('bills.below_paid', ['paid' => money($old['paid_qar'], 'QAR', false)]);
            }
            if ($data['type'] !== $old['type'] && (float) $old['paid_qar'] > 0) {
                $errors['type'] = __('bills.type_locked');
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if (!$old) {
            $data['number'] = Sequence::bill($data['bill_date']);
            if (!empty($this->extra['save_draft'])) {
                $data['status'] = 'draft';
            } elseif (FinanceService::needsApproval($data['type'], $data['amount_qar'])) {
                $data['status'] = 'pending';
            } else {
                $data['status'] = 'approved';
                $data['approved_by'] = Auth::id();
                $data['approved_at'] = date('Y-m-d H:i:s');
            }
        } else {
            $raised = $data['amount_qar'] > (float) $old['amount_qar'] || $data['type'] !== $old['type'];
            if (in_array($old['status'], FinanceService::OPEN, true) && $raised && FinanceService::needsApproval($data['type'], $data['amount_qar'])) {
                $data['status'] = 'pending'; // a bigger amount needs a new approval
            } elseif ($old['status'] === 'pending' && !FinanceService::needsApproval($data['type'], $data['amount_qar'])) {
                $data['status'] = 'approved';
                $data['approved_by'] = Auth::id();
                $data['approved_at'] = date('Y-m-d H:i:s');
            }
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        $status = $data['status'] ?? $old['status'];
        $payload = !empty($this->extra['paid_now']) ? ['pay_account_id' => (int) $data['account_id'], 'pay_method_id' => $data['payment_method_id'] ?? null] : [];
        if ($old && $old['status'] === 'pending' && ($status !== 'pending' || (float) $data['amount_qar'] !== (float) $old['amount_qar'])) {
            Approvals::cancelFor('bill', $id);
        }
        if ($status === 'pending' && ($created || $old['status'] !== 'pending' || (float) $data['amount_qar'] !== (float) $old['amount_qar'])) {
            Approvals::request('bill', 'bill', $id, FinanceService::title(['id' => $id] + $data + ($old ?? [])), (float) $data['amount_qar'], $payload);
        }
        FinanceService::recalc($id);
        if ($created && $status === 'approved' && $payload) {
            FinanceService::payInFull($id, $payload['pay_account_id'], $payload['pay_method_id'], $data['bill_date']);
        }
        if ($created && !empty($_FILES['receipt']['name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            FileStore::store($_FILES['receipt'], 'bill', $id, 'receipt', __('bills.receipt'));
        }
    }

    public function afterDelete(array $row): void
    {
        Approvals::cancelFor('bill', (int) $row['id']);
    }

    public function beforeTabs(array $row): string
    {
        return View::partial('portal/finance/bill_header', ['b' => $row]);
    }

    public function actions(array $row): array
    {
        $a = [];
        $id = (int) $row['id'];
        if ($row['status'] === 'draft' && $this->canEdit($row)) {
            $a[] = ['label' => 'bills.submit', 'url' => "/portal/bills/$id/submit", 'method' => 'post', 'class' => 'btn-primary'];
        }
        if ($row['status'] === 'pending') {
            $ap = DB::row("SELECT * FROM approvals WHERE type = 'bill' AND record_id = ? AND status = 'pending'", [$id]);
            if ($ap && Approvals::canDecide($ap)) {
                $a[] = ['label' => 'common.approve', 'url' => '/portal/approvals/' . $ap['id'] . '/approve', 'method' => 'post', 'class' => 'btn-primary'];
                $a[] = ['label' => 'common.reject', 'url' => '/portal/approvals/' . $ap['id'] . '/reject', 'method' => 'post', 'class' => 'btn-danger', 'confirm' => 'approvals.confirm_reject'];
            }
        }
        if (in_array($row['status'], FinanceService::OPEN, true) && Auth::can('finance', 'create')) {
            $a[] = ['label' => 'bills.record_payment', 'url' => '/portal/bill-payments/create?bill_id=' . $id, 'class' => 'btn-primary'];
        }
        if (!in_array($row['status'], ['paid', 'cancelled'], true) && (float) $row['paid_qar'] <= 0 && Auth::can('finance', 'edit') && !$row['payroll_run_id'] && !$row['invoice_id']) {
            $a[] = ['label' => 'bills.cancel', 'url' => "/portal/bills/$id/cancel", 'method' => 'post', 'confirm' => 'bills.confirm_cancel'];
        }
        if ($row['purchase_order_id']) {
            $a[] = ['label' => 'nav.purchase_orders', 'url' => '/portal/purchase-orders/' . $row['purchase_order_id']];
        }
        if ($row['invoice_id']) {
            $a[] = ['label' => 'invoices.singular', 'url' => '/portal/invoices/' . $row['invoice_id']];
        }
        if ($row['payroll_run_id'] && Auth::can('payroll')) {
            $a[] = ['label' => 'nav.payroll', 'url' => '/portal/payroll/' . $row['payroll_run_id']];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        $n = (int) DB::value('SELECT COUNT(*) FROM bill_payments WHERE bill_id = ? AND deleted_at IS NULL', [$row['id']]);
        return ['bill-payments' => ['label' => 'nav.payments', 'related' => 'bill-payments', 'filter' => ['bill_id' => (int) $row['id']], 'count' => $n ?: null]]
             + $this->standardTabs($row, ['categories' => ['receipt', 'document'], 'studio' => ['inv', 'fin']]);
    }
}
