<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/** Horse workflows that touch several modules: foaling, ownership transfer, website approval, finance summary. */
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
            $id = DB::insert('horses', [
                'name_en' => $name, 'name_ar' => $foal['name_ar'] ?? null, 'slug' => $slug, 'dob' => $foal['dob'],
                'sex' => $foal['sex'], 'category' => HorseRules::category($foal['sex'], $foal['dob']),
                'color_id' => $foal['color_id'] ?? null, 'breed_id' => DB::value('SELECT breed_id FROM horses WHERE id = ?', [$damId]) ?: DB::value("SELECT id FROM lookups WHERE type = 'breed' AND value_en = 'Purebred Arabian'"),
                'sire_id' => $sireId ?: null, 'dam_id' => $damId, 'location_id' => $foal['location_id'] ?? null,
                'owner_type' => $embryo && $embryo['owner_type'] === 'client' ? 'client' : 'sk', 'owner_party_id' => $embryo['owner_party_id'] ?? null,
                'status' => 'active', 'born_at_sk' => 1, 'breeder' => 'SK Arabians', 'notes' => $foal['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            DB::insert('ownership_history', ['horse_id' => $id, 'event_type' => 'birth', 'event_date' => $foal['dob'], 'created_by' => Auth::id()]);
            if ($br) {
                DB::update('breeding_records', ['status' => 'foaled', 'foaling_date' => $foal['dob'], 'foal_id' => $id, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
            }
            if ($embryo) {
                DB::update('embryos', ['status' => 'foaled', 'foal_id' => $id, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $embryo['id']]);
            }
            Audit::log('create', 'horses', 'horse', $id, null, ['name_en' => $name, 'dob' => $foal['dob'], 'sire_id' => $sireId, 'dam_id' => $damId, 'breeding_record_id' => $br['id'] ?? null, 'embryo_id' => $embryo['id'] ?? null], 'Foal recorded: ' . $name);
            if (!empty($foal['website'])) {
                self::requestWebsite($id);
            }
            Notifier::roles(['owner', 'general_manager'], 'foaling', __('notify.foal_born', ['name' => $name]), null, '/portal/horses/' . $id, true);
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

    /** Ownership transfer request (buyer from the Clients list). Needs Owner / GM approval. */
    public static function requestTransfer(int $horseId, array $d): int
    {
        $h = DB::row('SELECT * FROM horses WHERE id = ? AND deleted_at IS NULL', [$horseId]);
        if (!$h || in_array($h['status'], ['sold', 'transferred', 'deceased'], true)) {
            throw new \DomainException(__('horses.cannot_transfer'));
        }
        if (DB::value("SELECT id FROM ownership_history WHERE horse_id = ? AND status = 'pending'", [$horseId])) {
            throw new \DomainException(__('horses.transfer_pending'));
        }
        $rate = $d['currency'] === Money::BASE ? 1.0 : (float) $d['exchange_rate'];
        $priceQar = $d['price'] !== null ? Money::toQar((float) $d['price'], $rate) : null;
        $id = DB::insert('ownership_history', [
            'horse_id' => $horseId, 'event_type' => $d['event_type'], 'event_date' => $d['date'],
            'from_label' => $h['owner_type'] === 'sk' ? 'SK Arabians' : (string) DB::value('SELECT name_en FROM parties WHERE id = ?', [$h['owner_party_id']]),
            'from_party_id' => $h['owner_type'] === 'client' ? $h['owner_party_id'] : null, 'to_party_id' => $d['party_id'],
            'price_qar' => $priceQar, 'currency' => $d['price'] !== null ? $d['currency'] : null, 'exchange_rate' => $d['price'] !== null ? $rate : null,
            'price_original' => $d['price'], 'status' => 'pending', 'notes' => $d['notes'], 'created_by' => Auth::id(),
        ]);
        Approvals::request('horse_sale', 'ownership', $id, __('approvals.horse_transfer', ['name' => $h['name_en']]), $priceQar);
        return $id;
    }

    public static function approveSale(int $ownershipId, array $payload, array $approval): void
    {
        $o = DB::row('SELECT * FROM ownership_history WHERE id = ? FOR UPDATE', [$ownershipId]);
        if (!$o || $o['status'] !== 'pending') {
            return;
        }
        DB::update('ownership_history', ['status' => 'completed'], 'id = :id', ['id' => $ownershipId]);
        DB::update('horses', [
            'status' => $o['event_type'] === 'sale' ? 'sold' : 'transferred', 'owner_type' => 'client', 'owner_party_id' => $o['to_party_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $o['horse_id']]);
        DB::run('DELETE FROM horse_assignments WHERE horse_id = ?', [$o['horse_id']]);
        if ((float) $o['price_qar'] > 0) {
            // Income record (invoice + bill) so the amount is part of the finance reports
            $billId = FinanceService::createIncomeFromTransfer($o);
            DB::update('ownership_history', ['bill_id' => $billId], 'id = :id', ['id' => $ownershipId]);
        }
        if (method_exists(Studio::class, 'generateFromRecord')) {
            $docId = Studio::generateFromRecord('transfer', $ownershipId, (int) $approval['requested_by']);
            if ($docId) {
                DB::update('ownership_history', ['document_id' => $docId], 'id = :id', ['id' => $ownershipId]);
            }
        }
        Cache::bump();
    }

    public static function rejectSale(int $ownershipId, array $payload, array $approval): void
    {
        DB::update('ownership_history', ['status' => 'rejected'], "id = :id AND status = 'pending'", ['id' => $ownershipId]);
    }

    /** Horse finance tab: bills linked to the horse, allocated inventory usage and income. */
    public static function finance(int $horseId): array
    {
        $rows = DB::all("SELECT b.type, c.name_en AS category, SUM(b.amount_qar) AS total,
                SUM(CASE WHEN b.status IN ('approved','partially_paid','paid','overdue') THEN b.amount_qar ELSE 0 END) AS approved,
                SUM(CASE WHEN b.status IN ('draft','pending') THEN b.amount_qar ELSE 0 END) AS pending
            FROM bills b LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.horse_id = ? AND b.deleted_at IS NULL AND b.status <> 'cancelled' GROUP BY b.type, c.name_en ORDER BY b.type, total DESC", [$horseId]);
        $usage = InventoryService::horseUsageCost($horseId);
        $exp = $usage;
        $inc = 0.0;
        foreach ($rows as $r) {
            if ($r['type'] === 'expense') {
                $exp += (float) $r['total'];
            } elseif ($r['type'] === 'income') {
                $inc += (float) $r['total'];
            }
        }
        return ['rows' => $rows, 'usage' => $usage, 'expenses' => round($exp, 2), 'income' => round($inc, 2), 'net' => round($inc - $exp, 2),
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
            $in = DB::in($frontier, 'f', $params);
            $children = array_map('intval', DB::column("SELECT id FROM horses WHERE sire_id IN $in OR dam_id IN $in", $params));
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
