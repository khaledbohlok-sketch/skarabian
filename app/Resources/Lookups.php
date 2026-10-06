<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/** Drop-down lists (breeds, colours, locations, positions, nationalities, payment methods, units ...). Values are deactivated, never deleted. */
class Lookups extends Resource
{
    public string $key = 'lookups';
    public string $table = 'lookups';
    public string $module = 'settings';
    public string $recordType = 'lookup';
    public string $title = 'nav.lookups';
    public string $singular = 'lookups.singular';
    public array $search = ['t.value_en', 't.value_ar'];
    public string $sort = 't.type, t.sort, t.value_en';
    public bool $softDelete = false;
    public bool $hasCreatedBy = false;
    public int $perPage = 100;

    public const TYPES = ['breed' => 'lookups.t_breed', 'color' => 'lookups.t_color', 'location' => 'lookups.t_location', 'position' => 'lookups.t_position', 'department' => 'lookups.t_department',
        'nationality' => 'lookups.t_nationality', 'payment_method' => 'lookups.t_payment_method', 'unit' => 'lookups.t_unit', 'embryo_grade' => 'lookups.t_embryo_grade', 'embryo_stage' => 'lookups.t_embryo_stage'];

    public function fields(): array
    {
        return [
            'type'     => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'create_only' => true, 'col' => 6],
            'value_en' => ['type' => 'text', 'label' => 'lookups.value_en', 'required' => true, 'max' => 120, 'col' => 6, 'no_phone' => true],
            'value_ar' => ['type' => 'text', 'label' => 'lookups.value_ar', 'max' => 120, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'sort'     => ['type' => 'int', 'label' => 'categories.sort', 'col' => 3, 'empty' => 0],
            'active'   => ['type' => 'checkbox', 'label' => 'common.active', 'default' => 1, 'col' => 3],
        ];
    }

    public function columns(): array
    {
        return [
            'type'   => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'enum', 'prefix' => 'lookups.t'],
            'value'  => ['label' => 'lookups.value_en', 'sql' => 't.value_en', 'sort' => true, 'link' => true],
            'ar'     => ['label' => 'lookups.value_ar', 'sql' => 't.value_ar'],
            'active' => ['label' => 'common.active', 'sql' => 't.active', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return ['type' => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.type', 'options' => self::TYPES]];
    }

    public function label(array $row): string
    {
        return __(self::TYPES[$row['type']] ?? $row['type']) . ': ' . $row['value_en'];
    }

    public function canList(): bool
    {
        return Auth::can('settings', 'edit');
    }

    public function canView(array $row): bool
    {
        return $this->canList();
    }

    public function canCreate(): bool
    {
        return Auth::can('settings', 'edit');
    }

    public function canEdit(array $row): bool
    {
        return Auth::can('settings', 'edit');
    }

    public function canDelete(array $row): bool
    {
        return false; // deactivate instead: old records keep their value
    }

    public function prepare(array $data, ?array $old): array
    {
        $type = $data['type'] ?? $old['type'];
        $data['value_en'] = preg_replace('/\s+/', ' ', trim($data['value_en']));
        if (DB::value('SELECT id FROM lookups WHERE type = ? AND LOWER(value_en) = LOWER(?) AND id <> ?', [$type, $data['value_en'], $old['id'] ?? 0])) {
            throw ValidationException::one('value_en', 'lookups.duplicate');
        }
        return $data;
    }
}
