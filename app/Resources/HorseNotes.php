<?php
declare(strict_types=1);

namespace App\Resources;

class HorseNotes extends HorseChild
{
    public string $key = 'horse-notes';
    public string $table = 'horse_notes';
    public string $module = 'horse_notes';
    public string $recordType = 'horse_note';
    public string $title = 'horses.notes';
    public string $singular = 'horses.note';
    public array $search = ['t.note', 'h.name_en'];
    public string $sort = 't.id DESC';

    public function fields(): array
    {
        return [
            'horse_id' => $this->horseField(),
            'note'     => ['type' => 'textarea', 'label' => 'horses.note', 'required' => true, 'rows' => 4, 'max' => 3000],
        ];
    }

    public function from(): string
    {
        return '`horse_notes` t JOIN horses h ON h.id = t.horse_id LEFT JOIN users u ON u.id = t.created_by';
    }

    public function columns(): array
    {
        return [
            'created' => ['label' => 'common.date', 'sql' => 't.created_at', 'fmt' => 'datetime', 'sort' => true],
            'horse'   => $this->horseColumn(),
            'note'    => ['label' => 'horses.note', 'sql' => 't.note'],
            'by'      => ['label' => 'common.created_by', 'sql' => 'u.name'],
        ];
    }

    public function filters(): array
    {
        return ['horse_id' => $this->horseFilter()];
    }

    public function label(array $row): string
    {
        return mb_strimwidth((string) $row['note'], 0, 40, '…');
    }
}
