<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;

/** Stores (Clinic, Feed, Vitamins ...). */
class Inventories extends Resource
{
    public string $key = 'inventories';
    public string $table = 'inventories';
    public string $module = 'inventory';
    public string $recordType = 'inventory';
    public string $title = 'nav.inventories';
    public string $singular = 'inventory.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.location'];
    public string $sort = 't.name_en ASC';

    public const CATEGORIES = ['clinic' => 'inventory.cat_clinic', 'cosmetics' => 'inventory.cat_cosmetics', 'equipment' => 'inventory.cat_equipment', 'feed' => 'inventory.cat_feed',
                               'grooming' => 'inventory.cat_grooming', 'vitamins' => 'inventory.cat_vitamins', 'other' => 'inventory.cat_other'];

    public function fields(): array
    {
        return [
            'name_en'  => ['type' => 'text', 'label' => 'inventory.name_en', 'required' => true, 'max' => 100, 'col' => 6],
            'name_ar'  => ['type' => 'text', 'label' => 'inventory.name_ar', 'max' => 100, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'category' => ['type' => 'select', 'label' => 'finance.category', 'options' => self::CATEGORIES, 'required' => true, 'col' => 6],
            'location' => ['type' => 'text', 'label' => 'inventory.location', 'max' => 120, 'col' => 6],
        ];
    }

    public function columns(): array
    {
        return [
            'name'     => ['label' => 'inventory.name_en', 'sql' => 't.name_en', 'sort' => true],
            'category' => ['label' => 'finance.category', 'sql' => 't.category', 'fmt' => 'enum', 'prefix' => 'inventory.cat'],
            'items'    => ['label' => 'nav.items', 'sql' => '(SELECT COUNT(*) FROM inventory_items i WHERE i.inventory_id = t.id AND i.deleted_at IS NULL)', 'fmt' => 'num'],
            'low'      => ['label' => 'inventory.low_count', 'sql' => '(SELECT COUNT(*) FROM inventory_items i WHERE i.inventory_id = t.id AND i.deleted_at IS NULL AND i.min_quantity > 0 AND i.quantity <= i.min_quantity)', 'fmt' => 'num'],
            'value'    => ['label' => 'inventory.value', 'sql' => '(SELECT COALESCE(SUM(i.quantity * i.unit_price_qar), 0) FROM inventory_items i WHERE i.inventory_id = t.id AND i.deleted_at IS NULL)', 'fmt' => 'money'],
        ];
    }

    public function listTotals(ListQuery $q): array
    {
        return ['value' => $q->sum('(SELECT COALESCE(SUM(i.quantity * i.unit_price_qar), 0) FROM inventory_items i WHERE i.inventory_id = t.id AND i.deleted_at IS NULL)')];
    }

    public function links(): array
    {
        return [['inventory_items', 'inventory_id', 'nav.items']];
    }

    public function tabs(array $row): array
    {
        $n = (int) DB::value('SELECT COUNT(*) FROM inventory_items WHERE inventory_id = ? AND deleted_at IS NULL', [$row['id']]);
        return ['items' => ['label' => 'nav.items', 'related' => 'items', 'filter' => ['inventory_id' => (int) $row['id']], 'count' => $n]];
    }
}
