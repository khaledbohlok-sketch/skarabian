<?php
declare(strict_types=1);

namespace App\Resources;

class TrainingLogs extends HorseChild
{
    public string $key = 'training-logs';
    public string $table = 'training_logs';
    public string $module = 'horse_training';
    public string $recordType = 'training_log';
    public string $title = 'nav.training';
    public string $singular = 'training.singular';
    public array $search = ['h.name_en', 't.activity', 't.notes'];
    public string $sort = 't.log_date DESC, t.id DESC';

    public function fields(): array
    {
        return [
            'horse_id'            => $this->horseField(),
            'log_date'            => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'activity'            => ['type' => 'text', 'label' => 'training.activity', 'required' => true, 'max' => 150, 'col' => 8],
            'trainer_employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'training.trainer', 'col' => 8],
            'duration_min'        => ['type' => 'int', 'label' => 'training.duration', 'min' => 0, 'col' => 4],
            'notes'               => ['type' => 'textarea', 'label' => 'common.notes', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`training_logs` t JOIN horses h ON h.id = t.horse_id LEFT JOIN employees e ON e.id = t.trainer_employee_id';
    }

    public function columns(): array
    {
        return [
            'log_date' => ['label' => 'common.date', 'sql' => 't.log_date', 'fmt' => 'date', 'sort' => true],
            'horse'    => $this->horseColumn(),
            'activity' => ['label' => 'training.activity', 'sql' => 't.activity'],
            'trainer'  => ['label' => 'training.trainer', 'sql' => 'e.name_en'],
            'duration' => ['label' => 'training.duration', 'sql' => 't.duration_min', 'fmt' => 'num'],
        ];
    }

    public function filters(): array
    {
        return ['horse_id' => $this->horseFilter(), 'from' => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.log_date'], 'to' => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.log_date']];
    }

    public function label(array $row): string
    {
        return (string) $row['activity'];
    }
}
