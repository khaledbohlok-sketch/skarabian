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

    private static function lookupOptions(string $type, string $column): array
    {
        $o = [];
        // stored as the English value (as before); shown in the user's language. A value switched off but still on an
        // embryo stays in the list, so that embryo's form keeps it (and saving does not clear it).
        DB::assertIdent($column);
        foreach (DB::all("SELECT value_en, value_ar FROM lookups l WHERE type = ? AND (active = 1
                OR EXISTS (SELECT 1 FROM embryos e WHERE e.$column = l.value_en AND e.deleted_at IS NULL)) ORDER BY sort", [$type]) as $r) {
            $o[$r['value_en']] = loc($r, 'value');
        }
        return $o;
    }

    public function fields(): array
    {
        return [
            'code'           => ['type' => 'text', 'label' => 'embryos.code_label', 'readonly' => true, 'edit_only' => true, 'col' => 4],
            'name'           => ['type' => 'text', 'label' => 'embryos.name', 'max' => 120, 'col' => 8],
            'flush_date'     => ['type' => 'date', 'label' => 'embryos.flush_date', 'col' => 4, 'default' => fn () => date('Y-m-d')],
            'grade'          => ['type' => 'select', 'label' => 'embryos.grade', 'options' => fn () => self::lookupOptions('embryo_grade', 'grade'), 'col' => 4],
            'stage'          => ['type' => 'select', 'label' => 'embryos.stage', 'options' => fn () => self::lookupOptions('embryo_stage', 'stage'), 'col' => 4],
            'status'         => ['type' => 'segment', 'label' => 'common.status', 'options' => self::STATUSES, 'required' => true, 'default' => 'fresh', 'col' => 12],
            'location_id'    => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'embryos.location', 'col' => 8, 'help' => 'embryos.location_help'],

            'donor_mare_id'  => ['type' => 'picker', 'source' => 'mares', 'label' => 'embryos.donor', 'required' => true, 'section' => 'parents'],
            'sire_id'        => ['type' => 'picker', 'source' => 'stallions', 'label' => 'horse.sire', 'required' => true, 'section' => 'parents'],
            'owner_type'     => ['type' => 'segment', 'label' => 'embryos.owner', 'options' => ['sk' => 'horses.owner_sk', 'client' => 'horses.owner_client'], 'default' => 'sk', 'required' => true, 'section' => 'parents', 'col' => 4],
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
            'code'      => ['label' => 'embryos.code_label', 'sql' => "CONCAT(t.code, IF(t.name IS NULL OR t.name = '', '', CONCAT(' — ', t.name)))", 'sort' => true], // with its name, e.g. the stud's ET03
            // the mares and the sire link to their horse pages, as on the embryo's own page
            'donor'     => ['label' => 'embryos.donor', 'sql' => self::nameSql('d'), 'sort' => true, 'to' => ['horses', '_donor_id']],
            'sire'      => ['label' => 'horse.sire', 'sql' => self::nameSql('s'), 'sort' => true, 'to' => ['horses', '_sire_id']],
            'flush'     => ['label' => 'embryos.flush_date', 'sql' => 't.flush_date', 'fmt' => 'date', 'sort' => true],
            'status'    => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'embryos.status'],
            'location'  => ['label' => 'embryos.location', 'sql' => \App\Core\Lang::current() === 'ar' ? "COALESCE(NULLIF(l.value_ar, ''), l.value_en)" : 'l.value_en'],
            'recipient' => ['label' => 'embryos.recipient', 'sql' => self::nameSql('r'), 'to' => ['horses', '_recipient_id']],
            'expected'  => ['label' => 'embryos.expected_foaling', 'sql' => 't.expected_foaling_date', 'fmt' => 'date', 'sort' => true],
        ];
    }

    /** The horses' ids, for the links in the donor / sire / recipient cells. */
    public function listExtraSelect(): array
    {
        return ['_donor_id' => 't.donor_mare_id', '_sire_id' => 't.sire_id', '_recipient_id' => 't.recipient_mare_id'];
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
        if (!empty($data['transfer_date']) && in_array($data['status'], ['transferred', 'pregnant'], true)) {
            // Filled from the transfer date when empty, and recalculated when the transfer date changes — unless the
            // expected date was set by hand (it then differs from the one calculated from the old transfer date)
            $oldAuto = $old && !empty($old['transfer_date']) ? HorseRules::expectedFoaling($old['transfer_date']) : null;
            $dateChanged = $old && ($old['transfer_date'] ?? null) !== $data['transfer_date'];
            if (empty($data['expected_foaling_date']) || ($dateChanged && $data['expected_foaling_date'] === $oldAuto)) {
                $data['expected_foaling_date'] = HorseRules::expectedFoaling($data['transfer_date']);
            }
        }
        if (!$old) {
            // The real code is taken in afterSave(), inside the save transaction: a save that fails burns no number
            $data['code'] = Sequence::placeholder();
        }
        return $data;
    }

    /**
     * Embryo transferred → the recipient mare's breeding record (kept in sync with the embryo). The record is marked
     * pregnant only when the transfer is first recorded or the embryo is changed to "pregnant" — never on every save,
     * and never over a vet's negative pregnancy check.
     */
    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created && str_starts_with((string) ($data['code'] ?? ''), Sequence::PLACEHOLDER_PREFIX)) {
            $data['code'] = Sequence::embryo($data['flush_date'] ?? null);
            DB::update('embryos', ['code' => $data['code']], 'id = :id', ['id' => $id]);
        }
        if (!in_array($data['status'], ['transferred', 'pregnant'], true) || empty($data['recipient_mare_id'])) {
            if ($data['status'] === 'failed') {
                DB::run("UPDATE breeding_records SET status = 'lost', updated_at = NOW() WHERE embryo_id = ? AND status IN ('open','pregnant')", [$id]);
            }
            return;
        }
        $br = DB::row("SELECT * FROM breeding_records WHERE embryo_id = ? AND deleted_at IS NULL AND status IN ('open','pregnant','not_pregnant') ORDER BY id DESC LIMIT 1", [$id]);
        $fields = [
            'mare_id' => $data['recipient_mare_id'], 'stallion_id' => $data['sire_id'], 'embryo_id' => $id, 'method' => 'embryo_transfer',
            'start_date' => $data['transfer_date'], 'expected_foaling_date' => $data['expected_foaling_date'],
        ];
        if ($br) {
            $becamePregnant = $data['status'] === 'pregnant' && ($old['status'] ?? null) !== 'pregnant';
            $lastCheck = DB::value("SELECT result FROM pregnancy_checks WHERE breeding_record_id = ? AND deleted_at IS NULL AND result IN ('positive','negative')
                ORDER BY check_date DESC, id DESC LIMIT 1", [$br['id']]);
            if ($becamePregnant && $br['status'] !== 'pregnant' && $lastCheck !== 'negative') {
                $fields['status'] = 'pregnant';
            }
            DB::update('breeding_records', $fields + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
        } else {
            DB::insert('breeding_records', $fields + ['status' => 'pregnant', 'created_by' => Auth::id()]);
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
