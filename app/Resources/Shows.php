<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;

class Shows extends Resource
{
    public string $key = 'shows';
    public string $table = 'shows';
    public string $module = 'horse_training';
    public string $recordType = 'show';
    public string $title = 'nav.shows';
    public string $singular = 'shows.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.city', 't.organizer'];
    public string $sort = 't.start_date DESC';

    public function canList(): bool
    {
        return Auth::can('horse_training') || Auth::can('horses');
    }

    public function canView(array $row): bool
    {
        return $this->canList();
    }

    public function fields(): array
    {
        return [
            'name_en'    => ['type' => 'text', 'label' => 'common.name_en', 'required' => true, 'max' => 150, 'help' => 'shows.name_help'],
            'name_ar'    => ['type' => 'text', 'label' => 'common.name_ar', 'max' => 150, 'attrs' => ['dir' => 'rtl']],
            'organizer'  => ['type' => 'text', 'label' => 'shows.organizer', 'max' => 80, 'col' => 4],
            'city'       => ['type' => 'text', 'label' => 'shows.city', 'max' => 80, 'col' => 4],
            'country'    => ['type' => 'text', 'label' => 'shows.country', 'max' => 80, 'col' => 4],
            'start_date' => ['type' => 'date', 'label' => 'shows.start_date', 'required' => true, 'col' => 6],
            'end_date'   => ['type' => 'date', 'label' => 'shows.end_date', 'col' => 6],
        ];
    }

    public function columns(): array
    {
        return [
            'start_date' => ['label' => 'common.date', 'sql' => 't.start_date', 'fmt' => 'date', 'sort' => true],
            'name_en'    => ['label' => 'shows.show', 'sql' => 't.name_en', 'sort' => true],
            'organizer'  => ['label' => 'shows.organizer', 'sql' => 't.organizer'],
            'place'      => ['label' => 'shows.city', 'sql' => "CONCAT_WS(', ', t.city, t.country)"],
            'results'    => ['label' => 'shows.results', 'sql' => '(SELECT COUNT(*) FROM show_results r WHERE r.show_id = t.id AND r.deleted_at IS NULL)', 'fmt' => 'num'],
        ];
    }

    public function filters(): array
    {
        return ['from' => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.start_date'], 'to' => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.start_date']];
    }

    public function links(): array
    {
        return [['show_results', 'show_id', 'shows.results'], ['news', 'show_id', 'nav.news']];
    }

    public function tabs(array $row): array
    {
        return ['show-results' => ['label' => 'shows.results', 'related' => 'show-results', 'filter' => ['show_id' => (int) $row['id']]], 'documents' => ['label' => 'common.documents', 'type' => 'documents']];
    }
}
