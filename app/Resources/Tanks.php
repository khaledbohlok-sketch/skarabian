<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;

/** Liquid nitrogen tanks. Each tank holds several canisters (see Canisters). */
class Tanks extends Resource
{
    public string $key = 'tanks';
    public string $table = 'tanks';
    public string $module = 'semen_stock';
    public string $recordType = 'tank';
    public string $title = 'tanks.title';
    public string $singular = 'tanks.singular';
    public array $search = ['t.name', 't.location'];
    public string $sort = 't.name';

    public function fields(): array
    {
        return [
            'name'                 => ['type' => 'text', 'label' => 'tanks.name', 'required' => true, 'max' => 100, 'col' => 6],
            'location'             => ['type' => 'text', 'label' => 'tanks.location', 'max' => 150, 'col' => 6],
            'last_refill_date'     => ['type' => 'date', 'label' => 'tanks.last_refill', 'col' => 4],
            'refill_interval_days' => ['type' => 'int', 'label' => 'tanks.refill_interval', 'col' => 4, 'min' => 1, 'help' => 'tanks.refill_interval_help'],
            'notes'                => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3, 'col' => 12],
        ];
    }

    public function columns(): array
    {
        return [
            'name'     => ['label' => 'tanks.name', 'sql' => 't.name', 'sort' => true],
            'location' => ['label' => 'tanks.location', 'sql' => 't.location'],
            'canisters'=> ['label' => 'canisters.title', 'sql' => '(SELECT COUNT(*) FROM canisters c WHERE c.tank_id = t.id AND c.deleted_at IS NULL)', 'fmt' => 'num'],
            'straws'   => ['label' => 'semen_stock.straws', 'sql' => '(SELECT COALESCE(SUM(s.straws),0) FROM semen_stock s JOIN canisters c ON c.id = s.canister_id WHERE c.tank_id = t.id AND s.deleted_at IS NULL AND s.archived_at IS NULL)', 'fmt' => 'num'],
            'refill'   => ['label' => 'tanks.next_refill_due', 'sql' => 'DATE_ADD(t.last_refill_date, INTERVAL t.refill_interval_days DAY)', 'fmt' => 'date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['name'];
    }

    public function listActions(): array
    {
        return [['tanks.map', '/portal/tanks/map', '']];
    }

    public function links(): array
    {
        return [['canisters', 'tank_id', 'canisters.title']];
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

    public function rowClass(array $row): string
    {
        $due = $this->dueDate($row);
        return $due && $due < date('Y-m-d') ? 'row-alert' : '';
    }

    public function dueDate(array $row): ?string
    {
        if (empty($row['last_refill_date']) || empty($row['refill_interval_days'])) {
            return null;
        }
        return date('Y-m-d', strtotime($row['last_refill_date'] . ' +' . (int) $row['refill_interval_days'] . ' days'));
    }

    public function tabs(array $row): array
    {
        return [
            'canisters' => ['label' => 'canisters.title', 'related' => 'canisters', 'filter' => ['tank_id' => (int) $row['id']]],
            'refills'   => ['label' => 'tanks.refill_log', 'render' => fn ($r) => \App\Core\View::partial('portal/tanks/refills', ['tank' => $r])],
        ] + $this->standardTabs($row);
    }
}
