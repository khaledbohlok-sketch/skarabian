<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/**
 * Finance categories and sub-categories. The list is "spell-checked": a new name that is almost the same as an
 * existing one (e.g. "Employees Expenese" next to "Employee Expenses") is refused unless the user confirms it.
 */
class Categories extends Resource
{
    public string $key = 'categories';
    public string $table = 'finance_categories';
    public string $module = 'finance';
    public string $recordType = 'category';
    public string $title = 'nav.categories';
    public string $singular = 'categories.singular';
    public array $search = ['t.name_en', 't.name_ar', 'pc.name_en'];
    public string $sort = 'COALESCE(t.parent_id, t.id), t.parent_id IS NOT NULL, t.sort, t.name_en';
    public int $perPage = 100;

    public const TYPES = ['expense' => 'bills.type_expense', 'income' => 'bills.type_income', 'liability' => 'bills.type_liability', 'any' => 'categories.type_any'];

    public function fields(): array
    {
        return [
            'parent_id'       => ['type' => 'picker', 'source' => 'top_categories', 'label' => 'categories.parent', 'col' => 6, 'help' => 'categories.parent_help', 'no_add' => true],
            'type'            => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'expense', 'col' => 6, 'help' => 'categories.type_help'],
            'name_en'         => ['type' => 'text', 'label' => 'categories.name_en', 'required' => true, 'max' => 100, 'col' => 6, 'no_phone' => true],
            'name_ar'         => ['type' => 'text', 'label' => 'categories.name_ar', 'max' => 100, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'sort'            => ['type' => 'int', 'label' => 'categories.sort', 'col' => 3, 'empty' => 0],
            'active'          => ['type' => 'checkbox', 'label' => 'common.active', 'default' => 1, 'col' => 3],
            'confirm_similar' => ['type' => 'checkbox', 'label' => 'categories.confirm_similar', 'virtual' => true, 'col' => 6],
        ];
    }

    public function from(): string
    {
        return '`finance_categories` t LEFT JOIN finance_categories pc ON pc.id = t.parent_id';
    }

    public function columns(): array
    {
        return [
            'name'   => ['label' => 'categories.name_en', 'sql' => "CONCAT(IF(t.parent_id IS NULL, '', CONCAT(pc.name_en, ' › ')), t.name_en)"],
            'name_ar'=> ['label' => 'categories.name_ar', 'sql' => 't.name_ar'],
            'type'   => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'categories.type'],
            'bills'  => ['label' => 'nav.bills', 'sql' => '(SELECT COUNT(*) FROM bills b WHERE (b.category_id = t.id OR b.subcategory_id = t.id) AND b.deleted_at IS NULL)', 'fmt' => 'num'],
            'active' => ['label' => 'common.active', 'sql' => 't.active', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return ['type' => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.type', 'options' => self::TYPES]];
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'];
    }

    public function canCreate(): bool
    {
        return Auth::can('finance', 'edit');
    }

    public function links(): array
    {
        return [['bills', 'category_id', 'nav.bills'], ['bills', 'subcategory_id', 'nav.bills'], ['finance_categories', 'parent_id', 'categories.subcategories'],
                ['budgets', 'category_id', 'nav.budgets'], ['purchase_orders', 'category_id', 'nav.purchase_orders'], ['invoices', 'category_id', 'nav.invoices']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $data['name_en'] = preg_replace('/\s+/', ' ', trim($data['name_en']));
        if (!empty($data['parent_id'])) {
            $parent = DB::row('SELECT id, type, parent_id FROM finance_categories WHERE id = ?', [$data['parent_id']]);
            if (!$parent || $parent['parent_id'] || ($old && (int) $parent['id'] === (int) $old['id'])) {
                throw ValidationException::one('parent_id', 'categories.bad_parent');
            }
            $data['type'] = $parent['type']; // a sub-category always has its parent's type
        }
        $others = DB::all('SELECT id, name_en FROM finance_categories WHERE deleted_at IS NULL AND id <> ?', [$old['id'] ?? 0]);
        $key = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
        foreach ($others as $o) {
            if ($key($o['name_en']) === $key($data['name_en'])) {
                throw ValidationException::one('name_en', 'categories.duplicate', ['name' => $o['name_en']]);
            }
        }
        if (empty($this->extra['confirm_similar'])) {
            foreach ($others as $o) {
                $a = $key($o['name_en']);
                $b = $key($data['name_en']);
                if (strlen($a) >= 4 && strlen($b) >= 4 && levenshtein($a, $b) <= max(2, (int) floor(min(strlen($a), strlen($b)) / 5))) {
                    throw ValidationException::one('name_en', 'categories.similar', ['name' => $o['name_en']]);
                }
            }
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        // Sub-categories follow a type change of their parent
        if ($old && empty($data['parent_id']) && $data['type'] !== $old['type']) {
            DB::run('UPDATE finance_categories SET type = ? WHERE parent_id = ?', [$data['type'], $id]);
        }
    }
}
