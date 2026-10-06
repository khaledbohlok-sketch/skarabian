<?php
declare(strict_types=1);

namespace App\Resources;

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

    public function fields(): array
    {
        return [
            'bill_id'           => ['type' => 'picker', 'source' => 'open_bills', 'label' => 'bills.singular', 'required' => true, 'create_only' => true, 'no_add' => true],
            'payment_date'      => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'amount_qar'        => ['type' => 'money', 'label' => 'payments.amount', 'required' => true, 'min' => 0.01, 'col' => 4, 'help' => 'payments.amount_help',
                                    'default' => fn () => self::remainingFor((int) ($_GET['bill_id'] ?? 0))],
            'account_id'        => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'required' => true, 'col' => 4],
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
            'party'   => ['label' => 'bills.party', 'sql' => 'p.name_en', 'hide_in_parent' => true],
            'type'    => ['label' => 'common.type', 'sql' => 'b.type', 'fmt' => 'enum', 'prefix' => 'bills.type', 'hide_in_parent' => true],
            'amount'  => ['label' => 'payments.amount', 'sql' => 't.amount_qar', 'fmt' => 'money', 'sort' => true],
            'account' => ['label' => 'bills.account', 'sql' => 'a.name'],
            'ref'     => ['label' => 'bills.reference', 'sql' => 't.reference_no'],
        ];
    }

    public function filters(): array
    {
        return [
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
        $left = FinanceService::remaining($b) + ($old ? (float) $old['amount_qar'] : 0);
        if ((float) $data['amount_qar'] > round($left, 2) + 0.004) {
            throw ValidationException::one('amount_qar', 'payments.too_much', ['left' => money($left, 'QAR', false)]);
        }
        if ($data['payment_date'] > date('Y-m-d', strtotime('+1 day'))) {
            throw ValidationException::one('payment_date', 'payments.future');
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        FinanceService::recalc((int) ($data['bill_id'] ?? $old['bill_id']));
    }

    public function afterDelete(array $row): void
    {
        FinanceService::recalc((int) $row['bill_id']);
        if ($row['payroll_line_id']) {
            PayrollService::paymentRemoved((int) $row['payroll_line_id']);
        }
    }
}
