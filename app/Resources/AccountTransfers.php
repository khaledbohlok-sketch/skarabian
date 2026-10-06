<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;

/** Money moved between our own accounts (e.g. bank → petty cash). Not income or expense. */
class AccountTransfers extends Resource
{
    public string $key = 'account-transfers';
    public string $table = 'account_transfers';
    public string $module = 'finance';
    public string $recordType = 'account_transfer';
    public string $title = 'nav.transfers';
    public string $singular = 'transfers.singular';
    public array $search = ['f.name', 'a.name', 't.reference_no', 't.notes'];
    public string $sort = 't.transfer_date DESC, t.id DESC';
    public bool $financial = true;

    public function fields(): array
    {
        return [
            'from_account_id' => ['type' => 'picker', 'source' => 'accounts', 'label' => 'transfers.from', 'required' => true, 'col' => 6],
            'to_account_id'   => ['type' => 'picker', 'source' => 'accounts', 'label' => 'transfers.to', 'required' => true, 'col' => 6],
            'transfer_date'   => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'amount_qar'      => ['type' => 'money', 'label' => 'payments.amount', 'required' => true, 'min' => 0.01, 'col' => 4],
            'reference_no'    => ['type' => 'text', 'label' => 'bills.reference', 'max' => 60, 'col' => 4],
            'notes'           => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`account_transfers` t JOIN accounts f ON f.id = t.from_account_id JOIN accounts a ON a.id = t.to_account_id';
    }

    public function columns(): array
    {
        return [
            'date'   => ['label' => 'common.date', 'sql' => 't.transfer_date', 'fmt' => 'date', 'sort' => true],
            'from'   => ['label' => 'transfers.from', 'sql' => 'f.name'],
            'to'     => ['label' => 'transfers.to', 'sql' => 'a.name'],
            'amount' => ['label' => 'payments.amount', 'sql' => 't.amount_qar', 'fmt' => 'money', 'sort' => true],
            'ref'    => ['label' => 'bills.reference', 'sql' => 't.reference_no'],
        ];
    }

    public function filters(): array
    {
        return [
            'account' => ['type' => 'picker', 'source' => 'accounts', 'label' => 'bills.account', 'sql' => 't.from_account_id'],
            'from'    => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.transfer_date'],
            'to'      => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.transfer_date'],
        ];
    }

    public function label(array $row): string
    {
        $n = DB::pairs('SELECT id, name FROM accounts WHERE id IN (?, ?)', [$row['from_account_id'], $row['to_account_id']]);
        return ($n[$row['from_account_id']] ?? '?') . ' → ' . ($n[$row['to_account_id']] ?? '?') . ' — ' . money($row['amount_qar'], 'QAR', false);
    }

    public function prepare(array $data, ?array $old): array
    {
        if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
            throw ValidationException::one('to_account_id', 'transfers.same_account');
        }
        return $data;
    }

    public function redirectAfterSave(int $id, array $data): string
    {
        return '/portal/accounts/' . $data['from_account_id'] . '?tab=account-transfers';
    }
}
