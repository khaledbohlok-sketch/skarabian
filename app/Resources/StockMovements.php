<?php
declare(strict_types=1);

namespace App\Resources;

/** Read-only movement history (in, out to a horse, adjustments). Movements are never edited or deleted. */
class StockMovements extends Resource
{
    public string $key = 'stock-movements';
    public string $table = 'stock_movements';
    public string $module = 'inventory';
    public string $recordType = 'stock_movement';
    public string $title = 'nav.movements';
    public string $singular = 'movements.singular';
    public array $search = ['i.name_en', 't.note', 'h.name_en'];
    public string $sort = 't.movement_date DESC, t.id DESC';
    public bool $softDelete = false;

    public const DIRECTIONS = ['in' => 'movements.dir_in', 'out' => 'movements.dir_out', 'adjust' => 'movements.dir_adjust'];

    public function fields(): array
    {
        return [
            'item_id'           => ['type' => 'picker', 'source' => 'items', 'label' => 'items.singular'],
            'direction'         => ['type' => 'select', 'label' => 'movements.direction', 'options' => self::DIRECTIONS],
            'movement_date'     => ['type' => 'date', 'label' => 'common.date'],
            'quantity'          => ['type' => 'decimal', 'label' => 'common.quantity'],
            'unit_price_qar'    => ['type' => 'money', 'label' => 'items.unit_price'],
            'total_qar'         => ['type' => 'money', 'label' => 'movements.total'],
            'horse_id'          => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse'],
            'purchase_order_id' => ['type' => 'picker', 'source' => 'purchase_orders', 'label' => 'po.singular'],
            'bill_id'           => ['type' => 'picker', 'source' => 'bills', 'label' => 'bills.singular'],
            'note'              => ['type' => 'text', 'label' => 'common.notes'],
        ];
    }

    public function from(): string
    {
        return '`stock_movements` t JOIN inventory_items i ON i.id = t.item_id JOIN inventories v ON v.id = i.inventory_id LEFT JOIN horses h ON h.id = t.horse_id LEFT JOIN users u ON u.id = t.created_by';
    }

    public function columns(): array
    {
        return [
            'date'  => ['label' => 'common.date', 'sql' => 't.movement_date', 'fmt' => 'date', 'sort' => true],
            'item'  => ['label' => 'items.singular', 'sql' => 'i.name_en', 'sort' => true, 'hide_in_parent' => true],
            'dir'   => ['label' => 'movements.direction', 'sql' => 't.direction', 'fmt' => 'badge', 'prefix' => 'movements.dir'],
            'qty'   => ['label' => 'common.quantity', 'sql' => 't.quantity', 'fmt' => 'num'],
            'unit'  => ['label' => 'items.unit', 'sql' => 'i.unit'],
            'total' => ['label' => 'movements.total', 'sql' => 't.total_qar', 'fmt' => 'money'],
            'horse' => ['label' => 'bills.horse', 'sql' => 'h.name_en'],
            'note'  => ['label' => 'common.notes', 'sql' => 't.note'],
            'by'    => ['label' => 'movements.by', 'sql' => 'u.name'],
        ];
    }

    public function filters(): array
    {
        return [
            'item_id'      => ['type' => 'picker', 'source' => 'items', 'label' => 'items.singular', 'sql' => 't.item_id'],
            'inventory_id' => ['type' => 'picker', 'source' => 'inventories', 'label' => 'inventory.singular', 'sql' => 'i.inventory_id'],
            'direction'    => ['type' => 'select', 'label' => 'movements.direction', 'sql' => 't.direction', 'options' => self::DIRECTIONS],
            'horse_id'     => ['type' => 'picker', 'source' => 'horses', 'label' => 'bills.horse', 'sql' => 't.horse_id'],
            'from'         => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.movement_date'],
            'to'           => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.movement_date'],
        ];
    }

    public function label(array $row): string
    {
        return __('movements.singular') . ' #' . $row['id'];
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(array $row): bool
    {
        return false;
    }

    public function canDelete(array $row): bool
    {
        return false;
    }
}
