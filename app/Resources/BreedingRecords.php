<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\HorseRules;

class BreedingRecords extends Resource
{
    public string $key = 'breeding-records';
    public string $table = 'breeding_records';
    public string $module = 'horse_breeding';
    public string $recordType = 'breeding';
    public string $title = 'nav.breeding';
    public string $singular = 'breeding.singular';
    public array $search = ['m.name_en', 's.name_en', 'e.code'];
    public string $sort = "FIELD(t.status,'pregnant','open','not_pregnant','foaled','lost'), t.expected_foaling_date";
    public ?string $parentField = 'mare_id';
    public ?string $parentResource = 'horses';

    public const METHODS = ['natural' => 'breeding.method_natural', 'ai_fresh' => 'breeding.method_ai_fresh', 'ai_chilled' => 'breeding.method_ai_chilled', 'ai_frozen' => 'breeding.method_ai_frozen', 'embryo_transfer' => 'breeding.method_embryo_transfer', 'icsi' => 'breeding.method_icsi'];
    public const STATUSES = ['open' => 'breeding.status_open', 'pregnant' => 'breeding.status_pregnant', 'not_pregnant' => 'breeding.status_not_pregnant', 'foaled' => 'breeding.status_foaled', 'lost' => 'breeding.status_lost'];

    public function scope(array &$where, array &$params): void
    {
        if (Auth::horseScopeAssigned()) {
            $where[] = 't.mare_id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'asg', $params);
        }
    }

    public function fields(): array
    {
        return [
            'mare_id'               => ['type' => 'picker', 'source' => 'mares', 'label' => 'breeding.mare', 'required' => true, 'help' => 'breeding.mare_help'],
            'stallion_id'           => ['type' => 'picker', 'source' => 'stallions', 'label' => 'breeding.stallion'],
            'method'                => ['type' => 'select', 'label' => 'breeding.method', 'options' => self::METHODS, 'required' => true, 'default' => 'natural', 'col' => 6],
            'embryo_id'             => ['type' => 'picker', 'source' => 'embryos', 'label' => 'breeding.embryo', 'col' => 6, 'help' => 'breeding.embryo_help'],
            'start_date'            => ['type' => 'date', 'label' => 'breeding.start_date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'expected_foaling_date' => ['type' => 'date', 'label' => 'breeding.expected_foaling', 'col' => 4, 'help' => 'breeding.expected_help'],
            'status'                => ['type' => 'select', 'label' => 'common.status', 'options' => self::STATUSES, 'required' => true, 'default' => 'open', 'col' => 4],
            'notes'                 => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`breeding_records` t JOIN horses m ON m.id = t.mare_id LEFT JOIN horses s ON s.id = t.stallion_id LEFT JOIN embryos e ON e.id = t.embryo_id';
    }

    public function columns(): array
    {
        return [
            'mare'     => ['label' => 'breeding.mare', 'sql' => 'm.name_en', 'sort' => true],
            'stallion' => ['label' => 'breeding.stallion', 'sql' => "COALESCE(s.name_en, e.code)"],
            'method'   => ['label' => 'breeding.method', 'sql' => 't.method', 'fmt' => 'enum', 'prefix' => 'breeding.method'],
            'start'    => ['label' => 'breeding.start_date', 'sql' => 't.start_date', 'fmt' => 'date', 'sort' => true],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'breeding.status'],
            'due'      => ['label' => 'breeding.expected_foaling', 'sql' => 't.expected_foaling_date', 'fmt' => 'date', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'status' => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'due'    => ['type' => 'raw', 'label' => 'breeding.foaling_due', 'options' => ['30' => 'breeding.due_30'], 'sqlFor' => ['30' => "t.status = 'pregnant' AND t.expected_foaling_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"]],
            'mare_id' => ['type' => 'picker', 'source' => 'mares', 'label' => 'breeding.mare', 'sql' => 't.mare_id'],
        ];
    }

    public function label(array $row): string
    {
        $m = DB::value('SELECT name_en FROM horses WHERE id = ?', [$row['mare_id']]);
        return $m . ' — ' . fmt_date($row['start_date']);
    }

    public function prepare(array $data, ?array $old): array
    {
        if (in_array($data['method'], ['embryo_transfer', 'icsi'], true) === false && empty($data['stallion_id'])) {
            throw ValidationException::one('stallion_id', 'validation.required');
        }
        if ($data['method'] === 'embryo_transfer' && empty($data['embryo_id']) && empty($data['stallion_id'])) {
            throw ValidationException::one('embryo_id', 'breeding.embryo_or_stallion');
        }
        if (empty($data['expected_foaling_date']) && in_array($data['status'], ['open', 'pregnant'], true)) {
            $data['expected_foaling_date'] = HorseRules::expectedFoaling($data['start_date']);
        }
        if (!empty($data['embryo_id']) && empty($data['stallion_id'])) {
            $data['stallion_id'] = DB::value('SELECT sire_id FROM embryos WHERE id = ?', [$data['embryo_id']]);
        }
        return $data;
    }

    public function actions(array $row): array
    {
        $a = [];
        if (in_array($row['status'], ['open', 'pregnant'], true) && Auth::can('horse_breeding', 'create')) {
            $a[] = ['label' => 'horses.record_foaling', 'url' => '/portal/horses/' . $row['mare_id'] . '/foaling?breeding_record_id=' . $row['id'], 'class' => 'btn-primary'];
        }
        if (\App\Services\Studio::canCreate('cover') && $row['method'] !== 'embryo_transfer') {
            $a[] = ['label' => 'studio.type_cover', 'url' => '/portal/studio/new/cover?record_id=' . $row['id']];
        }
        return $a;
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/horses/pregnancy_checks', ['br' => $row]);
    }

    public function tabs(array $row): array
    {
        return ['documents' => ['label' => 'common.documents', 'type' => 'documents', 'studio' => ['cover']]];
    }
}
