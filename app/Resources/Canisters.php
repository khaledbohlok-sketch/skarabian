<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;

/** Canisters inside a tank. Each semen_stock row belongs to exactly one canister. */
class Canisters extends Resource
{
    public string $key = 'canisters';
    public string $table = 'canisters';
    public string $module = 'semen_stock';
    public string $recordType = 'canister';
    public string $title = 'canisters.title';
    public string $singular = 'canisters.singular';
    public array $search = ['t.name', 'tk.name'];
    public string $sort = 'tk.name, t.name';
    public ?string $parentField = 'tank_id';
    public ?string $parentResource = 'tanks';

    public function fields(): array
    {
        return [
            'tank_id' => ['type' => 'picker', 'source' => 'tanks', 'label' => 'tanks.singular', 'required' => true, 'col' => 6, 'remember' => true],
            'name'    => ['type' => 'text', 'label' => 'canisters.name', 'required' => true, 'max' => 60, 'col' => 6],
            'notes'   => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`canisters` t JOIN tanks tk ON tk.id = t.tank_id';
    }

    public function columns(): array
    {
        return [
            'tank'   => ['label' => 'tanks.singular', 'sql' => 'tk.name', 'sort' => true],
            'name'   => ['label' => 'canisters.name', 'sql' => 't.name', 'sort' => true],
            'straws' => ['label' => 'semen_stock.straws', 'sql' => '(SELECT COALESCE(SUM(s.straws),0) FROM semen_stock s WHERE s.canister_id = t.id AND s.deleted_at IS NULL)', 'fmt' => 'num'],
            'notes'  => ['label' => 'common.notes', 'sql' => 't.notes'],
        ];
    }

    public function filters(): array
    {
        return [
            'tank_id' => ['type' => 'picker', 'source' => 'tanks', 'label' => 'tanks.singular', 'sql' => 't.tank_id'],
        ];
    }

    public function label(array $row): string
    {
        return (string) ($row['name'] ?? '#' . $row['id']);
    }

    public function links(): array
    {
        return [['semen_stock', 'canister_id', 'nav.semen_stock']];
    }

    public function canCreate(): bool
    {
        return Auth::can('semen_stock', 'edit');
    }

    public function canEdit(array $row): bool
    {
        return Auth::can('semen_stock', 'edit');
    }

    public function canDelete(array $row): bool
    {
        return Auth::can('semen_stock', 'edit');
    }
}
