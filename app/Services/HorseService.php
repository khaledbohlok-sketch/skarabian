<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/** Horse workflows that touch several modules: foaling, website approval, finance summary, pedigree. */
final class HorseService
{
    /**
     * Foal born → new horse profile with sire and dam filled in, breeding record / embryo marked Foaled,
     * ownership history "born at SK", and (optionally) a request to show it in "Latest Foals" after Owner approval.
     * $source: ['breeding_record_id' => x] or ['embryo_id' => x] or ['mare_id' => x, 'stallion_id' => y]
     */
    public static function recordFoaling(array $source, array $foal): int
    {
        return DB::transaction(function () use ($source, $foal) {
            $br = null;
            $embryo = null;
            if (!empty($source['breeding_record_id'])) {
                $br = DB::row('SELECT * FROM breeding_records WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$source['breeding_record_id']]);
                if ($br && $br['embryo_id']) {
                    $embryo = DB::row('SELECT * FROM embryos WHERE id = ? FOR UPDATE', [$br['embryo_id']]);
                }
            } elseif (!empty($source['embryo_id'])) {
                $embryo = DB::row('SELECT * FROM embryos WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$source['embryo_id']]);
                if ($embryo) {
                    $br = DB::row("SELECT * FROM breeding_records WHERE embryo_id = ? AND deleted_at IS NULL AND status <> 'foaled' ORDER BY id DESC LIMIT 1 FOR UPDATE", [$embryo['id']]);
                }
            }
            if ($embryo && $embryo['status'] === 'foaled') {
                throw new \DomainException(__('breeding.already_foaled'));
            }
            if ($br && $br['status'] === 'foaled') {
                throw new \DomainException(__('breeding.already_foaled'));
            }
            // Genetic parents: an embryo's donor mare is the dam (not the recipient that carried it)
            $damId = $embryo ? (int) $embryo['donor_mare_id'] : (int) ($br['mare_id'] ?? $source['mare_id'] ?? 0);
            $sireId = $embryo ? (int) $embryo['sire_id'] : (int) ($br['stallion_id'] ?? $source['stallion_id'] ?? 0);
            if (!$damId) {
                throw new \DomainException(__('validation.required'));
            }
            $name = trim((string) ($foal['name_en'] ?? '')) ?: 'Foal ' . date('Y-m-d', strtotime($foal['dob']));
            $slug = self::uniqueSlug($name);
            // The foal belongs to the embryo's owner, or (no embryo) to its mare's owner when she is a client's
            $owner = $embryo ?: (DB::row('SELECT owner_type, owner_party_id FROM horses WHERE id = ?', [$damId]) ?: []);
            $clientOwned = ($owner['owner_type'] ?? 'sk') === 'client';
            $id = DB::insert('horses', [
                'name_en' => $name, 'name_ar' => $foal['name_ar'] ?? null, 'slug' => $slug, 'dob' => $foal['dob'],
                'sex' => $foal['sex'], 'category' => HorseRules::category($foal['sex'], $foal['dob']),
                'color_id' => $foal['color_id'] ?? null, 'breed_id' => DB::value('SELECT breed_id FROM horses WHERE id = ?', [$damId]) ?: DB::value("SELECT id FROM lookups WHERE type = 'breed' AND value_en = 'Purebred Arabian'"),
                'sire_id' => $sireId ?: null, 'dam_id' => $damId, 'location_id' => $foal['location_id'] ?? null,
                'owner_type' => $clientOwned ? 'client' : 'sk', 'owner_party_id' => $clientOwned ? ($owner['owner_party_id'] ?? null) : null,
                'status' => 'active', 'born_at_sk' => 1, 'breeder' => 'SK Arabians', 'notes' => $foal['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            if ($br) {
                DB::update('breeding_records', ['status' => 'foaled', 'foaling_date' => $foal['dob'], 'foal_id' => $id, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
            }
            if ($embryo) {
                DB::update('embryos', ['status' => 'foaled', 'foal_id' => $id, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $embryo['id']]);
            }
            self::ensureOwnership($id); // ownership history starts with "born at SK"
            Audit::log('create', 'horses', 'horse', $id, null, ['name_en' => $name, 'dob' => $foal['dob'], 'sire_id' => $sireId, 'dam_id' => $damId, 'breeding_record_id' => $br['id'] ?? null, 'embryo_id' => $embryo['id'] ?? null], 'Foal recorded: ' . $name);
            if (!empty($foal['website'])) {
                self::requestWebsite($id);
            }
            Notifier::roles(['owner', 'general_manager'], 'foaling', Notifier::msg('notify.foal_born', ['name' => $name]), null, '/portal/horses/' . $id, true);
            Cache::bump();
            return $id;
        });
    }

    /** Foals appear in "Latest Foals" only after the Owner approves. The Owner's own request is approved directly. */
    public static function requestWebsite(int $horseId): void
    {
        $h = DB::row('SELECT id, name_en, website_approved_at FROM horses WHERE id = ?', [$horseId]);
        if (!$h || $h['website_approved_at']) {
            return;
        }
        if (Auth::isOwner()) {
            self::approveFoalWebsite($horseId, [], []);
            return;
        }
        Approvals::request('foal_website', 'horse', $horseId, __('approvals.foal_website', ['name' => $h['name_en']]));
    }

    public static function approveFoalWebsite(int $horseId, array $payload, array $approval): void
    {
        DB::update('horses', ['show_on_website' => 1, 'website_approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $horseId]);
        Cache::bump();
    }

    /**
     * Horse finance tab: bills linked to the horse, allocated inventory usage and income. Like the reports
     * (ReportService), approved/paid and pending (draft or waiting for approval) are kept apart: expenses,
     * income and net are the approved figures (inventory used counts as approved), *_pending what is not
     * approved yet, and net_all both together.
     */
    public static function finance(int $horseId): array
    {
        // paid / approved-unpaid / pending approval, as everywhere else; drafts are not counted
        $rows = DB::all("SELECT b.type, c.name_en AS category, c.name_ar AS category_ar, " . FinanceService::bucketSums('b') . "
            FROM bills b LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.horse_id = ? AND b.deleted_at IS NULL AND b.status <> 'cancelled' GROUP BY b.type, c.id, c.name_en, c.name_ar ORDER BY b.type, SUM(b.amount_qar) DESC", [$horseId]);
        $rows = array_values(array_filter(array_map(fn ($r) => array_merge($r, FinanceService::buckets($r)), $rows), fn ($r) => $r['total'] != 0));
        $usage = InventoryService::horseUsageCost($horseId);
        $t = ['expense' => ['ok' => $usage, 'pend' => 0.0], 'income' => ['ok' => 0.0, 'pend' => 0.0]];
        foreach ($rows as $r) {
            if (isset($t[$r['type']])) {
                $t[$r['type']]['ok'] += (float) $r['paid'] + (float) $r['unpaid']; // approved: paid and approved-unpaid (FinanceService::buckets)
                $t[$r['type']]['pend'] += (float) $r['pending'];
            }
        }
        $exp = round($t['expense']['ok'], 2);
        $inc = round($t['income']['ok'], 2);
        $expP = round($t['expense']['pend'], 2);
        $incP = round($t['income']['pend'], 2);
        return ['rows' => $rows, 'usage' => $usage,
            'expenses' => $exp, 'expenses_pending' => $expP, 'income' => $inc, 'income_pending' => $incP,
            'net' => round($inc - $exp, 2), 'net_all' => round(($inc + $incP) - ($exp + $expP), 2),
            'purchase' => DB::value('SELECT purchase_price_qar FROM horses WHERE id = ?', [$horseId])];
    }

    /** Ancestor tree for the portal (all horses, internal and external). */
    public static function pedigree(int $horseId, int $generations = 5): array
    {
        $cache = [];
        $load = function (?int $id) use (&$cache) {
            return $id ? ($cache[$id] ??= DB::row('SELECT id, name_en, name_ar, sire_id, dam_id, is_external FROM horses WHERE id = ?', [$id])) : null;
        };
        $build = function (?int $id, int $depth) use (&$build, $load, $generations) {
            $h = $load($id);
            if (!$h) {
                return null;
            }
            if ($depth < $generations) {
                $h['sire'] = $build($h['sire_id'] ? (int) $h['sire_id'] : null, $depth + 1);
                $h['dam'] = $build($h['dam_id'] ? (int) $h['dam_id'] : null, $depth + 1);
            }
            return $h;
        };
        return $build($horseId, 0) ?? [];
    }

    /** Generations shown and edited everywhere (portal, website, Horse Profile): 5 = 62 ancestors. */
    public const PEDIGREE_GENERATIONS = 5;

    /**
     * The ancestor positions, generation by generation: 's'/'d' = sire/dam, then 'ss', 'sd', 'ds', 'dd'
     * (grandparents) and so on, each letter one step further back. 62 positions for 5 generations.
     */
    public static function pedigreePaths(int $generations = self::PEDIGREE_GENERATIONS): array
    {
        $paths = [];
        $level = [''];
        for ($g = 0; $g < $generations; $g++) {
            $next = [];
            foreach ($level as $p) {
                $next[] = $p . 's';
                $next[] = $p . 'd';
            }
            array_push($paths, ...$next);
            $level = $next;
        }
        return $paths;
    }

    /**
     * Saves a full 5-generation (62-ancestor) pedigree in one transaction. $tree maps a path (see
     * pedigreePaths()) to: an existing horse id, an array ['name_en', 'name_ar', 'country'] to create a
     * new external horse there, or null/'' to clear that position. Paths not present in $tree are left
     * untouched. A position still holding the horse that was on file there before this save is no change: when
     * one horse sits at two positions, the one edited wins. $fromEditor: $tree is the pedigree editor's form,
     * whose boxes start out as the tree on file — behind an ancestor replaced there, the boxes still showing the
     * old ancestor's line are never written to the new one (an import passes false: every name in it is meant).
     * Returns ['completeness' => [filled, total], 'inbreeding' => n repeated ancestors].
     */
    public static function savePedigree(int $horseId, array $tree, bool $fromEditor = false): array
    {
        return DB::transaction(function () use ($horseId, $tree, $fromEditor) {
            $paths = self::pedigreePaths();
            $stored = self::storedPedigree($horseId);
            $resolved = [];
            $created = []; // a new ancestor named at two positions (same name and sex) is one horse
            foreach ($paths as $path) {
                if (!array_key_exists($path, $tree)) {
                    continue;
                }
                $v = $tree[$path];
                if (is_array($v) && trim((string) ($v['name_en'] ?? '')) !== '') {
                    $sex = str_ends_with($path, 's') ? 'male' : 'female';
                    $name = mb_substr(trim((string) $v['name_en']), 0, 120);
                    $key = $sex . '|' . mb_strtolower($name);
                    if (isset($created[$key])) {
                        $resolved[$path] = $created[$key];
                        continue;
                    }
                    $resolved[$path] = $created[$key] = DB::insert('horses', [
                        'name_en' => $name, 'name_ar' => trim((string) ($v['name_ar'] ?? '')) ?: null,
                        'slug' => self::uniqueSlug($name), 'sex' => $sex, 'category' => HorseRules::category($sex, null),
                        'origin_country' => trim((string) ($v['country'] ?? '')) ?: null, 'is_external' => 1,
                        'status' => 'active', 'created_by' => Auth::id(),
                    ]);
                    Audit::log('create', 'horses', 'horse', $resolved[$path], null, ['name_en' => $name, 'is_external' => 1], 'External ancestor created: ' . $name);
                } elseif (is_numeric($v) && (int) $v > 0) {
                    $resolved[$path] = (int) $v;
                } else {
                    $resolved[$path] = null;
                }
            }
            // Every position but the last generation is a child whose own sire/dam are the next positions
            foreach (array_merge([''], self::pedigreePaths(self::PEDIGREE_GENERATIONS - 1)) as $childPath) {
                [$sirePath, $damPath] = [$childPath . 's', $childPath . 'd'];
                if ($childPath === '') {
                    $childId = $horseId;
                } elseif (array_key_exists($childPath, $resolved)) {
                    $childId = $resolved[$childPath];
                } elseif (array_key_exists($sirePath, $resolved) || array_key_exists($damPath, $resolved)) {
                    // a box further back given without this one (e.g. a spreadsheet column): the ancestor on file here
                    $childId = self::ancestorAt($horseId, $childPath);
                } else {
                    continue;
                }
                if (!$childId) {
                    continue;
                }
                $before = DB::row('SELECT sire_id, dam_id FROM horses WHERE id = ?', [$childId]) ?: ['sire_id' => null, 'dam_id' => null];
                // The pedigree tree posts inline with the horse's whole edit form (not just when something
                // in it actually changed), so only columns whose resolved value differs from what's already
                // stored are written — otherwise every unrelated save (e.g. changing the colour) would
                // rewrite the entire tree and log a spurious "Pedigree updated" entry.
                $update = [];
                foreach (['sire_id' => $sirePath, 'dam_id' => $damPath] as $col => $path) {
                    if (!array_key_exists($path, $resolved)) {
                        continue;
                    }
                    $id = $resolved[$path];
                    if ((int) ($before[$col] ?? 0) === (int) ($id ?? 0)) {
                        continue; // unchanged: nothing to write, and a link already on file can't make a new loop
                    }
                    // Still the horse on file at this position before the save: under the same horse that is no change
                    // (another position of that horse may just have changed it); in the editor, behind a replaced
                    // ancestor, it is the old ancestor's line the form showed, not meant for the new one. (An emptied
                    // box is always meant: the editor sends one only when the user cleared it.)
                    if ($id !== null && (int) ($stored[$path] ?? 0) === $id && ($fromEditor || (int) $childId === (int) ($stored[$childPath] ?? 0))) {
                        continue;
                    }
                    if ($id !== null && self::isSelfOrDescendant($childId, $id)) {
                        throw new \DomainException(__('horses.pedigree_loop'));
                    }
                    // A sire must be a male horse and a dam a female one (horses without a recorded sex pass)
                    if ($id !== null) {
                        $anc = DB::row('SELECT name_en, sex FROM horses WHERE id = ?', [$id]);
                        $want = $col === 'sire_id' ? 'male' : 'female';
                        if ($anc && in_array($anc['sex'], ['male', 'female'], true) && $anc['sex'] !== $want) {
                            throw new \DomainException(__($col === 'sire_id' ? 'horses.pedigree_sire_not_male' : 'horses.pedigree_dam_not_female', ['name' => $anc['name_en']]));
                        }
                    }
                    $update[$col] = $id;
                }
                if ($update) {
                    DB::update('horses', $update + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $childId]);
                    Audit::log('update', 'horses', 'horse', $childId, $before, $update, 'Pedigree updated');
                }
            }
            $ids = array_values(array_filter($resolved));
            $inbreeding = count(array_filter(array_count_values($ids), fn ($c) => $c > 1));
            Cache::bump();
            return ['completeness' => [count(array_filter($resolved)), count($paths)], 'inbreeding' => $inbreeding];
        });
    }

    /** The horse on file at every pedigree position ('' = the horse itself), as the editor shows it: path => id or null. */
    private static function storedPedigree(int $horseId): array
    {
        $ids = ['' => $horseId];
        $rows = [];
        foreach (self::pedigreePaths() as $path) {
            $child = $ids[substr($path, 0, -1)];
            if ($child) {
                $rows[$child] ??= DB::row('SELECT sire_id, dam_id FROM horses WHERE id = ?', [$child]) ?: [];
            }
            $ids[$path] = $child ? ((int) ($rows[$child][str_ends_with($path, 's') ? 'sire_id' : 'dam_id'] ?? 0) ?: null) : null;
        }
        return $ids;
    }

    /** The horse recorded at a pedigree position right now ('s' = sire, 'sd' = sire's dam …), or null. */
    private static function ancestorAt(int $horseId, string $path): ?int
    {
        $id = $horseId;
        foreach (str_split($path) as $c) {
            $id = (int) DB::value('SELECT ' . ($c === 's' ? 'sire_id' : 'dam_id') . ' FROM horses WHERE id = ?', [$id]);
            if (!$id) {
                return null;
            }
        }
        return $id;
    }

    /** True when $candidateId is the horse itself or one of its descendants (prevents pedigree loops). */
    public static function isSelfOrDescendant(int $horseId, int $candidateId): bool
    {
        if ($horseId === $candidateId) {
            return true;
        }
        $frontier = [$horseId];
        $seen = [];
        for ($depth = 0; $depth < 12 && $frontier; $depth++) {
            $params = [];
            $inS = DB::in($frontier, 's', $params);
            $inD = DB::in($frontier, 'd', $params);
            $children = array_map('intval', DB::column("SELECT id FROM horses WHERE sire_id IN $inS OR dam_id IN $inD", $params));
            if (in_array($candidateId, $children, true)) {
                return true;
            }
            $frontier = array_values(array_diff($children, $seen));
            $seen = array_merge($seen, $children);
        }
        return false;
    }

    /** Daily: re-suggest categories from age (foal → colt/filly → stallion/mare) unless set manually. */
    public static function recalcCategories(): int
    {
        $n = 0;
        foreach (DB::all('SELECT id, sex, dob, category FROM horses WHERE category_locked = 0 AND deleted_at IS NULL AND dob IS NOT NULL') as $h) {
            $c = HorseRules::category($h['sex'], $h['dob']);
            if ($c !== $h['category']) {
                DB::update('horses', ['category' => $c], 'id = :id', ['id' => $h['id']]);
                Audit::log('update', 'horses', 'horse', $h['id'], ['category' => $h['category']], ['category' => $c], 'Category updated from age', ['id' => null, 'name' => 'system']);
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ ownership

    public const TRANSFER_TYPES = ['sale', 'purchase', 'gift', 'lease', 'breeding_share', 'other'];
    /** Statuses a transfer can set; '' = decided from the transfer (see transferStatus()). */
    public const TRANSFER_STATUSES = ['active', 'in_shelter', 'sold', 'exported', 'leased_out'];

    /** True once the database update that adds ownership history (migration 022) has been applied. */
    public static function ownershipAvailable(): bool
    {
        return DB::hasColumn('horses', 'owner_name');
    }

    /** Creates the opening history row from the horse's owner fields when it has none (horses added before or outside this flow). */
    public static function ensureOwnership(int $horseId): void
    {
        if (!self::ownershipAvailable() || DB::value('SELECT 1 FROM horse_ownership WHERE horse_id = ? AND deleted_at IS NULL LIMIT 1', [$horseId])) {
            return;
        }
        $h = DB::row('SELECT h.*, p.name_en AS party_name FROM horses h LEFT JOIN parties p ON p.id = h.owner_party_id WHERE h.id = ?', [$horseId]);
        if (!$h) {
            return;
        }
        $born = $h['born_at_sk'] && $h['dob'];
        $bought = !$born && $h['purchase_date'] && $h['owner_type'] === 'sk';
        DB::insert('horse_ownership', [
            'horse_id' => $horseId, 'owner_type' => $h['owner_type'], 'owner_party_id' => $h['owner_party_id'],
            'owner_name' => $h['owner_type'] === 'client' ? $h['party_name'] : ($h['owner_type'] === 'other' ? $h['owner_name'] : null),
            'from_date' => $born ? $h['dob'] : ($h['purchase_date'] ?: ($h['dob'] ?: substr((string) $h['created_at'], 0, 10))),
            'transfer_type' => $born ? 'birth' : ($bought ? 'purchase' : 'opening'),
            'price' => $bought ? $h['purchase_price_qar'] : null, 'price_qar' => $bought ? $h['purchase_price_qar'] : null,
            'created_by' => Auth::id(),
        ]);
    }

    /** Ownership history of a horse, newest first (party name, bill number and the previous owner of each row). */
    public static function ownership(int $horseId): array
    {
        if (!self::ownershipAvailable()) {
            return [];
        }
        self::ensureOwnership($horseId);
        $rows = DB::all('SELECT o.*, p.name_en AS party_en, p.name_ar AS party_ar, b.number AS bill_number, b.status AS bill_status, u.name AS by_name
            FROM horse_ownership o LEFT JOIN parties p ON p.id = o.owner_party_id LEFT JOIN bills b ON b.id = o.bill_id LEFT JOIN users u ON u.id = o.created_by
            WHERE o.horse_id = ? AND o.deleted_at IS NULL ORDER BY o.from_date DESC, o.id DESC', [$horseId]);
        foreach ($rows as $i => $r) {
            $rows[$i]['previous'] = $rows[$i + 1] ?? null;
        }
        return $rows;
    }

    /** The owner's name for display: "SK Arabians", the client (Arabic name in Arabic) or the name typed in. */
    public static function ownerLabel(array $o): string
    {
        if (($o['owner_type'] ?? 'sk') === 'sk') {
            return __('horses.owner_sk');
        }
        $party = ['name_en' => $o['party_en'] ?? null, 'name_ar' => $o['party_ar'] ?? null];
        if (!$party['name_en'] && !empty($o['owner_party_id'])) {
            $party = DB::row('SELECT name_en, name_ar FROM parties WHERE id = ?', [$o['owner_party_id']]) ?: $party;
        }
        return $party['name_en'] ? loc($party) : (string) ($o['owner_name'] ?? '—');
    }

    /**
     * The status a transfer gives the horse when the user leaves it on "automatic": back at SK → active; SK leases it
     * out → leased out; SK sells or gives it away → sold; between other owners (e.g. a client's horse boarding here) → unchanged.
     */
    public static function transferStatus(string $current, string $fromType, string $toType, string $transferType): string
    {
        if ($toType === 'sk') {
            return in_array($current, ['sold', 'exported', 'leased_out'], true) ? 'active' : $current;
        }
        if ($fromType === 'sk' && $transferType === 'lease') {
            return 'leased_out';
        }
        if ($fromType === 'sk' && $transferType !== 'breeding_share') {
            return 'sold';
        }
        return $current;
    }

    /**
     * Transfers a horse to a new owner in one transaction: closes the current history row, opens the new one,
     * updates the horse's owner and status, optionally creates the bill (income when SK sells, expense when SK
     * buys — through FinanceService, so the usual approval rules apply) and logs it.
     * $d: owner_type sk|client|other, owner_party_id, owner_name, date, transfer_type, price, currency, status ('' = automatic),
     *     create_bill (bool), notes. Returns ['id' => history row id, 'bill_id' => ?int, 'bill_status' => ?string, 'status' => new horse status].
     * @throws ValidationException
     */
    public static function transferOwnership(int $horseId, array $d): array
    {
        $errors = [];
        $type = (string) ($d['owner_type'] ?? '');
        $partyId = (int) ($d['owner_party_id'] ?? 0) ?: null;
        $name = mb_substr(trim((string) ($d['owner_name'] ?? '')), 0, 150);
        $date = (string) ($d['date'] ?? '');
        $ttype = (string) ($d['transfer_type'] ?? '');
        $status = (string) ($d['status'] ?? '');
        $currency = strtoupper((string) ($d['currency'] ?? Money::BASE)) ?: Money::BASE;
        $price = Money::parse($d['price'] ?? null);
        if (!in_array($type, ['sk', 'client', 'other'], true)) {
            $errors['owner_type'] = __('validation.required');
        }
        if ($type === 'client' && (!$partyId || !DB::value("SELECT 1 FROM parties WHERE id = ? AND deleted_at IS NULL AND type IN ('client','both')", [$partyId]))) {
            $errors['owner_party_id'] = __('validation.pick_from_list');
        }
        if ($type === 'other' && $name === '') {
            $errors['owner_name'] = __('validation.required');
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date || $date > date('Y-m-d')) { // a real day (not 30 February)
            $errors['date'] = __('validation.date');
        }
        if (!in_array($ttype, self::TRANSFER_TYPES, true)) {
            $errors['transfer_type'] = __('validation.required');
        }
        if ($status !== '' && !in_array($status, self::TRANSFER_STATUSES, true)) {
            $errors['status'] = __('validation.required');
        }
        if (($d['price'] ?? '') !== '' && ($d['price'] ?? null) !== null && ($price === null || $price < 0)) {
            $errors['price'] = __('validation.number');
        }
        if (!DB::value('SELECT 1 FROM currencies WHERE code = ?', [$currency])) {
            $errors['currency'] = __('validation.required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return DB::transaction(function () use ($horseId, $type, $partyId, $name, $date, $ttype, $status, $currency, $price, $d) {
            $h = DB::row('SELECT * FROM horses WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$horseId]);
            if (!$h) {
                throw new \DomainException(__('common.not_found'));
            }
            self::ensureOwnership($horseId);
            $cur = DB::row('SELECT * FROM horse_ownership WHERE horse_id = ? AND deleted_at IS NULL AND `to_date` IS NULL ORDER BY from_date DESC, id DESC LIMIT 1 FOR UPDATE', [$horseId])
                ?? ['id' => null, 'owner_type' => $h['owner_type'], 'owner_party_id' => $h['owner_party_id'], 'owner_name' => $h['owner_name'] ?? null, 'from_date' => null];
            if ($cur['from_date'] && $date < $cur['from_date']) {
                throw new ValidationException(['date' => __('ownership.date_before_current', ['date' => fmt_date($cur['from_date'])])]);
            }
            $same = $cur['owner_type'] === $type && match ($type) {
                'sk' => true,
                'client' => (int) $cur['owner_party_id'] === (int) $partyId,
                default => mb_strtolower(trim((string) $cur['owner_name'])) === mb_strtolower($name),
            };
            if ($same) {
                throw new ValidationException(['owner_type' => __('ownership.same_owner')]);
            }
            $partyName = $partyId ? (string) DB::value('SELECT name_en FROM parties WHERE id = ?', [$partyId]) : null;
            $rate = Money::rate($currency);
            $priceQar = $price !== null ? Money::toQar($price, $rate) : null;
            $newStatus = $status !== '' ? $status : self::transferStatus((string) $h['status'], (string) $cur['owner_type'], $type, $ttype);

            if ($cur['id']) {
                DB::update('horse_ownership', ['to_date' => $date, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $cur['id']]);
            }
            $oid = DB::insert('horse_ownership', [
                'horse_id' => $horseId, 'owner_type' => $type, 'owner_party_id' => $type === 'client' ? $partyId : null,
                'owner_name' => $type === 'client' ? $partyName : ($type === 'other' ? $name : null),
                'from_date' => $date, 'transfer_type' => $ttype, 'price' => $price, 'currency' => $currency, 'exchange_rate' => $rate, 'price_qar' => $priceQar,
                'notes' => mb_substr(trim((string) ($d['notes'] ?? '')), 0, 2000) ?: null, 'created_by' => Auth::id(),
            ]);

            // The bill: SK selling → income, SK buying → expense; transfers between other owners have none
            $billId = null;
            $billStatus = null;
            $billType = $cur['owner_type'] === 'sk' && $type !== 'sk' ? 'income' : ($type === 'sk' && $cur['owner_type'] !== 'sk' ? 'expense' : null);
            if (!empty($d['create_bill']) && $billType && $price !== null && $price > 0 && Auth::can('finance', 'create')) {
                $counterparty = $billType === 'income' ? ($type === 'client' ? $partyId : null) : ($cur['owner_type'] === 'client' ? (int) $cur['owner_party_id'] : null);
                $who = $billType === 'income' ? ($partyName ?? $name) : ($cur['owner_type'] === 'client' ? (string) DB::value('SELECT name_en FROM parties WHERE id = ?', [$cur['owner_party_id']]) : (string) $cur['owner_name']);
                $billId = FinanceService::create([
                    'type' => $billType, 'status' => 'draft', 'party_id' => $counterparty ?: null,
                    'category_id' => $billType === 'income' ? (DB::value("SELECT id FROM finance_categories WHERE name_en = 'Horse Sales' AND deleted_at IS NULL ORDER BY id LIMIT 1") ?: null) : null,
                    // the bill's title already names its party; a buyer/seller who is not a client is named here
                    'description' => mb_substr(__($billType === 'income' ? 'ownership.bill_sale' : 'ownership.bill_purchase', ['name' => $h['name_en']]) . (!$counterparty && $who !== '' ? ' — ' . $who : ''), 0, 255),
                    'currency' => $currency, 'exchange_rate' => $rate, 'amount_original' => $price, 'amount_qar' => $priceQar,
                    'bill_date' => $date, 'horse_id' => $horseId, 'notes' => __('ownership.bill_note'),
                ]);
                $billStatus = FinanceService::submit($billId); // income → approved; expense above the limit → waits for approval
                DB::update('horse_ownership', ['bill_id' => $billId], 'id = :id', ['id' => $oid]);
            }

            $before = ['owner_type' => $h['owner_type'], 'owner_party_id' => $h['owner_party_id'], 'owner_name' => $h['owner_name'] ?? null, 'status' => $h['status']];
            $upd = ['owner_type' => $type, 'owner_party_id' => $type === 'client' ? $partyId : null, 'owner_name' => $type === 'other' ? $name : null,
                    'status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
            if ($type === 'sk' && $ttype === 'purchase') {
                $upd['purchase_date'] = $date;
                $upd['purchase_price_qar'] = $priceQar;
            }
            DB::update('horses', $upd, 'id = :id', ['id' => $horseId]);
            $label = $type === 'sk' ? 'SK Arabians' : ($partyName ?? $name);
            Audit::log('transfer', 'horses', 'horse', $horseId, $before, $upd + ['transfer_type' => $ttype, 'date' => $date, 'price_qar' => $priceQar, 'bill_id' => $billId, 'ownership_id' => $oid],
                'Ownership transferred: ' . $h['name_en'] . ' → ' . $label . ' (' . $ttype . ', ' . $date . ')');
            Cache::bump();
            return ['id' => $oid, 'bill_id' => $billId, 'bill_status' => $billStatus, 'status' => $newStatus];
        });
    }

    // ------------------------------------------------------------------ pregnancy checks

    /**
     * The next pregnancy check of each open or pregnant breeding record (natural cover, AI, or an embryo transfer —
     * its start date is the transfer date) at HorseRules::PREGNANCY_CHECK_DAYS after the start, due on or before
     * $until and not yet recorded. A recorded check (any result but "scheduled") made from 3 days before a milestone
     * counts for it — and for every earlier one. Only covers/transfers of the last 90 days, of mares at the stud, are followed.
     * Rows: id (breeding record), mare_id, name_en, name_ar, start_date, day (14/30/45/60), due_date, days (from today).
     */
    public static function pregnancyChecksDue(string $until): array
    {
        $days = HorseRules::PREGNANCY_CHECK_DAYS;
        $params = ['since' => date('Y-m-d', strtotime('-' . (max($days) + 30) . ' days')), 'upto' => date('Y-m-d', strtotime($until . ' -' . min($days) . ' days'))];
        $scope = '';
        if (Auth::check() && Auth::horseScopeAssigned()) {
            $scope = ' AND b.mare_id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'pcs', $params);
        }
        $rows = DB::all("SELECT b.id, b.mare_id, b.start_date, m.name_en, m.name_ar,
                (SELECT MAX(c.check_date) FROM pregnancy_checks c WHERE c.breeding_record_id = b.id AND c.deleted_at IS NULL AND c.result <> 'scheduled') AS last_check
            FROM breeding_records b JOIN horses m ON m.id = b.mare_id
            WHERE b.deleted_at IS NULL AND m.deleted_at IS NULL AND m.status IN ('active','in_shelter') AND b.status IN ('open','pregnant') AND b.start_date >= :since AND b.start_date <= :upto$scope
            ORDER BY b.start_date", $params);
        $today = strtotime(date('Y-m-d'));
        $out = [];
        foreach ($rows as $r) {
            foreach ($days as $n) {
                $due = date('Y-m-d', strtotime($r['start_date'] . ' +' . $n . ' days'));
                if ($r['last_check'] && $r['last_check'] >= date('Y-m-d', strtotime($due . ' -3 days'))) {
                    continue; // this milestone was checked
                }
                if ($due <= $until) {
                    $out[] = $r + ['day' => $n, 'due_date' => $due, 'days' => (int) floor((strtotime($due) - $today) / 86400)];
                }
                break; // only the next milestone of each record
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
        return $out;
    }

    public static function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = slugify($name);
        $slug = $base;
        $i = 2;
        while (DB::value('SELECT id FROM horses WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
