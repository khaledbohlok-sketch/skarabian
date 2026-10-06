<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\View;

/** Cash, bank and petty-cash accounts. Balances and account numbers are sensitive. */
class Accounts extends Resource
{
    public string $key = 'accounts';
    public string $table = 'accounts';
    public string $module = 'finance';
    public string $recordType = 'account';
    public string $title = 'nav.accounts';
    public string $singular = 'accounts.singular';
    public array $search = ['t.name', 't.bank_name'];
    public string $sort = 't.active DESC, t.type, t.name';

    public const TYPES = ['cash' => 'accounts.type_cash', 'bank' => 'accounts.type_bank', 'petty_cash' => 'accounts.type_petty_cash'];

    /** Same formula as FinanceService::accountBalances(). */
    public const BALANCE_SQL = "t.opening_balance_qar
        + COALESCE((SELECT SUM(CASE WHEN b.type = 'income' THEN p.amount_qar ELSE -p.amount_qar END) FROM bill_payments p JOIN bills b ON b.id = p.bill_id WHERE p.account_id = t.id AND p.deleted_at IS NULL), 0)
        + COALESCE((SELECT SUM(x.amount_qar) FROM account_transfers x WHERE x.to_account_id = t.id AND x.deleted_at IS NULL), 0)
        - COALESCE((SELECT SUM(x.amount_qar) FROM account_transfers x WHERE x.from_account_id = t.id AND x.deleted_at IS NULL), 0)";

    public function fields(): array
    {
        return [
            'name'                => ['type' => 'text', 'label' => 'accounts.name', 'required' => true, 'max' => 120, 'col' => 6, 'no_phone' => true],
            'type'                => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'bank', 'col' => 3],
            'active'              => ['type' => 'checkbox', 'label' => 'common.active', 'default' => 1, 'col' => 3],
            'bank_name'           => ['type' => 'text', 'label' => 'employees.bank_name', 'max' => 120, 'col' => 6],
            'account_no'          => ['type' => 'encrypted', 'label' => 'accounts.account_no', 'sensitive' => true, 'col' => 6],
            'opening_balance_qar' => ['type' => 'money', 'label' => 'accounts.opening_balance', 'sensitive' => true, 'col' => 6, 'empty' => 0],
            'opening_date'        => ['type' => 'date', 'label' => 'accounts.opening_date', 'col' => 6],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['name'];
    }

    public function columns(): array
    {
        return [
            'name'    => ['label' => 'accounts.name', 'sql' => 't.name', 'sort' => true],
            'type'    => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'accounts.type'],
            'bank'    => ['label' => 'employees.bank_name', 'sql' => 't.bank_name'],
            'active'  => ['label' => 'common.active', 'sql' => 't.active', 'fmt' => 'bool'],
            'balance' => ['label' => 'accounts.balance', 'sql' => '(' . self::BALANCE_SQL . ')', 'fmt' => 'money', 'sensitive' => true],
        ];
    }

    public function listTotals(ListQuery $q): array
    {
        return $this->canSensitive() ? ['balance' => $q->sum(self::BALANCE_SQL)] : [];
    }

    public function listActions(): array
    {
        return Auth::can('finance', 'create') ? [['accounts.new_transfer', '/portal/account-transfers/create', '']] : [];
    }

    public function links(): array
    {
        return [['bill_payments', 'account_id', 'nav.payments'], ['bills', 'account_id', 'nav.bills'], ['account_transfers', 'from_account_id', 'nav.transfers'], ['account_transfers', 'to_account_id', 'nav.transfers']];
    }

    public function tabs(array $row): array
    {
        $t = [];
        if ($this->canSensitive()) {
            $t['statement'] = ['label' => 'accounts.statement', 'render' => fn ($r) => View::partial('portal/finance/account_statement', ['a' => $r])];
        }
        return $t + ['account-transfers' => ['label' => 'nav.transfers', 'render' => fn ($r) => View::partial('portal/finance/account_transfers', ['a' => $r])]]
             + array_intersect_key($this->standardTabs($row), ['history' => 1]);
    }
}
