<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\FinanceService;
use App\Services\Money;
use App\Services\Sequence;

/**
 * Invoices to clients (boarding and other services). Lines are edited while the invoice is a draft; issuing it
 * creates the income record (bill) that payments and reports use. Receipts are printed from SK Arabian Studio.
 */
class Invoices extends Resource
{
    public string $key = 'invoices';
    public string $table = 'invoices';
    public string $module = 'finance';
    public string $recordType = 'invoice';
    public string $title = 'nav.invoices';
    public string $singular = 'invoices.singular';
    public array $search = ['t.number', 'p.name_en', 't.notes'];
    public string $sort = 't.invoice_date DESC, t.id DESC';
    public bool $financial = true;

    public const TYPES = ['boarding' => 'invoices.type_boarding', 'other' => 'invoices.type_other'];
    public const STATUSES = ['draft' => 'invoices.status_draft', 'issued' => 'invoices.status_issued', 'partially_paid' => 'invoices.status_partially_paid', 'paid' => 'invoices.status_paid', 'cancelled' => 'invoices.status_cancelled'];

    public function fields(): array
    {
        return [
            'number'        => ['type' => 'text', 'label' => 'invoices.number', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'party_id'      => ['type' => 'picker', 'source' => 'clients', 'label' => 'invoices.client', 'required' => true, 'col' => 8],
            'invoice_type'  => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'boarding', 'col' => 4],
            'category_id'   => ['type' => 'picker', 'source' => 'top_categories', 'filter' => ['type' => 'income'], 'label' => 'finance.category', 'col' => 8, 'no_add' => true, 'help' => 'invoices.category_help'],
            'invoice_date'  => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'due_date'      => ['type' => 'date', 'label' => 'bills.due_date', 'col' => 4, 'default' => fn () => date('Y-m-d', strtotime('+14 days'))],
            'horse_id'      => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'col' => 4],
            'currency'      => ['type' => 'currency', 'label' => 'finance.currency', 'default' => 'QAR', 'required' => true, 'col' => 4],
            'exchange_rate' => ['type' => 'decimal', 'label' => 'finance.exchange_rate', 'default' => 1, 'col' => 4, 'help' => 'bills.rate_help'],
            'notes'         => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`invoices` t JOIN parties p ON p.id = t.party_id';
    }

    public function columns(): array
    {
        return [
            'number' => ['label' => 'invoices.number', 'sql' => 't.number', 'sort' => true],
            'date'   => ['label' => 'common.date', 'sql' => 't.invoice_date', 'fmt' => 'date', 'sort' => true],
            'client' => ['label' => 'invoices.client', 'sql' => 'p.name_en', 'sort' => true, 'hide_in_parent' => true],
            'type'   => ['label' => 'common.type', 'sql' => 't.invoice_type', 'fmt' => 'enum', 'prefix' => 'invoices.type'],
            'total'  => ['label' => 'invoices.total_qar', 'sql' => 't.total_qar', 'fmt' => 'money', 'sort' => true],
            'status' => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'invoices.status'],
            'due'    => ['label' => 'bills.due_date', 'sql' => 't.due_date', 'fmt' => 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'   => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'party_id' => ['type' => 'picker', 'source' => 'clients', 'label' => 'invoices.client', 'sql' => 't.party_id'],
            'from'     => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.invoice_date'],
            'to'       => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.invoice_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['number'];
    }

    public function headerBadges(array $row): string
    {
        return status_badge($row['status'], 'invoices.status');
    }

    public function canEdit(array $row): bool
    {
        return $row['status'] === 'draft' && parent::canEdit($row);
    }

    public function canDelete(array $row): bool
    {
        return $row['status'] === 'draft' && parent::canDelete($row);
    }

    public function links(): array
    {
        return [['bills', 'invoice_id', 'nav.bills']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $rate = $data['currency'] === Money::BASE ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
        if ($rate <= 0) {
            throw ValidationException::one('exchange_rate', 'bills.rate_required');
        }
        if (!empty($data['due_date']) && $data['due_date'] < $data['invoice_date']) {
            throw ValidationException::one('due_date', 'bills.due_before_date');
        }
        $data['exchange_rate'] = $rate;
        if (!$old) {
            $data['number'] = Sequence::monthly('INV', $data['invoice_date']);
            $data['status'] = 'draft';
        } else {
            $data['total_qar'] = Money::toQar((float) $old['total_original'], $rate);
        }
        return $data;
    }

    public function redirectAfterSave(int $id, array $data): string
    {
        return '/portal/invoices/' . $id . '/lines';
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/finance/lines_view', ['kind' => 'invoice', 'row' => $row, 'res' => $this]);
    }

    public function actions(array $row): array
    {
        $a = [];
        $id = (int) $row['id'];
        if ($row['status'] === 'draft' && $this->canEdit($row)) {
            $a[] = ['label' => 'invoices.edit_lines', 'url' => "/portal/invoices/$id/lines"];
            if ((float) $row['total_original'] > 0) {
                $a[] = ['label' => 'invoices.issue', 'url' => "/portal/invoices/$id/issue", 'method' => 'post', 'class' => 'btn-primary', 'confirm' => 'invoices.confirm_issue'];
            }
        }
        if ($row['bill_id']) {
            $bill = DB::row('SELECT id, status FROM bills WHERE id = ?', [$row['bill_id']]);
            if ($bill && in_array($bill['status'], FinanceService::OPEN, true) && Auth::can('finance', 'create')) {
                $a[] = ['label' => 'invoices.record_payment', 'url' => '/portal/bill-payments/create?bill_id=' . $bill['id'], 'class' => 'btn-primary'];
            }
            $a[] = ['label' => 'invoices.income_record', 'url' => '/portal/bills/' . $row['bill_id']];
        }
        if (in_array($row['status'], ['draft', 'issued'], true) && Auth::can('finance', 'edit')) {
            $a[] = ['label' => 'invoices.cancel', 'url' => "/portal/invoices/$id/cancel", 'method' => 'post', 'confirm' => 'invoices.confirm_cancel'];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        $t = [];
        if ($row['bill_id']) {
            $t['payments'] = ['label' => 'nav.payments', 'related' => 'bill-payments', 'filter' => ['bill_id' => (int) $row['bill_id']]];
        }
        return $t + $this->standardTabs($row, ['categories' => ['document'], 'studio' => ['inv']]);
    }
}
