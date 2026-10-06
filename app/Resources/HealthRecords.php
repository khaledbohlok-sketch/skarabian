<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\ValidationException;
use App\Services\InventoryService;

class HealthRecords extends HorseChild
{
    public string $key = 'health-records';
    public string $table = 'health_records';
    public string $module = 'horse_health';
    public string $recordType = 'health_record';
    public string $title = 'nav.health';
    public string $singular = 'health.singular';
    public array $search = ['t.title', 't.details', 'h.name_en', 't.vet_name'];
    public string $sort = 't.record_date DESC, t.id DESC';

    public const TYPES = ['vaccination' => 'health.type_vaccination', 'vet_visit' => 'health.type_vet_visit', 'deworming' => 'health.type_deworming', 'dental' => 'health.type_dental', 'farrier' => 'health.type_farrier', 'treatment' => 'health.type_treatment', 'medicine' => 'health.type_medicine', 'other' => 'health.type_other'];

    public function fields(): array
    {
        return [
            'horse_id'        => $this->horseField(),
            'type'            => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'col' => 6],
            'record_date'     => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 6],
            'title'           => ['type' => 'text', 'label' => 'health.title', 'required' => true, 'max' => 150, 'col' => 12],
            'details'         => ['type' => 'textarea', 'label' => 'health.details', 'rows' => 3],
            'vet_employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'health.vet_employee', 'col' => 6],
            'vet_name'        => ['type' => 'text', 'label' => 'health.vet_external', 'max' => 120, 'col' => 6],
            'item_id'         => ['type' => 'picker', 'source' => 'items', 'label' => 'health.item', 'col' => 6, 'help' => 'health.item_help'],
            'quantity'        => ['type' => 'decimal', 'label' => 'common.quantity', 'col' => 3, 'min' => 0],
            'cost_qar'        => ['type' => 'money', 'label' => 'health.cost', 'col' => 3, 'min' => 0],
            'next_due_date'   => ['type' => 'date', 'label' => 'health.next_due', 'col' => 6, 'help' => 'health.next_due_help'],
        ];
    }

    public function columns(): array
    {
        return [
            'record_date' => ['label' => 'common.date', 'sql' => 't.record_date', 'fmt' => 'date', 'sort' => true],
            'horse'       => $this->horseColumn(),
            'type'        => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'health.type'],
            'title'       => ['label' => 'health.title', 'sql' => 't.title'],
            'next_due'    => ['label' => 'health.next_due', 'sql' => 't.next_due_date', 'fmt' => 'expiry', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'horse_id' => $this->horseFilter(),
            'type'     => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.type', 'options' => self::TYPES],
            'due'      => ['type' => 'raw', 'label' => 'health.due_filter', 'options' => ['overdue' => 'common.overdue', 'soon' => 'common.due_soon'],
                           'sqlFor' => ['overdue' => 't.next_due_date < CURDATE()', 'soon' => 't.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)']],
            'from'     => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.record_date'],
            'to'       => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.record_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['title'];
    }

    public function prepare(array $data, ?array $old): array
    {
        if (!empty($data['item_id']) && empty($data['quantity'])) {
            throw ValidationException::one('quantity', 'health.qty_required');
        }
        if (!empty($data['next_due_date']) && $data['next_due_date'] < $data['record_date']) {
            throw ValidationException::one('next_due_date', 'health.due_before_date');
        }
        return $data;
    }

    /** Medicine used → inventory goes down → cost allocated to the horse. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created || ($old && ((int) $old['item_id'] !== (int) ($data['item_id'] ?? 0) || (float) $old['quantity'] !== (float) ($data['quantity'] ?? 0) || (int) $old['horse_id'] !== (int) $data['horse_id']))) {
            InventoryService::syncUsage('health', $id, (int) $data['horse_id'], $data['item_id'] ?? null, isset($data['quantity']) ? (float) $data['quantity'] : null, $data['record_date']);
        }
    }

    public function afterDelete(array $row): void
    {
        InventoryService::syncUsage('health', (int) $row['id'], (int) $row['horse_id'], null, null, date('Y-m-d'));
    }
}
