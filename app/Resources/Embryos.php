<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\HorseRules;
use App\Services\Sequence;

/**
 * Embryos. Donor mare and sire are chosen from the horse list (no free typing — the old system had spelling
 * duplicates such as AJ RAZNEH / AJ RAZENAH), "Expected Foaling" replaces the old "Date of Birth" column, and the
 * storage location is a real field (it used to be written in Notes).
 */
class Embryos extends Resource
{
    public string $key = 'embryos';
    public string $table = 'embryos';
    public string $module = 'embryos';
    public string $recordType = 'embryo';
    public string $title = 'nav.embryos';
    public string $singular = 'embryos.singular';
    public array $search = ['t.code', 't.name', 'd.name_en', 's.name_en', 'r.name_en'];
    public string $sort = 't.id DESC';
    public bool $archivable = true;

    public const STATUSES = ['fresh' => 'embryos.status_fresh', 'frozen' => 'embryos.status_frozen', 'transferred' => 'embryos.status_transferred', 'pregnant' => 'embryos.status_pregnant', 'foaled' => 'embryos.status_foaled', 'failed' => 'embryos.status_failed'];

    public function sections(): array
    {
        return ['main' => 'embryos.sec_embryo', 'parents' => 'embryos.sec_parents', 'transfer' => 'embryos.sec_transfer'];
    }

    private static function lookupOptions(string $type): array
    {
        $o = [];
        foreach (DB::all('SELECT value_en FROM lookups WHERE type = ? AND active = 1 ORDER BY sort', [$type]) as $r) {
            $o[$r['value_en']] = $r['value_en'];
        }
        return $o;
    }

    public function fields(): array
    {
        return [
            'code'           => ['type' => 'text', 'label' => 'embryos.code_label', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'name'           => ['type' => 'text', 'label' => 'embryos.name', 'max' => 120, 'col' => 8],
            'flush_date'     => ['type' => 'date', 'label' => 'embryos.flush_date', 'col' => 4, 'default' => fn () => date('Y-m-d')],
            'grade'          => ['type' => 'select', 'label' => 'embryos.grade', 'options' => fn () => self::lookupOptions('embryo_grade'), 'col' => 4],
            'stage'          => ['type' => 'select', 'label' => 'embryos.stage', 'options' => fn () => self::lookupOptions('embryo_stage'), 'col' => 4],
            'status'         => ['type' => 'select', 'label' => 'common.status', 'options' => self::STATUSES, 'required' => true, 'default' => 'fresh', 'col' => 4],
            'location_id'    => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'embryos.location', 'col' => 8, 'help' => 'embryos.location_help'],

            'donor_mare_id'  => ['type' => 'picker', 'source' => 'mares', 'label' => 'embryos.donor', 'required' => true, 'section' => 'parents'],
            'sire_id'        => ['type' => 'picker', 'source' => 'stallions', 'label' => 'horse.sire', 'required' => true, 'section' => 'parents'],
            'owner_type'     => ['type' => 'select', 'label' => 'embryos.owner', 'options' => ['sk' => 'horses.owner_sk', 'client' => 'horses.owner_client'], 'default' => 'sk', 'required' => true, 'section' => 'parents', 'col' => 4],
            'owner_party_id' => ['type' => 'picker', 'source' => 'clients', 'label' => 'horses.owner_party', 'section' => 'parents', 'col' => 8],

            'recipient_mare_id'     => ['type' => 'picker', 'source' => 'mares', 'label' => 'embryos.recipient', 'section' => 'transfer'],
            'transfer_date'         => ['type' => 'date', 'label' => 'embryos.transfer_date', 'section' => 'transfer', 'col' => 6],
            'expected_foaling_date' => ['type' => 'date', 'label' => 'embryos.expected_foaling', 'section' => 'transfer', 'col' => 6, 'help' => 'embryos.expected_help'],
            'notes'                 => ['type' => 'textarea', 'label' => 'common.notes', 'section' => 'transfer', 'rows' => 3],
        ];
    }

    public function from(): string
    {
        return '`embryos` t JOIN horses d ON d.id = t.donor_mare_id JOIN horses s ON s.id = t.sire_id LEFT JOIN horses r ON r.id = t.recipient_mare_id LEFT JOIN lookups l ON l.id = t.location_id';
    }

    public function columns(): array
    {
        return [
            'code'      => ['label' => 'embryos.code_label', 'sql' => 't.code', 'sort' => true],
            'donor'     => ['label' => 'embryos.donor', 'sql' => 'd.name_en', 'sort' => true],
            'sire'      => ['label' => 'horse.sire', 'sql' => 's.name_en', 'sort' => true],
            'flush'     => ['label' => 'embryos.flush_date', 'sql' => 't.flush_date', 'fmt' => 'date', 'sort' => true],
            'status'    => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'embryos.status'],
            'location'  => ['label' => 'embryos.location', 'sql' => 'l.value_en'],
            'recipient' => ['label' => 'embryos.recipient', 'sql' => 'r.name_en'],
            'expected'  => ['label' => 'embryos.expected_foaling', 'sql' => 't.expected_foaling_date', 'fmt' => 'date', 'sort' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'status'   => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'donor'    => ['type' => 'picker', 'source' => 'mares', 'label' => 'embryos.donor', 'sql' => 't.donor_mare_id'],
            'sire'     => ['type' => 'picker', 'source' => 'stallions', 'label' => 'horse.sire', 'sql' => 't.sire_id'],
            'location' => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'embryos.location', 'sql' => 't.location_id'],
            'archived' => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function label(array $row): string
    {
        return $row['code'] . (!empty($row['name']) ? ' — ' . $row['name'] : '');
    }

    public function links(): array
    {
        return [['bills', 'embryo_id', 'nav.bills'], ['breeding_records', 'embryo_id', 'nav.breeding']];
    }

    public function prepare(array $data, ?array $old): array
    {
        $errors = [];
        if (!empty($data['recipient_mare_id']) && (int) $data['recipient_mare_id'] === (int) $data['donor_mare_id']) {
            $errors['recipient_mare_id'] = __('embryos.recipient_same');
        }
        if (in_array($data['status'], ['transferred', 'pregnant'], true)) {
            if (empty($data['recipient_mare_id'])) {
                $errors['recipient_mare_id'] = __('embryos.recipient_required');
            }
            if (empty($data['transfer_date'])) {
                $errors['transfer_date'] = __('validation.required');
            }
        }
        if (in_array($data['status'], ['fresh', 'frozen'], true) && empty($data['location_id']) && $data['status'] === 'frozen') {
            $errors['location_id'] = __('embryos.location_required');
        }
        if (!empty($data['transfer_date']) && !empty($data['flush_date']) && $data['transfer_date'] < $data['flush_date']) {
            $errors['transfer_date'] = __('embryos.transfer_before_flush');
        }
        if (($data['owner_type'] ?? 'sk') === 'client' && empty($data['owner_party_id'])) {
            $errors['owner_party_id'] = __('validation.required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if (($data['owner_type'] ?? 'sk') === 'sk') {
            $data['owner_party_id'] = null;
        }
        if (empty($data['expected_foaling_date']) && !empty($data['transfer_date']) && in_array($data['status'], ['transferred', 'pregnant'], true)) {
            $data['expected_foaling_date'] = HorseRules::expectedFoaling($data['transfer_date']);
        }
        if (!$old) {
            $data['code'] = Sequence::embryo($data['flush_date'] ?? null);
        }
        return $data;
    }

    /** Embryo transferred → recipient mare marked pregnant with the expected foaling date (breeding record kept in sync). */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if (!in_array($data['status'], ['transferred', 'pregnant'], true) || empty($data['recipient_mare_id'])) {
            if ($data['status'] === 'failed') {
                DB::run("UPDATE breeding_records SET status = 'lost', updated_at = NOW() WHERE embryo_id = ? AND status IN ('open','pregnant')", [$id]);
            }
            return;
        }
        $br = DB::row("SELECT * FROM breeding_records WHERE embryo_id = ? AND deleted_at IS NULL AND status IN ('open','pregnant','not_pregnant') ORDER BY id DESC LIMIT 1", [$id]);
        $fields = [
            'mare_id' => $data['recipient_mare_id'], 'stallion_id' => $data['sire_id'], 'embryo_id' => $id, 'method' => 'embryo_transfer',
            'start_date' => $data['transfer_date'], 'expected_foaling_date' => $data['expected_foaling_date'], 'status' => 'pregnant',
        ];
        if ($br) {
            DB::update('breeding_records', $fields + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
        } else {
            DB::insert('breeding_records', $fields + ['created_by' => Auth::id()]);
        }
    }

    public function actions(array $row): array
    {
        $a = [];
        if (in_array($row['status'], ['transferred', 'pregnant'], true) && Auth::can('embryos', 'edit')) {
            $a[] = ['label' => 'horses.record_foaling', 'url' => '/portal/embryos/' . $row['id'] . '/foaling', 'class' => 'btn-primary'];
        }
        if ($row['foal_id']) {
            $a[] = ['label' => 'embryos.open_foal', 'url' => '/portal/horses/' . $row['foal_id']];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        $t = [];
        if (Auth::can('horse_breeding')) {
            $t['breeding-records'] = ['label' => 'embryos.pregnancy', 'related' => 'breeding-records', 'filter' => ['embryo_id' => (int) $row['id']]];
        }
        if (Auth::can('finance')) {
            $t['bills'] = ['label' => 'embryos.costs', 'related' => 'bills', 'filter' => ['embryo_id' => (int) $row['id']]];
        }
        return $t + $this->standardTabs($row, ['studio' => ['embryo'], 'categories' => ['photo', 'document']]);
    }
}
