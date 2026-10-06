<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\ValidationException;

/** Salary advances and loans; the monthly deduction is taken in the payroll run until the balance is zero. */
class EmployeeLoans extends Resource
{
    public string $key = 'employee-loans';
    public string $table = 'employee_loans';
    public string $module = 'payroll';
    public string $recordType = 'employee_loan';
    public string $title = 'nav.loans';
    public string $singular = 'loans.singular';
    public array $search = ['e.name_en', 't.notes'];
    public string $sort = 't.balance_qar > 0 DESC, t.issue_date DESC';
    public ?string $parentField = 'employee_id';
    public ?string $parentResource = 'employees';

    public function fields(): array
    {
        return [
            'employee_id'       => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'required' => true, 'no_add' => true],
            'type'              => ['type' => 'select', 'label' => 'common.type', 'options' => ['advance' => 'loans.type_advance', 'loan' => 'loans.type_loan'], 'required' => true, 'col' => 4],
            'issue_date'        => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'amount_qar'        => ['type' => 'money', 'label' => 'loans.amount', 'required' => true, 'min' => 1, 'col' => 4],
            'monthly_deduction' => ['type' => 'money', 'label' => 'loans.monthly', 'required' => true, 'min' => 0, 'col' => 6],
            'balance_qar'       => ['type' => 'money', 'label' => 'loans.balance', 'min' => 0, 'col' => 6, 'edit_only' => true, 'help' => 'loans.balance_help'],
            'notes'             => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`employee_loans` t JOIN employees e ON e.id = t.employee_id';
    }

    public function columns(): array
    {
        return [
            'employee' => ['label' => 'employees.singular', 'sql' => 'e.name_en', 'sort' => true, 'hide_in_parent' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'loans.type'],
            'date'     => ['label' => 'common.date', 'sql' => 't.issue_date', 'fmt' => 'date', 'sort' => true],
            'amount'   => ['label' => 'loans.amount', 'sql' => 't.amount_qar', 'fmt' => 'money'],
            'monthly'  => ['label' => 'loans.monthly', 'sql' => 't.monthly_deduction', 'fmt' => 'money'],
            'balance'  => ['label' => 'loans.balance', 'sql' => 't.balance_qar', 'fmt' => 'money', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return ['employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'sql' => 't.employee_id'],
                'open' => ['type' => 'raw', 'label' => 'loans.balance', 'options' => ['open' => 'loans.open'], 'sqlFor' => ['open' => 't.balance_qar > 0']]];
    }

    public function label(array $row): string
    {
        return __('loans.type_' . $row['type']) . ' ' . number_format((float) $row['amount_qar'], 2);
    }

    public function prepare(array $data, ?array $old): array
    {
        if ((float) $data['monthly_deduction'] > (float) $data['amount_qar']) {
            throw ValidationException::one('monthly_deduction', 'loans.monthly_too_high');
        }
        if (!$old) {
            $data['balance_qar'] = $data['amount_qar'];
        }
        return $data;
    }
}
