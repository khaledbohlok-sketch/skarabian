<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/**
 * Inventory items. "All inventories" lists everything: an empty filter always means all (the old Items page
 * showed nothing until a filter was chosen). Quantity changes only through stock movements.
 */
class Items extends Resource
{
    public string $key = 'items';
    public string $table = 'inventory_items';
    public string $module = 'inventory';
    public string $recordType = 'item';
    public string $title = 'nav.items';
    public string $singular = 'items.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.sku', 'v.name_en', 's.name_en'];
    public string $sort = 'v.name_en, t.name_en';
    public bool $archivable = true;

    private static function units(): array
    {
        $o = [];
        foreach (DB::all("SELECT value_en FROM lookups WHERE type = 'unit' AND active = 1 ORDER BY sort, value_en") as $r) {
            $o[$r['value_en']] = $r['value_en'];
        }
        return $o;
    }

    public function fields(): array
    {
        return [
            'inventory_id'   => ['type' => 'picker', 'source' => 'inventories', 'label' => 'inventory.singular', 'required' => true, 'col' => 6],
            'sku'            => ['type' => 'text', 'label' => 'items.sku', 'max' => 60, 'col' => 6],
            'name_en'        => ['type' => 'text', 'label' => 'items.name_en', 'required' => true, 'max' => 150, 'col' => 6, 'no_phone' => true],
            'name_ar'        => ['type' => 'text', 'label' => 'items.name_ar', 'max' => 150, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'unit'           => ['type' => 'select', 'label' => 'items.unit', 'options' => fn () => self::units(), 'required' => true, 'default' => 'pcs', 'col' => 3],
            'quantity'       => ['type' => 'decimal', 'label' => 'items.opening_qty', 'min' => 0, 'create_only' => true, 'col' => 3, 'help' => 'items.opening_help', 'empty' => 0],
            'unit_price_qar' => ['type' => 'money', 'label' => 'items.unit_price', 'min' => 0, 'col' => 3, 'empty' => 0],
            'min_quantity'   => ['type' => 'decimal', 'label' => 'items.min_qty', 'min' => 0, 'col' => 3, 'help' => 'items.min_help', 'empty' => 0],
            'supplier_id'    => ['type' => 'picker', 'source' => 'suppliers', 'label' => 'po.supplier', 'col' => 6],
            'expiry_date'    => ['type' => 'date', 'label' => 'items.expiry', 'col' => 6, 'expiry' => true, 'help' => 'items.expiry_help'],
            'notes'          => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`inventory_items` t JOIN inventories v ON v.id = t.inventory_id LEFT JOIN parties s ON s.id = t.supplier_id';
    }

    public function columns(): array
    {
        return [
            'name'      => ['label' => 'items.name_en', 'sql' => 't.name_en', 'sort' => true],
            'inventory' => ['label' => 'inventory.singular', 'sql' => 'v.name_en', 'sort' => true, 'hide_in_parent' => true],
            'qty'       => ['label' => 'common.quantity', 'sql' => 't.quantity', 'fmt' => 'num', 'sort' => true],
            'unit'      => ['label' => 'items.unit', 'sql' => 't.unit'],
            'min'       => ['label' => 'items.min_qty', 'sql' => 't.min_quantity', 'fmt' => 'num'],
            'price'     => ['label' => 'items.unit_price', 'sql' => 't.unit_price_qar', 'fmt' => 'money'],
            'value'     => ['label' => 'inventory.value', 'sql' => 'ROUND(t.quantity * t.unit_price_qar, 2)', 'fmt' => 'money', 'sort' => true],
            'expiry'    => ['label' => 'items.expiry', 'sql' => 't.expiry_date', 'fmt' => 'expiry', 'sort' => true],
            'supplier'  => ['label' => 'po.supplier', 'sql' => 's.name_en'],
        ];
    }

    public function filters(): array
    {
        return [
            'inventory_id' => ['type' => 'picker', 'source' => 'inventories', 'label' => 'items.all_inventories', 'sql' => 't.inventory_id'],
            'category'     => ['type' => 'select', 'label' => 'finance.category', 'sql' => 'v.category', 'options' => Inventories::CATEGORIES],
            'stock'        => ['type' => 'raw', 'label' => 'items.stock_filter', 'options' => ['low' => 'items.f_low', 'out' => 'items.f_out', 'expiring' => 'items.f_expiring', 'expired' => 'items.f_expired'],
                               'sqlFor' => ['low' => 't.min_quantity > 0 AND t.quantity <= t.min_quantity', 'out' => 't.quantity <= 0',
                                            'expiring' => 't.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)', 'expired' => 't.expiry_date < CURDATE()']],
            'supplier_id'  => ['type' => 'picker', 'source' => 'suppliers', 'label' => 'po.supplier', 'sql' => 't.supplier_id'],
            'archived'     => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function rowClass(array $row): string
    {
        return isset($row['qty'], $row['min']) && (float) $row['min'] > 0 && (float) $row['qty'] <= (float) $row['min'] ? 'row-alert' : '';
    }

    public function listTotals(ListQuery $q): array
    {
        return ['value' => $q->sum('ROUND(t.quantity * t.unit_price_qar, 2)')];
    }

    public function links(): array
    {
        return [['stock_movements', 'item_id', 'nav.movements'], ['purchase_order_items', 'item_id', 'nav.purchase_orders'], ['health_records', 'item_id', 'nav.health'],
                ['diet_plans', 'item_id', 'nav.diet'], ['diet_logs', 'item_id', 'nav.diet'], ['bills', 'item_id', 'nav.bills']];
    }

    public function prepare(array $data, ?array $old): array
    {
        if (DB::value('SELECT id FROM inventory_items WHERE inventory_id = ? AND name_en = ? AND id <> ? AND deleted_at IS NULL', [$data['inventory_id'], $data['name_en'], $old['id'] ?? 0])) {
            throw ValidationException::one('name_en', 'items.duplicate');
        }
        return $data;
    }

    /** Opening stock is recorded as the first movement, so the history always explains the quantity. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created && (float) ($data['quantity'] ?? 0) > 0) {
            DB::insert('stock_movements', [
                'item_id' => $id, 'direction' => 'in', 'quantity' => $data['quantity'], 'unit_price_qar' => $data['unit_price_qar'] ?? 0,
                'total_qar' => round((float) $data['quantity'] * (float) ($data['unit_price_qar'] ?? 0), 2), 'movement_date' => date('Y-m-d'),
                'note' => __('items.opening_note'), 'created_by' => Auth::id(),
            ]);
        }
    }

    public function headerBadges(array $row): string
    {
        $b = parent::headerBadges($row);
        if ((float) $row['min_quantity'] > 0 && (float) $row['quantity'] <= (float) $row['min_quantity']) {
            $b .= ' <span class="badge badge-bad">' . e(__('items.low_stock')) . '</span>';
        }
        return $b;
    }

    public function afterDetails(array $row): string
    {
        $q = rtrim(rtrim(number_format((float) $row['quantity'], 3, '.', ','), '0'), '.');
        return '<div class="stat-grid"><div class="stat dark"><div class="k">' . e(__('items.in_stock')) . '</div><div class="v">' . e($q . ' ' . $row['unit']) . '</div></div>'
             . '<div class="stat"><div class="k">' . e(__('inventory.value')) . '</div><div class="v">' . money(round((float) $row['quantity'] * (float) $row['unit_price_qar'], 2)) . '</div></div></div>';
    }

    public function actions(array $row): array
    {
        return Auth::can('inventory', 'create') ? [['label' => 'items.stock_move', 'url' => '/portal/items/' . $row['id'] . '/stock', 'class' => 'btn-primary']] : [];
    }

    public function tabs(array $row): array
    {
        $n = (int) DB::value('SELECT COUNT(*) FROM stock_movements WHERE item_id = ?', [$row['id']]);
        return ['movements' => ['label' => 'nav.movements', 'related' => 'stock-movements', 'filter' => ['item_id' => (int) $row['id']], 'count' => $n]]
             + array_intersect_key($this->standardTabs($row), ['history' => 1]);
    }
}
