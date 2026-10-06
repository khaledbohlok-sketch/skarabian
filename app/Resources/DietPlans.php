<?php
declare(strict_types=1);

namespace App\Resources;

class DietPlans extends HorseChild
{
    public string $key = 'diet-plans';
    public string $table = 'diet_plans';
    public string $module = 'horse_diet';
    public string $recordType = 'diet_plan';
    public string $title = 'diet.plan';
    public string $singular = 'diet.plan_line';
    public array $search = ['h.name_en', 'i.name_en'];
    public string $sort = "h.name_en, FIELD(t.feeding,'early_morning','late_morning','afternoon','evening','late_evening'), i.name_en";

    public const FEEDINGS = ['early_morning' => 'diet.feeding_early_morning', 'late_morning' => 'diet.feeding_late_morning', 'afternoon' => 'diet.feeding_afternoon', 'evening' => 'diet.feeding_evening', 'late_evening' => 'diet.feeding_late_evening'];

    public function redirectAfterSave(int $id, array $data): string
    {
        return '/portal/horses/' . $data['horse_id'] . '?tab=diet';
    }

    public function fields(): array
    {
        return [
            'horse_id' => $this->horseField(),
            'item_id'  => ['type' => 'picker', 'source' => 'items', 'filter' => ['category' => 'feed'], 'label' => 'diet.feed_item', 'required' => true, 'col' => 6],
            'feeding'  => ['type' => 'select', 'label' => 'diet.feeding', 'options' => self::FEEDINGS, 'required' => true, 'col' => 3],
            'quantity' => ['type' => 'decimal', 'label' => 'common.quantity', 'required' => true, 'min' => 0, 'col' => 3],
            'notes'    => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`diet_plans` t JOIN horses h ON h.id = t.horse_id JOIN inventory_items i ON i.id = t.item_id';
    }

    public function columns(): array
    {
        return [
            'horse'    => $this->horseColumn(),
            'feeding'  => ['label' => 'diet.feeding', 'sql' => 't.feeding', 'fmt' => 'enum', 'prefix' => 'diet.feeding'],
            'item'     => ['label' => 'diet.feed_item', 'sql' => 'i.name_en'],
            'quantity' => ['label' => 'common.quantity', 'sql' => "CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM t.quantity)), ' ', i.unit)"],
        ];
    }

    public function filters(): array
    {
        return ['horse_id' => $this->horseFilter(), 'feeding' => ['type' => 'select', 'label' => 'diet.feeding', 'sql' => 't.feeding', 'options' => self::FEEDINGS]];
    }

    public function label(array $row): string
    {
        return __('diet.plan_line');
    }
}
