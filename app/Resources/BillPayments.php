<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\FinanceService;
use App\Services\PayrollService;

/** Payments against a bill (partial or full). Deleting one needs Owner / GM approval, like every financial record. */
class BillPayments extends Resource
{
    public string $key = 'bill-payments';
    public string $table = 'bill_payments';
    public string $module = 'finance';
    public string $recordType = 'bill_payment';
    public string $title = 'nav.payments';
    public string $singular = 'payments.singular';
    public array $search = ['b.number', 't.reference_no', 'p.name_en'];
    public string $sort = 't.payment_date DESC, t.id DESC';
    public bool $financial = true;
    public ?string $parentField = 'bill_id';
    public ?string $parentResource = 'bills';
    public bool $bulkSelect = true;
    public string $bulkNoneKey = 'payments.select_some';

    /** The Payments list's bulk bar: delete the ticked payments — the Owner at once (to the Trash, each bill's paid
     * amount updated), anyone else asks the Owner. Wired by bill-report-picker.js (FinanceController::bulkDeletePayments). */
    public function bulkBarHtml(): string
    {
        if (!Auth::can('finance', 'delete')) {
            return '';
        }
        return '<span class="bulk-actions"><form method="post" action="' . e(url('/portal/bill-payments/bulk-delete')) . '" class="inline bulk-ids" data-confirm="'
            . e(__(Auth::isOwner() ? 'payments.bulk_delete_confirm' : 'payments.bulk_delete_request_confirm')) . '">' . csrf_field()
            . '<button type="submit" class="btn btn-danger">' . icon('trash') . ' ' . e(__('common.delete')) . '</button></form></span>';
    }

    public function fields(): array
    {
        return [
            'bill_id'           => ['type' => 'picker', 'source' => 'open_bills', 'label' => 'bills.singular', 'required' => true, 'create_only' => true, 'no_add' => true],
            'payment_date'      => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'amount_qar'        => ['type' => 'money', 'label' => 'payments.amount', 'required' => true, 'min' => 0.01, 'col' => 4, 'help' => 'payments.amount_help', 'unit' => 'QAR',
                                    'default' => fn () => self::remainingFor((int) ($_GET['bill_id'] ?? 0))],
            'account_id'        => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'required' => true, 'col' => 4, 'remember' => true],
            'payment_method_id' => ['type' => 'lookup', 'lookup' => 'payment_method', 'label' => 'bills.method', 'col' => 6],
            'reference_no'      => ['type' => 'text', 'label' => 'bills.reference', 'max' => 80, 'col' => 6],
            'notes'             => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    private static function remainingFor(int $billId): ?string
    {
        $b = $billId ? DB::row('SELECT amount_qar, paid_qar FROM bills WHERE id = ?', [$billId]) : null;
        return $b ? number_format(FinanceService::remaining($b), 2, '.', '') : null;
    }

    public function from(): string
    {
        return '`bill_payments` t JOIN bills b ON b.id = t.bill_id LEFT JOIN parties p ON p.id = b.party_id LEFT JOIN accounts a ON a.id = t.account_id';
    }

    public function columns(): array
    {
        return [
            'date'    => ['label' => 'common.date', 'sql' => 't.payment_date', 'fmt' => 'date', 'sort' => true],
            'bill'    => ['label' => 'bills.number', 'sql' => 'b.number', 'hide_in_parent' => true],
            'party'   => ['label' => 'bills.party', 'sql' => self::nameSql('p'), 'hide_in_parent' => true],
            'type'    => ['label' => 'common.type', 'sql' => 'b.type', 'fmt' => 'enum', 'prefix' => 'bills.type', 'hide_in_parent' => true],
            'amount'  => ['label' => 'payments.amount', 'sql' => 't.amount_qar', 'fmt' => 'money', 'sort' => true],
            'account' => ['label' => 'bills.account', 'sql' => 'a.name'],
            'ref'     => ['label' => 'bills.reference', 'sql' => 't.reference_no'],
        ];
    }

    public function filters(): array
    {
        return [
            // one bill's payments ("Open full list" on a bill's / invoice's Payments tab)
            'bill_id'    => ['type' => 'picker', 'source' => 'bills', 'label' => 'bills.singular', 'sql' => 't.bill_id'],
            'account_id' => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'sql' => 't.account_id'],
            'type'       => ['type' => 'select', 'label' => 'common.type', 'sql' => 'b.type', 'options' => Bills::TYPES],
            'from'       => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.payment_date'],
            'to'         => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.payment_date'],
        ];
    }

    public function label(array $row): string
    {
        return DB::value('SELECT number FROM bills WHERE id = ?', [$row['bill_id']]) . ' — ' . money($row['amount_qar'], 'QAR', false) . ' — ' . fmt_date($row['payment_date']);
    }

    public function listTotals(ListQuery $q): array
    {
        return !empty($q->activeFilters['type']) ? ['amount' => $q->sum('t.amount_qar')] : [];
    }

    /** Salary payments are changed from the payroll run. */
    public function canEdit(array $row): bool
    {
        return !$row['payroll_line_id'] && parent::canEdit($row);
    }

    public function prepare(array $data, ?array $old): array
    {
        $billId = (int) ($data['bill_id'] ?? $old['bill_id']);
        $b = DB::row('SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL', [$billId]);
        if (!$b || (!$old && !in_array($b['status'], FinanceService::OPEN, true))) {
            throw ValidationException::one('bill_id', 'payments.bill_not_open');
        }
        // a salary is paid from its payroll run (Payroll → the month): paid here, the payroll line would stay unpaid
        if (!$old && !empty($b['payroll_run_id'])) {
            throw ValidationException::one('bill_id', 'payments.salary_from_payroll');
        }
        $left = FinanceService::remaining($b) + ($old ? (float) $old['amount_qar'] : 0);
        if ((float) $data['amount_qar'] > round($left, 2) + 0.004) {
            throw ValidationException::one('amount_qar', 'payments.too_much', ['left' => money($left, 'QAR', false)]);
        }
        if ($data['payment_date'] > date('Y-m-d', strtotime('+1 day'))) {
            throw ValidationException::one('payment_date', 'payments.future');
        }
        return $data;
    }

    public function actions(array $row): array
    {
        $income = DB::value('SELECT type FROM bills WHERE id = ?', [$row['bill_id']]) === 'income';
        return $income && \App\Services\Studio::canCreate('inv')
            ? [['label' => 'payments.print_receipt', 'url' => '/portal/studio/new/inv?record_type=bill_payment&record_id=' . $row['id'], 'class' => 'btn-primary']] : [];
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        $billId = (int) ($data['bill_id'] ?? $old['bill_id']);
        // prepare() checked the amount without a lock; checked again here inside the save transaction with the bill
        // row locked and the payments read as committed, so two payments saved at the same moment can't overpay it
        $b = DB::row('SELECT * FROM bills WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$billId]);
        $paid = (float) DB::value('SELECT COALESCE(SUM(amount_qar), 0) FROM bill_payments WHERE bill_id = ? AND deleted_at IS NULL FOR UPDATE', [$billId]);
        if (!$b || round($paid, 2) > round((float) $b['amount_qar'], 2) + 0.004) {
            $left = $b ? max(0.0, round((float) $b['amount_qar'] - ($paid - (float) $data['amount_qar']), 2)) : 0.0;
            throw ValidationException::one('amount_qar', 'payments.too_much', ['left' => money($left, 'QAR', false)]);
        }
        FinanceService::recalc($billId);
    }

    public function afterDelete(array $row): void
    {
        FinanceService::recalc((int) $row['bill_id']);
        if ($row['payroll_line_id']) {
            PayrollService::paymentRemoved((int) $row['payroll_line_id']);
        }
    }

    /** Back from the Trash only onto a bill that still exists and still owes it (not paid again meanwhile). */
    public function restoreBlocker(array $row): ?string
    {
        $b = DB::row('SELECT * FROM bills WHERE id = ?', [$row['bill_id']]);
        if (!$b || $b['deleted_at'] !== null || $b['status'] === 'cancelled') {
            return __('payments.restore_bill_gone');
        }
        if ($row['payroll_line_id'] && DB::value('SELECT paid_at FROM payroll_lines WHERE id = ?', [$row['payroll_line_id']])) {
            return __('payments.restore_salary_paid');
        }
        if ((float) $row['amount_qar'] > FinanceService::remaining($b) + 0.004) {
            return __('payments.restore_overpaid', ['left' => money(FinanceService::remaining($b), 'QAR', false)]);
        }
        return null;
    }

    /** Restored: the bill counts it again (and a salary is paid again, its loan repayment taken again). */
    public function afterRestore(array $row): void
    {
        FinanceService::recalc((int) $row['bill_id']);
        if ($row['payroll_line_id']) {
            PayrollService::paymentRestored((int) $row['payroll_line_id'], (string) $row['payment_date']);
        }
    }
}
