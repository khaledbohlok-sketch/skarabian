<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;

/** Base for records that belong to a horse: shown in the horse's tabs, limited to assigned horses for grooms. */
abstract class HorseChild extends Resource
{
    public ?string $parentField = 'horse_id';
    public ?string $parentResource = 'horses';
    protected string $horseCol = 't.horse_id';

    public function scope(array &$where, array &$params): void
    {
        if (Auth::horseScopeAssigned()) {
            $where[] = $this->horseCol . ' IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'asg', $params);
        }
    }

    public function canView(array $row): bool
    {
        return parent::canView($row) && Auth::canSeeHorse((int) ($row['horse_id'] ?? 0));
    }

    protected function horseField(array $extra = []): array
    {
        return ['type' => 'picker', 'source' => 'horses', 'label' => 'horses.singular', 'required' => true, 'no_add' => true] + $extra;
    }

    protected function horseFilter(): array
    {
        return ['type' => 'picker', 'source' => 'horses', 'label' => 'horses.singular', 'sql' => $this->horseCol];
    }

    protected function horseColumn(): array
    {
        return ['label' => 'horses.singular', 'sql' => 'h.name_en', 'sort' => true, 'hide_in_parent' => true];
    }

    public function from(): string
    {
        return '`' . $this->table . '` t JOIN horses h ON h.id = ' . $this->horseCol;
    }
}
