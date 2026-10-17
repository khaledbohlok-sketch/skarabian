<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Loads "SK Arabians Horse Information Cards — 7 October 2026" (database/data/horse_cards_2026-10-07.json, built from the
 * cards' PDF): the 32 SK horses with their identity and registration details, five-generation pedigrees (every ancestor
 * as a pedigree-only horse), show results with judges' cards, the cards' text as notes, the offspring they name and the
 * 18 expected foals (12 embryos carried by recipient mares, 6 pregnancies carried by the mare).
 *
 * Safe to run more than once: horses are matched by name whatever the punctuation ("D A'BAQ" = "D ABAQ"), shows by name
 * and year, results/notes/embryos/pregnancies by their details. Only empty fields are filled in; a sire, dam, sex or
 * number already on file is never replaced — a different one is listed as a conflict instead. Nothing is published on
 * the website (horses stay off it; results only when the Owner ticks the box).
 */
final class HorseCardsImport
{
    public const FILE = 'database/data/horse_cards_2026-10-07.json';
    public const SETTING = 'import.horse_cards_2026_10_07';

    private array $data;
    private bool $publish;
    private ?int $by;
    private array $report;
    /** horse key => id */
    private array $ids = [];
    /** id => ['sire_id' => .., 'dam_id' => ..] for the loop check */
    private array $links = [];

    private function __construct(array $data, bool $publish)
    {
        $this->data = $data;
        $this->publish = $publish;
        $this->by = Auth::id();
        $this->report = [
            'sk' => [], 'horses' => ['new' => 0, 'existing' => 0], 'others' => ['new' => 0, 'existing' => 0], 'links' => ['new' => 0, 'existing' => 0],
            'shows' => ['new' => 0, 'existing' => 0], 'results' => ['new' => 0, 'existing' => 0], 'cards' => ['new' => 0, 'existing' => 0],
            'notes' => ['new' => 0, 'existing' => 0], 'embryos' => ['new' => 0, 'existing' => 0], 'carried' => ['new' => 0, 'existing' => 0],
            'conflicts' => [],
        ];
    }

    public static function path(): string
    {
        return APP_ROOT . '/' . self::FILE;
    }

    /** The database has update 018 (UELN, year-only shows, judges' cards, "not recorded" method). */
    public static function ready(): bool
    {
        return DB::hasColumn('horses', 'ueln') && DB::hasColumn('shows', 'year_only') && DB::hasColumn('show_results', 'judge_card')
            && str_contains((string) (DB::columns('breeding_records')['method']['Type'] ?? ''), "'unknown'");
    }

    public static function hasFile(): bool
    {
        return is_file(self::path());
    }

    /** When it was imported: ['at' => .., 'by' => .., 'report' => ..] or null. */
    public static function done(): ?array
    {
        $v = Settings::get(self::SETTING);
        $d = $v ? json_decode((string) $v, true) : null;
        return is_array($d) ? $d : null;
    }

    private static function load(): array
    {
        $d = json_decode((string) file_get_contents(self::path()), true);
        if (!is_array($d) || !isset($d['horses'], $d['parents'], $d['shows'], $d['results'], $d['notes'], $d['carried'], $d['transfers'])) {
            throw new \RuntimeException('The horse cards data file is not readable.');
        }
        return $d;
    }

    /** What an import would do, changing nothing: the same run inside a transaction that is rolled back. */
    public static function preview(): array
    {
        $pdo = DB::pdo();
        if ($pdo->inTransaction()) { // already inside a transaction (a test): undo to a savepoint instead
            $pdo->exec('SAVEPOINT horse_cards_preview');
            try {
                return (new self(self::load(), false))->run();
            } finally {
                $pdo->exec('ROLLBACK TO SAVEPOINT horse_cards_preview');
            }
        }
        $report = [];
        try {
            DB::transaction(function () use (&$report) {
                $report = (new self(self::load(), false))->run();
                throw new HorseCardsPreviewDone();
            });
        } catch (HorseCardsPreviewDone) {
        }
        return $report;
    }

    /** Imports for real (one transaction: all or nothing) and records when. */
    public static function import(bool $publishResults): array
    {
        @set_time_limit(300);
        return DB::transaction(function () use ($publishResults) {
            $report = (new self(self::load(), $publishResults))->run();
            $user = Auth::user();
            Settings::set(self::SETTING, json_encode(['at' => date('Y-m-d H:i:s'), 'by' => $user['name'] ?? null, 'report' => self::summary($report)], JSON_UNESCAPED_UNICODE));
            Audit::log('import_cards', 'horses', null, null, null, self::summary($report), 'Horse cards imported (7 October 2026): ' . $report['horses']['new'] . ' SK horses new, '
                . $report['others']['new'] . ' pedigree-only horses, ' . $report['results']['new'] . ' show results, ' . $report['notes']['new'] . ' notes');
            Cache::bump();
            return $report;
        });
    }

    /** The counts without the per-horse list (kept in Settings and the activity log). */
    public static function summary(array $r): array
    {
        return array_diff_key($r, ['sk' => 1]);
    }

    /** Anything at all to add? */
    public static function hasWork(array $r): bool
    {
        foreach (['horses', 'others', 'links', 'shows', 'results', 'cards', 'notes', 'embryos', 'carried'] as $k) {
            if (($r[$k]['new'] ?? 0) > 0) {
                return true;
            }
        }
        return !empty(array_filter($r['sk'] ?? [], fn ($h) => !empty($h['filled'])));
    }

    // ------------------------------------------------------------------ the run

    private function run(): array
    {
        $this->matchHorses();
        $this->horses();
        $this->parents();
        $this->showsAndResults();
        $this->notes();
        $this->pregnancies();
        return $this->report;
    }

    /** Same horse whatever the punctuation: upper case, no dots/apostrophes, single spaces, no trailing "(AE)" country tag. */
    public static function key(string $name): string
    {
        $s = mb_strtoupper(trim($name));
        $s = str_replace(['’', '‘', '`'], "'", $s);
        $s = preg_replace('/\s*\((?:[A-Z]{2})\)$/', '', $s);
        $s = str_replace(['.', "'"], '', $s);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    private function conflict(string $langKey, array $params): void
    {
        $this->report['conflicts'][] = __($langKey, $params);
    }

    /** Horses already in the system, by name key (and the spellings the cards confirm as the same horse). */
    private function matchHorses(): void
    {
        $aliases = $this->data['aliases'] ?? [];
        $found = [];
        $spelled = [];
        foreach (DB::all('SELECT id, name_en, is_external FROM horses WHERE deleted_at IS NULL ORDER BY is_external, id') as $h) {
            $own = self::key((string) $h['name_en']);
            $k = $aliases[$own] ?? $own;
            if (isset($this->data['horses'][$k])) {
                $found[$k][] = (int) $h['id'];
                if ($own !== $k) {
                    $spelled[(int) $h['id']] = [(string) $h['name_en'], $k];
                }
            }
        }
        foreach ($found as $k => $ids) {
            $this->ids[$k] = $ids[0];
            if (count($ids) > 1) {
                $this->conflict('cards.c_duplicate', ['name' => $this->data['horses'][$k]['name'], 'ids' => '#' . implode(', #', $ids)]);
            }
            if (isset($spelled[$ids[0]])) { // typed in with a spelling the cards confirm as this horse (AJ RAZNAH = AJ RAZNEH)
                $this->conflict('cards.c_alias', ['have' => $spelled[$ids[0]][0], 'card' => $this->data['horses'][$k]['name']]);
            }
        }
    }

    private function lookupId(string $type, ?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = DB::value('SELECT id FROM lookups WHERE type = ? AND value_en = ?', [$type, $value]);
        if (!$id) {
            $id = DB::insert('lookups', ['type' => $type, 'value_en' => $value, 'sort' => (int) DB::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM lookups WHERE type = ?', [$type]), 'active' => 1]);
        }
        return (int) $id;
    }

    private function country(?string $cc): ?string
    {
        static $known = [];
        if ($cc === null || $cc === '') {
            return null;
        }
        return ($known[$cc] ??= (bool) DB::value('SELECT 1 FROM countries WHERE id = ?', [$cc])) ? $cc : null;
    }

    /** Creates the horses that are not in the system yet and fills the empty fields of those that are. */
    private function horses(): void
    {
        $breed = $this->lookupId('breed', 'Purebred Arabian');
        foreach ($this->data['horses'] as $k => $h) {
            $sk = !empty($h['sk']);
            $values = [
                'dob' => $h['dob'] ?? null, 'color_id' => $this->lookupId('color', $h['color'] ?? null), 'breeder' => $h['breeder'] ?? null,
                'origin_country' => $this->country($h['country'] ?? null), 'registration_no' => $h['registration_no'] ?? null, 'microchip' => $h['microchip'] ?? null,
                'ueln' => $h['ueln'] ?? null, 'passport_issue_date' => $h['passport_issue_date'] ?? null, 'passport_issue_place' => $h['passport_issue_place'] ?? null,
                'location_id' => $this->lookupId('location', $h['location'] ?? null), 'notes' => $h['notes'] ?? null,
            ];
            if ($sk) {
                $values['breed_id'] = $breed;
            }
            $values = array_filter($values, fn ($v) => $v !== null && $v !== '');
            if (!isset($this->ids[$k])) {
                $this->create($k, $h, $values);
                continue;
            }
            $this->complete($k, $h, $values);
        }
    }

    /** Card category, locked when the age rule would say otherwise (e.g. a stallion under 4, a filly with no birth date). */
    private static function category(array $h): array
    {
        $rule = HorseRules::category($h['sex'], $h['dob'] ?? null);
        $cat = $h['category'] ?? $rule;
        return [$cat, $cat !== $rule || !empty($h['lock']) ? 1 : 0];
    }

    private function create(string $k, array $h, array $values): void
    {
        $sk = !empty($h['sk']);
        [$cat, $lock] = self::category($h);
        foreach (['registration_no', 'microchip', 'ueln'] as $u) {
            if (isset($values[$u]) && ($other = DB::value("SELECT name_en FROM horses WHERE $u = ? AND deleted_at IS NULL", [$values[$u]]))) {
                $this->conflict('cards.c_unique', ['horse' => $h['name'], 'field' => __('horses.' . $u), 'value' => $values[$u], 'other' => $other]);
                unset($values[$u]);
            }
        }
        $id = DB::insert('horses', $values + [
            'name_en' => $h['name'], 'slug' => HorseService::uniqueSlug($h['name']), 'sex' => $h['sex'], 'category' => $cat, 'category_locked' => $lock,
            'owner_type' => 'sk', 'status' => 'active', 'is_external' => $sk ? 0 : 1, 'show_on_website' => 0,
            'breeding_stallion' => $sk && !empty($h['breeding_stallion']) && $h['sex'] === 'male' ? 1 : 0, 'born_at_sk' => $sk && !empty($h['born_at_sk']) ? 1 : 0,
            'created_by' => $this->by,
        ]);
        $this->ids[$k] = $id;
        $this->links[$id] = ['sire_id' => null, 'dam_id' => null];
        if ($sk) {
            $this->report['horses']['new']++;
            $this->report['sk'][$k] = ['name' => $h['name'], 'id' => $id, 'status' => 'new', 'filled' => []];
            Audit::log('create', 'horses', 'horse', $id, null, ['name_en' => $h['name'], 'source' => 'horse cards 7 Oct 2026'], 'Horse added from the horse cards: ' . $h['name']);
        } else {
            $this->report['others']['new']++;
        }
    }

    private function complete(string $k, array $h, array $values): void
    {
        $id = $this->ids[$k];
        $sk = !empty($h['sk']);
        $row = DB::row('SELECT * FROM horses WHERE id = ?', [$id]);
        if ($row['sex'] !== $h['sex']) {
            $this->conflict('cards.c_sex', ['horse' => $row['name_en'], 'have' => __('horse.sex_' . $row['sex']), 'card' => __('horse.sex_' . $h['sex'])]);
        }
        $set = [];
        foreach ($values as $col => $v) {
            if ($col === 'notes') {
                $have = trim((string) $row['notes']);
                $first = strtok((string) $v, "\n");
                if ($have === '') {
                    $set['notes'] = $v;
                } elseif (!str_contains($have, (string) $first)) {
                    $set['notes'] = $have . "\n\n" . $v;
                }
                continue;
            }
            if ($row[$col] === null || $row[$col] === '') {
                if (in_array($col, ['registration_no', 'microchip', 'ueln'], true) && ($other = DB::value("SELECT name_en FROM horses WHERE $col = ? AND id <> ? AND deleted_at IS NULL", [$v, $id]))) {
                    $this->conflict('cards.c_unique', ['horse' => $row['name_en'], 'field' => __('horses.' . $col), 'value' => $v, 'other' => $other]);
                    continue;
                }
                $set[$col] = $v;
            } elseif ((string) $row[$col] !== (string) $v && in_array($col, ['dob', 'registration_no', 'microchip', 'ueln'], true)) {
                $this->conflict('cards.c_field', ['horse' => $row['name_en'], 'field' => __('horses.' . ($col === 'dob' ? 'dob' : $col)), 'have' => (string) $row[$col], 'card' => (string) $v]);
            }
        }
        if ($sk) {
            if ((int) $row['is_external'] === 1) { // known so far only as an ancestor: it is one of SK's horses
                $set += ['is_external' => 0, 'owner_type' => 'sk', 'owner_party_id' => null];
            }
            if (!empty($h['breeding_stallion']) && !(int) $row['breeding_stallion'] && $row['sex'] === 'male') {
                $set['breeding_stallion'] = 1;
            }
            if (!empty($h['born_at_sk']) && !(int) $row['born_at_sk']) {
                $set['born_at_sk'] = 1;
            }
            if (!(int) $row['category_locked'] && $row['sex'] === $h['sex']) {
                [$cat, $lock] = self::category(array_merge($h, ['dob' => $set['dob'] ?? $row['dob']]));
                if ($cat !== $row['category'] || $lock) {
                    $set += ['category' => $cat, 'category_locked' => $lock];
                }
            }
        }
        if ($set) {
            DB::update('horses', $set + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            if ($sk) {
                Audit::log('update', 'horses', 'horse', $id, array_intersect_key($row, $set), $set, 'Details added from the horse cards: ' . $row['name_en']);
            }
        }
        $this->report[$sk ? 'horses' : 'others']['existing']++;
        if ($sk) {
            $this->report['sk'][$k] = ['name' => $row['name_en'], 'id' => $id, 'status' => $set ? 'filled' : 'same', 'filled' => array_keys($set)];
        }
    }

    /** Sire and dam of every horse on the cards; never replaces a different parent already on file. */
    private function parents(): void
    {
        foreach (DB::all('SELECT id, sire_id, dam_id FROM horses WHERE deleted_at IS NULL') as $r) {
            $this->links[(int) $r['id']] = ['sire_id' => $r['sire_id'] ? (int) $r['sire_id'] : null, 'dam_id' => $r['dam_id'] ? (int) $r['dam_id'] : null];
        }
        $names = fn (?int $id) => $id ? (string) DB::value('SELECT name_en FROM horses WHERE id = ?', [$id]) : '';
        foreach ($this->data['parents'] as $child => [$sire, $dam]) {
            $childId = $this->ids[$child] ?? null;
            if (!$childId) {
                continue;
            }
            $set = [];
            foreach (['sire_id' => $sire, 'dam_id' => $dam] as $col => $pk) {
                $pid = $pk !== null ? ($this->ids[$pk] ?? null) : null;
                if (!$pid) {
                    continue;
                }
                $have = $this->links[$childId][$col] ?? null;
                if ($have === $pid) {
                    $this->report['links']['existing']++;
                    continue;
                }
                if ($have !== null) {
                    $this->conflict('cards.c_parent', ['horse' => $this->data['horses'][$child]['name'], 'role' => __($col === 'sire_id' ? 'horse.sire' : 'horse.dam'), 'have' => $names($have), 'card' => $this->data['horses'][$pk]['name']]);
                    continue;
                }
                if ($this->isAncestor($childId, $pid)) {
                    $this->conflict('cards.c_loop', ['horse' => $this->data['horses'][$child]['name'], 'parent' => $this->data['horses'][$pk]['name']]);
                    continue;
                }
                $set[$col] = $pid;
                $this->links[$childId][$col] = $pid;
                $this->report['links']['new']++;
            }
            if ($set) {
                DB::update('horses', $set + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $childId]);
            }
        }
    }

    /** True when $horseId is $candidate itself or one of its ancestors (linking would close a loop). */
    private function isAncestor(int $horseId, int $candidate): bool
    {
        $todo = [$candidate];
        $seen = [];
        while ($todo) {
            $id = array_pop($todo);
            if ($id === $horseId) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($this->links[$id] ?? [] as $p) {
                if ($p) {
                    $todo[] = $p;
                }
            }
        }
        return false;
    }

    private function showsAndResults(): void
    {
        $showIds = [];
        foreach ($this->data['shows'] as $sk => $s) {
            $row = DB::row('SELECT * FROM shows WHERE LOWER(name_en) = LOWER(?) AND YEAR(start_date) = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$s['name'], (int) $s['year']]);
            if ($row) {
                $showIds[$sk] = (int) $row['id'];
                $fill = array_filter(['city' => $row['city'] ? null : $s['city'], 'country' => $row['country'] ? null : $this->country($s['country']), 'end_date' => $row['end_date'] ? null : $s['end']]);
                if ($fill) {
                    DB::update('shows', $fill + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
                }
                $this->report['shows']['existing']++;
                continue;
            }
            $showIds[$sk] = DB::insert('shows', ['name_en' => $s['name'], 'start_date' => $s['start'], 'end_date' => $s['end'], 'city' => $s['city'], 'country' => $this->country($s['country']),
                'year_only' => !empty($s['year_only']) ? 1 : 0, 'created_by' => $this->by]);
            $this->report['shows']['new']++;
        }
        foreach ($this->data['results'] as $r) {
            $horseId = $this->ids[$r['horse']] ?? null;
            $showId = $showIds[$r['show']] ?? null;
            if (!$horseId || !$showId) {
                continue;
            }
            $card = $r['judge_card'] ? json_encode($r['judge_card'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $row = DB::row('SELECT * FROM show_results WHERE horse_id = ? AND show_id = ? AND class_name <=> ? AND placing <=> ? AND deleted_at IS NULL ORDER BY id LIMIT 1',
                [$horseId, $showId, $r['class'], $r['placing']]);
            if ($row) {
                $fill = array_filter([
                    'judge_card' => $row['judge_card'] ? null : $card, 'score' => $row['score'] !== null ? null : $r['score'],
                    'title_en' => ($row['title_en'] ?? '') !== '' ? null : $r['title'], 'medal' => $row['medal'] !== 'none' || $r['medal'] === 'none' ? null : $r['medal'],
                    'notes' => ($row['notes'] ?? '') !== '' ? null : $r['notes'],
                ], fn ($v) => $v !== null);
                if ($fill) {
                    DB::update('show_results', $fill + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
                }
                $this->report['results']['existing']++;
                if ($card) {
                    $this->report['cards'][$row['judge_card'] ? 'existing' : 'new']++;
                }
                continue;
            }
            DB::insert('show_results', [
                'show_id' => $showId, 'horse_id' => $horseId, 'class_name' => $r['class'], 'placing' => $r['placing'], 'title_en' => $r['title'], 'medal' => $r['medal'],
                'score' => $r['score'], 'is_public' => $this->publish ? 1 : 0, 'notes' => $r['notes'] !== null ? mb_substr($r['notes'], 0, 255) : null, 'judge_card' => $card,
                'created_by' => $this->by,
            ]);
            $this->report['results']['new']++;
            if ($card) {
                $this->report['cards']['new']++;
            }
        }
    }

    /** The cards' text, one note per section; a note whose first line is already there is not added again. */
    private function notes(): void
    {
        foreach ($this->data['notes'] as $k => $list) {
            $horseId = $this->ids[$k] ?? null;
            if (!$horseId) {
                continue;
            }
            foreach (array_reverse($list) as $text) { // the Notes tab lists the newest first: the card's first section on top
                $first = (string) strtok((string) $text, "\n");
                if (DB::value("SELECT 1 FROM horse_notes WHERE horse_id = ? AND deleted_at IS NULL AND (note = ? OR note LIKE ?)", [$horseId, $text, addcslashes($first, '%_\\') . '%'])) {
                    $this->report['notes']['existing']++;
                    continue;
                }
                DB::insert('horse_notes', ['horse_id' => $horseId, 'note' => $text, 'created_by' => $this->by]);
                $this->report['notes']['new']++;
            }
        }
    }

    /** Expected foals: pregnancies carried by the mare, and embryos carried by recipient mares (with the recipient's pregnancy). */
    private function pregnancies(): void
    {
        foreach ($this->data['carried'] as $c) {
            $mare = $this->ids[$c['mare']] ?? null;
            $stallion = $this->ids[$c['stallion']] ?? null;
            if (!$mare) {
                continue;
            }
            if (DB::value('SELECT 1 FROM breeding_records WHERE mare_id = ? AND stallion_id <=> ? AND start_date = ? AND deleted_at IS NULL', [$mare, $stallion, $c['start']])) {
                $this->report['carried']['existing']++;
                continue;
            }
            DB::insert('breeding_records', ['mare_id' => $mare, 'stallion_id' => $stallion, 'method' => 'unknown', 'start_date' => $c['start'], 'expected_foaling_date' => $c['due'],
                'status' => 'pregnant', 'notes' => $c['notes'], 'created_by' => $this->by]);
            $this->report['carried']['new']++;
        }
        foreach ($this->data['transfers'] as $t) {
            $donor = $this->ids[$t['donor']] ?? null;
            $sire = $this->ids[$t['sire']] ?? null;
            $recipient = $this->ids[$t['recipient']] ?? null;
            if (!$donor || !$sire) {
                continue;
            }
            $embryo = DB::value('SELECT id FROM embryos WHERE donor_mare_id = ? AND sire_id = ? AND name = ? AND deleted_at IS NULL', [$donor, $sire, $t['ref']]);
            if ($embryo) {
                $this->report['embryos']['existing']++;
            } else {
                $embryo = DB::insert('embryos', [
                    'code' => Sequence::embryo($t['covered']), 'name' => $t['ref'], 'donor_mare_id' => $donor, 'sire_id' => $sire, 'owner_type' => 'sk', 'status' => 'pregnant',
                    'recipient_mare_id' => $recipient, 'expected_foaling_date' => $t['due'], 'notes' => $t['notes'], 'created_by' => $this->by,
                ]);
                $this->report['embryos']['new']++;
            }
            if ($recipient && !DB::value('SELECT 1 FROM breeding_records WHERE embryo_id = ? AND deleted_at IS NULL', [$embryo])) {
                DB::insert('breeding_records', ['mare_id' => $recipient, 'stallion_id' => $sire, 'embryo_id' => $embryo, 'method' => 'embryo_transfer', 'start_date' => $t['covered'],
                    'expected_foaling_date' => $t['due'], 'status' => 'pregnant', 'notes' => $t['br_notes'], 'created_by' => $this->by]);
            }
        }
    }
}

/** Thrown inside the preview's transaction so that everything it did is rolled back. */
final class HorseCardsPreviewDone extends \RuntimeException
{
}
