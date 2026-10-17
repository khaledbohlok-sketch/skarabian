<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Settings;

/**
 * Frozen stallion semen stock (straws in liquid nitrogen canisters). The straw count changes ONLY
 * through semen_movements (see SemenMovementsController) — this resource's edit form never touches
 * `straws` directly, matching the spec's "stock changes only through movements" rule.
 */
class SemenStock extends Resource
{
    public string $key = 'semen-stock';
    public string $table = 'semen_stock';
    public string $module = 'semen_stock';
    public string $recordType = 'semen_stock';
    public string $title = 'nav.semen_stock';
    public string $singular = 'semen_stock.singular';
    public array $search = ['st.name_en', 'st.name_ar', 't.stallion_name_free', 't.batch_label', 'tk.name', 'c.name', 'p.name_en'];
    public string $sort = 't.id DESC';
    public bool $archivable = true;
    public ?string $indexView = 'portal/semen/index';

    public function fields(): array
    {
        return [
            'stallion_id'         => ['type' => 'picker', 'source' => 'stallions', 'label' => 'semen_stock.stallion', 'col' => 6, 'help' => 'semen_stock.stallion_help'],
            'stallion_name_free'  => ['type' => 'text', 'label' => 'semen_stock.stallion_free', 'max' => 150, 'col' => 6],
            'batch_label'         => ['type' => 'text', 'label' => 'semen_stock.batch', 'max' => 60, 'col' => 4],
            'canister_id'         => ['type' => 'picker', 'source' => 'canisters', 'label' => 'canisters.singular', 'required' => true, 'col' => 4, 'remember' => true],
            'straw_size'          => ['type' => 'text', 'label' => 'semen_stock.straw_size', 'max' => 20, 'col' => 4, 'help' => 'semen_stock.straw_size_help'],
            'straws'              => ['type' => 'int', 'label' => 'semen_stock.straws', 'readonly' => true, 'edit_only' => true, 'col' => 3],
            'straws_per_dose_min' => ['type' => 'int', 'label' => 'semen_stock.spd_min', 'required' => true, 'min' => 1, 'default' => 1, 'col' => 3],
            'straws_per_dose_max' => ['type' => 'int', 'label' => 'semen_stock.spd_max', 'min' => 1, 'col' => 3, 'help' => 'semen_stock.spd_max_help'],
            'initial_straws'      => ['type' => 'int', 'label' => 'semen_stock.initial_straws', 'virtual' => true, 'create_only' => true, 'min' => 0, 'default' => 0, 'col' => 3, 'help' => 'semen_stock.initial_straws_help'],
            'color_id'            => ['type' => 'select', 'label' => 'semen_stock.color', 'options' => fn () => StrawColors::options(), 'col' => 4],
            'owner_type'          => ['type' => 'segment', 'label' => 'horses.owner_type', 'options' => ['sk' => 'horses.owner_sk', 'client' => 'horses.owner_client'], 'default' => 'sk', 'required' => true, 'col' => 4],
            'owner_party_id'      => ['type' => 'picker', 'source' => 'clients', 'label' => 'horses.owner_party', 'col' => 4],
            'report_date'         => ['type' => 'date', 'label' => 'semen_stock.report_date', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'notes'               => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`semen_stock` t LEFT JOIN horses st ON st.id = t.stallion_id JOIN canisters c ON c.id = t.canister_id JOIN tanks tk ON tk.id = c.tank_id
                LEFT JOIN straw_colors sc ON sc.id = t.color_id LEFT JOIN parties p ON p.id = t.owner_party_id';
    }

    public function columns(): array
    {
        return [
            // names in the user's language (display only)
            'stallion'  => ['label' => 'semen_stock.stallion', 'sql' => 'COALESCE(' . self::nameSql('st') . ', t.stallion_name_free)', 'sort' => true],
            'tank'      => ['label' => 'tanks.singular', 'sql' => 'tk.name', 'sort' => true],
            'canister'  => ['label' => 'canisters.singular', 'sql' => 'c.name', 'sort' => true],
            'straws'    => ['label' => 'semen_stock.straws', 'sql' => 't.straws', 'fmt' => 'num', 'sort' => true],
            'per_dose'  => ['label' => 'semen_stock.per_dose', 'sql' => "IF(t.straws_per_dose_min = t.straws_per_dose_max, CAST(t.straws_per_dose_min AS CHAR), CONCAT(t.straws_per_dose_min,'–',t.straws_per_dose_max))"],
            'doses'     => ['label' => 'semen_stock.doses', 'sql' => "IF(t.straws_per_dose_min = t.straws_per_dose_max, CAST(FLOOR(t.straws / GREATEST(t.straws_per_dose_min,1)) AS CHAR), CONCAT(FLOOR(t.straws / GREATEST(t.straws_per_dose_max,1)),'–',FLOOR(t.straws / GREATEST(t.straws_per_dose_min,1))))"],
            'doses_min' => ['label' => 'semen_stock.doses_min', 'sql' => 'FLOOR(t.straws / GREATEST(t.straws_per_dose_max,1))', 'fmt' => 'num'],
            'report_date' => ['label' => 'semen_stock.report_date', 'sql' => 't.report_date', 'fmt' => 'date', 'sort' => true],
            'size'      => ['label' => 'semen_stock.straw_size', 'sql' => 't.straw_size'],
            'color'     => ['label' => 'semen_stock.color', 'sql' => "COALESCE(" . (\App\Core\Lang::isRtl() ? 'sc.name_ar, sc.name_en' : 'sc.name_en, sc.name_ar') . ")"],
            'color_hex' => ['label' => 'semen_stock.color', 'sql' => 'sc.hex', 'no_export' => true], // for the colour dot on the page only
            'owner'     => ['label' => 'horses.owner_type', 'sql' => "IF(t.owner_type = 'client', " . self::nameSql('p') . ", NULL)"],
        ];
    }

    public function filters(): array
    {
        return [
            'stallion_id' => ['type' => 'picker', 'source' => 'stallions', 'label' => 'semen_stock.stallion', 'sql' => 't.stallion_id'],
            'tank_id'     => ['type' => 'picker', 'source' => 'tanks', 'label' => 'tanks.singular', 'sql' => 'tk.id'],
            'canister_id' => ['type' => 'picker', 'source' => 'canisters', 'label' => 'canisters.singular', 'sql' => 't.canister_id'],
            'color_id'    => ['type' => 'select', 'label' => 'semen_stock.color', 'sql' => 't.color_id', 'options' => fn () => StrawColors::options()],
            'owner_party_id' => ['type' => 'picker', 'source' => 'clients', 'label' => 'horses.owner_party', 'sql' => 't.owner_party_id'],
            'low_stock'   => ['type' => 'bool', 'label' => 'semen_stock.low_only', 'sql' => '(FLOOR(t.straws / GREATEST(t.straws_per_dose_max,1)) <= ' . (int) Settings::get('semen_stock.low_threshold', 2) . ')'],
            'archived'    => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function label(array $row): string
    {
        $name = $row['stallion_name_free'] ?? null;
        if (!empty($row['stallion_id'])) {
            $name = DB::value('SELECT name_en FROM horses WHERE id = ?', [$row['stallion_id']]) ?? $name;
        }
        return trim(($name ?: '—') . (!empty($row['batch_label']) ? ' — ' . $row['batch_label'] : ''));
    }

    public function links(): array
    {
        return [['semen_movements', 'stock_id', 'semen_stock.movements']];
    }

    public function listActions(): array
    {
        $a = [['semen_stock.movements', '/portal/semen-movements', '']];
        if (Auth::can('semen_stock', 'edit')) {
            $a[] = ['tanks.title', '/portal/tanks', ''];
            $a[] = ['straw_colors.title', '/portal/straw-colors', ''];
        }
        return $a;
    }

    public function canCreate(): bool
    {
        return Auth::can('semen_stock', 'create');
    }

    public function canEdit(array $row): bool
    {
        return Auth::can('semen_stock', 'edit');
    }

    public function canDelete(array $row): bool
    {
        return Auth::can('semen_stock', 'delete');
    }

    public function listTotals(ListQuery $q): array
    {
        return [
            'straws' => $q->sum('t.straws'),
            'doses_min' => $q->sum('FLOOR(t.straws / GREATEST(t.straws_per_dose_max,1))'),
        ];
    }

    public function rowClass(array $row): string
    {
        $threshold = (int) Settings::get('semen_stock.low_threshold', 2);
        return ((int) ($row['doses_min'] ?? 0)) <= $threshold ? 'row-alert' : '';
    }

    public function prepare(array $data, ?array $old): array
    {
        $errors = [];
        if (empty($data['stallion_id']) && empty($data['stallion_name_free'])) {
            $errors['stallion_name_free'] = __('semen_stock.need_stallion');
        }
        if (!empty($data['stallion_id'])) {
            $data['stallion_name_free'] = null; // the linked horse's name is authoritative
        }
        if (empty($data['straws_per_dose_max']) || (int) $data['straws_per_dose_max'] < (int) $data['straws_per_dose_min']) {
            $data['straws_per_dose_max'] = $data['straws_per_dose_min'];
        }
        if (($data['owner_type'] ?? 'sk') === 'client' && empty($data['owner_party_id'])) {
            $errors['owner_party_id'] = __('validation.required');
        } elseif (($data['owner_type'] ?? 'sk') === 'sk') {
            $data['owner_party_id'] = null;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $data;
    }

    /** New rows start at 0 straws; an initial IN movement (if any were entered) brings it up to count. */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created && !empty($this->extra['initial_straws'])) {
            \App\Services\SemenStockService::movement($id, 'in', (int) $this->extra['initial_straws'], ['notes' => __('semen_stock.initial_stock_note')]);
        }
    }

    public function actions(array $row): array
    {
        $a = [];
        if (Auth::can('semen_stock', 'edit')) {
            $a[] = ['label' => 'semen_stock.record_movement', 'url' => '/portal/semen-stock/' . $row['id'] . '/movement', 'class' => 'btn-primary'];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        return [
            'movements' => ['label' => 'semen_stock.movements', 'render' => fn ($r) => \App\Core\View::partial('portal/semen/movements_tab', ['row' => $r])],
        ] + $this->standardTabs($row);
    }
}
