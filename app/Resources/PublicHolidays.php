<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/** Public holidays (HR): not counted as leave days and not marked as leave on the attendance sheet. */
class PublicHolidays extends Resource
{
    public string $key = 'public-holidays';
    public string $table = 'public_holidays';
    public string $module = 'hr';
    public string $recordType = 'public_holiday';
    public string $title = 'holidays.title';
    public string $singular = 'holidays.singular';
    public array $search = ['t.name_en', 't.name_ar'];
    public string $sort = 't.holiday_date DESC';
    public int $perPage = 50;

    public function fields(): array
    {
        return [
            'holiday_date' => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'col' => 4],
            'name_en'      => ['type' => 'text', 'label' => 'holidays.name_en', 'required' => true, 'max' => 120, 'col' => 4, 'no_phone' => true],
            'name_ar'      => ['type' => 'text', 'label' => 'holidays.name_ar', 'max' => 120, 'col' => 4, 'attrs' => ['dir' => 'rtl'], 'no_phone' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'date'    => ['label' => 'common.date', 'sql' => 't.holiday_date', 'fmt' => 'date', 'sort' => true],
            'name_en' => ['label' => 'holidays.name_en', 'sql' => 't.name_en', 'sort' => true],
            'name_ar' => ['label' => 'holidays.name_ar', 'sql' => 't.name_ar'],
        ];
    }

    public function filters(): array
    {
        return [
            'from' => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.holiday_date'],
            'to'   => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.holiday_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'] . ' — ' . fmt_date((string) $row['holiday_date']);
    }

    /** The table comes with database update 020 (Settings → Apply database update). */
    public function canList(): bool
    {
        return DB::hasColumn('public_holidays', 'holiday_date') && parent::canList();
    }

    public function canCreate(): bool
    {
        return DB::hasColumn('public_holidays', 'holiday_date') && Auth::can('hr', 'edit');
    }

    public function canEdit(array $row): bool
    {
        return Auth::can('hr', 'edit');
    }

    public function canDelete(array $row): bool
    {
        return Auth::can('hr', 'edit');
    }

    public function prepare(array $data, ?array $old): array
    {
        $data['name_en'] = preg_replace('/\s+/', ' ', trim((string) $data['name_en']));
        if (DB::value('SELECT id FROM public_holidays WHERE holiday_date = ? AND id <> ? AND deleted_at IS NULL', [$data['holiday_date'], $old['id'] ?? 0])) {
            throw ValidationException::one('holiday_date', 'holidays.duplicate');
        }
        return $data;
    }
}
