<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;

/** Read-only ledger of every semen stock change. No create/edit/delete — see SemenStockService. */
class SemenMovements extends Resource
{
    public string $key = 'semen-movements';
    public string $table = 'semen_movements';
    public string $module = 'semen_stock';
    public string $recordType = 'semen_movement';
    public string $title = 'semen_stock.movements';
    public string $singular = 'semen_stock.movement_singular';
    public array $search = ['st.name_en', 's.stallion_name_free', 't.recipient', 't.notes'];
    public string $sort = 't.id DESC';
    public bool $hasCreatedBy = false;
    public bool $softDelete = false;

    public const TYPES = ['in' => 'semen_stock.type_in', 'out' => 'semen_stock.type_out', 'transfer' => 'semen_stock.type_transfer', 'adjustment' => 'semen_stock.type_adjustment'];

    public function fields(): array
    {
        return [];
    }

    public function from(): string
    {
        return '`semen_movements` t JOIN semen_stock s ON s.id = t.stock_id LEFT JOIN horses st ON st.id = s.stallion_id
                LEFT JOIN horses m ON m.id = t.mare_id LEFT JOIN users u ON u.id = t.created_by';
    }

    public function columns(): array
    {
        return [
            'date'     => ['label' => 'common.date', 'sql' => 't.movement_date', 'fmt' => 'date', 'sort' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.type', 'fmt' => 'badge', 'prefix' => 'semen_stock.type'],
            'stallion' => ['label' => 'semen_stock.stallion', 'sql' => "COALESCE(" . self::nameSql('st') . ", s.stallion_name_free)"],
            'delta'    => ['label' => 'semen_stock.straws', 'sql' => 't.straws_delta', 'fmt' => 'num'],
            'recipient'=> ['label' => 'semen_stock.recipient', 'sql' => 't.recipient'],
            'mare'     => ['label' => 'horse.dam', 'sql' => self::nameSql('m')],
            'reason'   => ['label' => 'semen_stock.reason', 'sql' => 't.reason'],
            'by'       => ['label' => 'common.by', 'sql' => 'u.name'],
        ];
    }

    public function filters(): array
    {
        return [
            'type'        => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.type', 'options' => self::TYPES],
            'stallion_id' => ['type' => 'picker', 'source' => 'stallions', 'label' => 'semen_stock.stallion', 'sql' => 's.stallion_id'],
            'recipient'   => ['type' => 'text', 'label' => 'semen_stock.recipient', 'sql' => 't.recipient'],
            'from'        => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.movement_date'],
            'to'          => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.movement_date'],
        ];
    }

    /** "Out · 09 Oct 2026" instead of "#9" */
    public function label(array $row): string
    {
        $type = isset($row['type']) ? __((string) (self::TYPES[$row['type']] ?? $row['type'])) : '';
        $date = !empty($row['movement_date']) ? fmt_date((string) $row['movement_date']) : '';
        return trim($type . ' · ' . $date, ' ·') ?: '#' . $row['id'];
    }

    /** The movement's own page: a read-only card with the same facts as the list row, linked back to its stock. */
    public function afterDetails(array $row): string
    {
        $cols = $this->columns();
        $select = implode(', ', array_map(fn ($k, $c) => $c['sql'] . ' AS `' . $k . '`', array_keys($cols), $cols));
        $r = \App\Core\DB::row('SELECT ' . $select . ', t.stock_id, t.notes FROM ' . $this->from() . ' WHERE t.id = ?', [(int) $row['id']]);
        if (!$r) {
            return '';
        }
        $dash = '<span class="muted">—</span>';
        $delta = (float) $r['delta'];
        $cells = [
            'common.date'          => $r['date'] ? e(fmt_date((string) $r['date'])) : $dash,
            'common.type'          => status_badge((string) $r['type'], 'semen_stock.type'),
            'semen_stock.stallion'           => $r['stallion'] !== null && $r['stallion'] !== '' ? '<a href="' . e(url('/portal/semen-stock/' . (int) $r['stock_id'])) . '">' . e((string) $r['stallion']) . '</a>' : $dash,
            'semen_stock.straws'   => '<span class="' . ($delta < 0 ? 'neg' : '') . '">' . ($delta > 0 ? '+' : '') . e(rtrim(rtrim(number_format($delta, 3, '.', ','), '0'), '.')) . '</span>',
            'semen_stock.recipient'=> $r['recipient'] !== null && $r['recipient'] !== '' ? e((string) $r['recipient']) : $dash,
            'horse.dam'            => $r['mare'] !== null && $r['mare'] !== '' ? e((string) $r['mare']) : $dash,
            'semen_stock.reason'   => $r['reason'] !== null && $r['reason'] !== '' ? e((string) $r['reason']) : $dash,
            'common.by'            => $r['by'] !== null && $r['by'] !== '' ? e((string) $r['by']) : $dash,
        ];
        $html = '<section class="card"><dl class="dl dl-grid">';
        foreach ($cells as $label => $value) {
            $html .= '<div><dt>' . e(__($label)) . '</dt><dd>' . $value . '</dd></div>';
        }
        if (!empty($r['notes'])) {
            $html .= '<div class="wide"><dt>' . e(__('common.notes')) . '</dt><dd>' . nl2br(e((string) $r['notes'])) . '</dd></div>';
        }
        return $html . '</dl></section>';
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

    public function rowClass(array $row): string
    {
        return match ($row['type'] ?? '') {
            'out' => 'row-pending', 'adjustment' => 'row-alert', default => '',
        };
    }
}
