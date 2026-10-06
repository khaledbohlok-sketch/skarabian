<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Services\Cache;
use App\Services\Sequence;

/** Show result → appears on the horse profile and the public Champions page automatically. */
class ShowResults extends HorseChild
{
    public string $key = 'show-results';
    public string $table = 'show_results';
    public string $module = 'horse_training';
    public string $recordType = 'show_result';
    public string $title = 'horses.shows_titles';
    public string $singular = 'shows.result';
    public array $search = ['h.name_en', 's.name_en', 't.title_en', 't.class_name'];
    public string $sort = 's.start_date DESC, t.placing';

    public const MEDALS = ['none' => 'shows.medal_none', 'gold' => 'shows.medal_gold', 'silver' => 'shows.medal_silver', 'bronze' => 'shows.medal_bronze'];

    public function canList(): bool
    {
        return Auth::can('horse_training') || Auth::can('horses');
    }

    public function canView(array $row): bool
    {
        return $this->canList() && Auth::canSeeHorse((int) $row['horse_id']);
    }

    public function fields(): array
    {
        return [
            'show_id'    => ['type' => 'picker', 'source' => 'shows', 'label' => 'shows.show', 'required' => true],
            'horse_id'   => $this->horseField(),
            'class_name' => ['type' => 'text', 'label' => 'shows.class', 'max' => 150, 'col' => 6],
            'placing'    => ['type' => 'int', 'label' => 'shows.placing', 'min' => 1, 'col' => 3],
            'medal'      => ['type' => 'select', 'label' => 'shows.medal', 'options' => self::MEDALS, 'default' => 'none', 'required' => true, 'col' => 3],
            'title_en'   => ['type' => 'text', 'label' => 'shows.title_en', 'max' => 150, 'col' => 6, 'help' => 'shows.title_help'],
            'title_ar'   => ['type' => 'text', 'label' => 'shows.title_ar', 'max' => 150, 'col' => 6, 'attrs' => ['dir' => 'rtl']],
            'score'      => ['type' => 'decimal', 'label' => 'shows.score', 'col' => 4],
            'prize_qar'  => ['type' => 'money', 'label' => 'shows.prize', 'col' => 4, 'sensitive' => true, 'min' => 0, 'help' => 'shows.prize_help'],
            'is_public'  => ['type' => 'checkbox', 'label' => 'shows.is_public', 'default' => 1, 'col' => 4],
            'notes'      => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`show_results` t JOIN horses h ON h.id = t.horse_id JOIN shows s ON s.id = t.show_id';
    }

    public function columns(): array
    {
        return [
            'date'   => ['label' => 'common.date', 'sql' => 's.start_date', 'fmt' => 'date', 'sort' => true],
            'show'   => ['label' => 'shows.show', 'sql' => 's.name_en', 'sort' => true],
            'horse'  => $this->horseColumn(),
            'class'  => ['label' => 'shows.class', 'sql' => 't.class_name'],
            'result' => ['label' => 'shows.result', 'sql' => "COALESCE(NULLIF(t.title_en,''), CONCAT('#', t.placing))"],
            'medal'  => ['label' => 'shows.medal', 'sql' => 't.medal', 'fmt' => 'enum', 'prefix' => 'shows.medal'],
        ];
    }

    public function filters(): array
    {
        return [
            'horse_id' => $this->horseFilter(),
            'show_id'  => ['type' => 'picker', 'source' => 'shows', 'label' => 'shows.show', 'sql' => 't.show_id'],
            'medal'    => ['type' => 'select', 'label' => 'shows.medal', 'sql' => 't.medal', 'options' => self::MEDALS],
        ];
    }

    public function label(array $row): string
    {
        return (string) ($row['title_en'] ?: $row['class_name'] ?: __('shows.result'));
    }

    /** Prize money is booked as pending income on the horse (Accountant approves it in Finance). */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        $prize = (float) ($data['prize_qar'] ?? 0);
        if ($prize > 0 && (!$old || (float) $old['prize_qar'] <= 0)) {
            $show = DB::row('SELECT name_en, start_date FROM shows WHERE id = ?', [$data['show_id']]);
            DB::insert('bills', [
                'number' => Sequence::bill($show['start_date']), 'type' => 'income',
                'category_id' => DB::value("SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = 'Prize Money'"),
                'description' => 'Prize money — ' . $show['name_en'], 'currency' => 'QAR', 'exchange_rate' => 1,
                'amount_original' => $prize, 'amount_qar' => round($prize, 2), 'bill_date' => $show['start_date'], 'status' => 'pending',
                'horse_id' => $data['horse_id'], 'created_by' => Auth::id(),
            ]);
        }
        Cache::bump();
    }
}
