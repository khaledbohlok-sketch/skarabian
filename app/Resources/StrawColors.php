<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;

/** Manageable straw colors for the Semen Inventory (shown as a colored badge on each stock row). */
class StrawColors extends Resource
{
    public string $key = 'straw-colors';
    public string $table = 'straw_colors';
    public string $module = 'semen_stock';
    public string $recordType = 'straw_color';
    public string $title = 'straw_colors.title';
    public string $singular = 'straw_colors.singular';
    public array $search = ['t.name_en', 't.name_ar'];
    public string $sort = 't.sort, t.name_en';
    public int $perPage = 100;
    public bool $hasCreatedBy = false;

    /** Active colors, plus a switched-off one still on a stock row: its edit form keeps it (and saving does not clear it). */
    public static function options(): array
    {
        $o = [];
        foreach (\App\Core\DB::all('SELECT id, name_en, name_ar FROM straw_colors t WHERE active = 1
                OR EXISTS (SELECT 1 FROM semen_stock s WHERE s.color_id = t.id AND s.deleted_at IS NULL) ORDER BY sort, name_en') as $c) {
            $o[$c['id']] = loc($c, 'name');
        }
        return $o;
    }

    public static function hexOf(?int $id): string
    {
        return $id ? (string) (\App\Core\DB::value('SELECT hex FROM straw_colors WHERE id = ?', [$id]) ?: '#8a8f98') : '#8a8f98';
    }

    public function fields(): array
    {
        return [
            'name_en' => ['type' => 'text', 'label' => 'categories.name_en', 'required' => true, 'max' => 60, 'col' => 4, 'no_phone' => true],
            'name_ar' => ['type' => 'text', 'label' => 'categories.name_ar', 'max' => 60, 'col' => 4, 'attrs' => ['dir' => 'rtl']],
            'hex'     => ['type' => 'color', 'label' => 'straw_colors.hex', 'required' => true, 'default' => '#8a8f98', 'col' => 2],
            'sort'    => ['type' => 'int', 'label' => 'categories.sort', 'col' => 2, 'empty' => 0],
            'active'  => ['type' => 'checkbox', 'label' => 'common.active', 'default' => 1, 'col' => 4],
        ];
    }

    public function columns(): array
    {
        return [
            'swatch'  => ['label' => 'straw_colors.hex', 'sql' => 't.hex', 'fmt' => 'swatch'],
            'name_en' => ['label' => 'categories.name_en', 'sql' => 't.name_en', 'sort' => true],
            'name_ar' => ['label' => 'categories.name_ar', 'sql' => 't.name_ar'],
            'rows'    => ['label' => 'straw_colors.title', 'sql' => '(SELECT COUNT(*) FROM semen_stock s WHERE s.color_id = t.id AND s.deleted_at IS NULL)', 'fmt' => 'num'],
            'active'  => ['label' => 'common.active', 'sql' => 't.active', 'fmt' => 'bool'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'];
    }

    public function links(): array
    {
        return [['semen_stock', 'color_id', 'straw_colors.title']];
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

    public function prepare(array $data, ?array $old): array
    {
        $data['name_en'] = preg_replace('/\s+/', ' ', trim($data['name_en']));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data['hex'])) {
            throw \App\Core\ValidationException::one('hex', 'validation.invalid');
        }
        return $data;
    }
}
