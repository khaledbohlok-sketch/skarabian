<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;

/** Monthly or yearly budget per expense category; the list shows spent and what is left (red when exceeded). */
class Budgets extends Resource
{
    public string $key = 'budgets';
    public string $table = 'budgets';
    public string $module = 'finance';
    public string $recordType = 'budget';
    public string $title = 'nav.budgets';
    public string $singular = 'budgets.singular';
    public array $search = ['c.name_en'];
    public string $sort = 't.year DESC, t.month DESC, c.name_en';

    private const SPENT = "(SELECT COALESCE(SUM(b.amount_qar), 0) FROM bills b WHERE (b.category_id = t.category_id OR b.subcategory_id = t.category_id)
        AND b.type = 'expense' AND b.status <> 'cancelled' AND b.deleted_at IS NULL AND YEAR(b.bill_date) = t.year AND (t.period_type = 'year' OR MONTH(b.bill_date) = t.month))";

    public static function months(): array
    {
        $m = [];
        for ($i = 1; $i <= 12; $i++) {
            $m[(string) $i] = 'common.month_' . $i;
        }
        return $m;
    }

    public function fields(): array
    {
        return [
            'category_id' => ['type' => 'picker', 'source' => 'top_categories', 'filter' => ['type' => 'expense'], 'label' => 'finance.category', 'required' => true, 'col' => 6, 'no_add' => true],
            'period_type' => ['type' => 'select', 'label' => 'budgets.period', 'options' => ['month' => 'budgets.monthly', 'year' => 'budgets.yearly'], 'required' => true, 'default' => 'month', 'col' => 6],
            'year'        => ['type' => 'int', 'label' => 'budgets.year', 'required' => true, 'min' => 2020, 'default' => fn () => date('Y'), 'col' => 4],
            'month'       => ['type' => 'select', 'label' => 'budgets.month', 'options' => self::months(), 'default' => fn () => date('n'), 'col' => 4, 'help' => 'budgets.month_help'],
            'amount_qar'  => ['type' => 'money', 'label' => 'budgets.amount', 'required' => true, 'min' => 1, 'col' => 4],
        ];
    }

    public function from(): string
    {
        return '`budgets` t JOIN finance_categories c ON c.id = t.category_id';
    }

    public function columns(): array
    {
        return [
            'category' => ['label' => 'finance.category', 'sql' => 'c.name_en', 'sort' => true],
            'period'   => ['label' => 'budgets.period', 'sql' => "IF(t.period_type = 'year', CAST(t.year AS CHAR), CONCAT(t.year, '-', LPAD(t.month, 2, '0')))", 'sort' => true],
            'amount'   => ['label' => 'budgets.amount', 'sql' => 't.amount_qar', 'fmt' => 'money'],
            'spent'    => ['label' => 'budgets.spent', 'sql' => self::SPENT, 'fmt' => 'money'],
            'left'     => ['label' => 'budgets.left', 'sql' => '(t.amount_qar - ' . self::SPENT . ')', 'fmt' => 'money'],
        ];
    }

    public function filters(): array
    {
        return [
            'year'        => ['type' => 'select', 'label' => 'budgets.year', 'sql' => 't.year', 'options' => fn () => array_combine($y = array_map('strval', range((int) date('Y') + 1, 2025)), $y), 'default' => date('Y')],
            'category_id' => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'finance.category', 'sql' => 't.category_id'],
            'exceeded'    => ['type' => 'raw', 'label' => 'budgets.status', 'options' => ['over' => 'budgets.exceeded'], 'sqlFor' => ['over' => self::SPENT . ' > t.amount_qar']],
        ];
    }

    public function rowClass(array $row): string
    {
        return isset($row['left']) && (float) $row['left'] < 0 ? 'row-alert' : '';
    }

    public function label(array $row): string
    {
        $c = (string) DB::value('SELECT name_en FROM finance_categories WHERE id = ?', [$row['category_id']]);
        return $c . ' — ' . ($row['period_type'] === 'year' ? $row['year'] : $row['year'] . '-' . str_pad((string) $row['month'], 2, '0', STR_PAD_LEFT));
    }

    public function prepare(array $data, ?array $old): array
    {
        if ($data['period_type'] === 'year') {
            $data['month'] = null;
        } elseif (empty($data['month'])) {
            throw ValidationException::one('month', 'validation.required');
        }
        $dup = DB::value('SELECT id FROM budgets WHERE category_id = ? AND period_type = ? AND year = ? AND month <=> ? AND deleted_at IS NULL AND id <> ?',
            [$data['category_id'], $data['period_type'], $data['year'], $data['month'], $old['id'] ?? 0]);
        if ($dup) {
            throw ValidationException::one('category_id', 'budgets.duplicate');
        }
        return $data;
    }
}
