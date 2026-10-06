<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\ValidationException;
use App\Services\InventoryService;

class DietLogs extends HorseChild
{
    public string $key = 'diet-logs';
    public string $table = 'diet_logs';
    public string $module = 'horse_diet';
    public string $recordType = 'diet_log';
    public string $title = 'nav.diet';
    public string $singular = 'diet.log_entry';
    public array $search = ['h.name_en', 'i.name_en', 't.notes'];
    public string $sort = 't.log_date DESC, t.id DESC';

    public function redirectAfterSave(int $id, array $data): string
    {
        return '/portal/horses/' . $data['horse_id'] . '?tab=diet';
    }

    public function fields(): array
    {
        return [
            'horse_id' => $this->horseField(),
            'log_date' => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 6],
            'feeding'  => ['type' => 'select', 'label' => 'diet.feeding', 'options' => DietPlans::FEEDINGS, 'required' => true, 'col' => 6],
            'item_id'  => ['type' => 'picker', 'source' => 'items', 'filter' => ['category' => 'feed'], 'label' => 'diet.feed_item', 'col' => 6, 'no_add' => true],
            'quantity' => ['type' => 'decimal', 'label' => 'common.quantity', 'min' => 0, 'col' => 6, 'help' => 'diet.qty_help'],
            'notes'    => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`diet_logs` t JOIN horses h ON h.id = t.horse_id LEFT JOIN inventory_items i ON i.id = t.item_id';
    }

    public function columns(): array
    {
        return [
            'log_date' => ['label' => 'common.date', 'sql' => 't.log_date', 'fmt' => 'date', 'sort' => true],
            'horse'    => $this->horseColumn(),
            'feeding'  => ['label' => 'diet.feeding', 'sql' => 't.feeding', 'fmt' => 'enum', 'prefix' => 'diet.feeding'],
            'item'     => ['label' => 'diet.feed_item', 'sql' => 'i.name_en'],
            'quantity' => ['label' => 'common.quantity', 'sql' => "CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM t.quantity)), ' ', COALESCE(i.unit,''))"],
            'notes'    => ['label' => 'common.notes', 'sql' => 't.notes'],
        ];
    }

    public function filters(): array
    {
        return [
            'horse_id' => $this->horseFilter(),
            'feeding'  => ['type' => 'select', 'label' => 'diet.feeding', 'sql' => 't.feeding', 'options' => DietPlans::FEEDINGS],
            'from'     => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.log_date'],
            'to'       => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.log_date'],
        ];
    }

    public function label(array $row): string
    {
        return __('diet.log_entry') . ' ' . fmt_date($row['log_date'] ?? null);
    }

    public function prepare(array $data, ?array $old): array
    {
        if (!empty($data['item_id']) && empty($data['quantity'])) {
            throw ValidationException::one('quantity', 'health.qty_required');
        }
        return $data;
    }

    /** Feed given → inventory goes down → cost allocated to the horse. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created || ($old && ((int) $old['item_id'] !== (int) ($data['item_id'] ?? 0) || (float) $old['quantity'] !== (float) ($data['quantity'] ?? 0)))) {
            InventoryService::syncUsage('diet', $id, (int) $data['horse_id'], $data['item_id'] ?? null, isset($data['quantity']) ? (float) $data['quantity'] : null, $data['log_date']);
        }
    }

    public function afterDelete(array $row): void
    {
        InventoryService::syncUsage('diet', (int) $row['id'], (int) $row['horse_id'], null, null, date('Y-m-d'));
    }
}
