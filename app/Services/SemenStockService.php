<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * All semen straw counts change ONLY through here — never a direct UPDATE on semen_stock.straws —
 * so every change is both row-locked (safe against two users recording at once) and logged as a
 * movement. OUT, and the out leg of a TRANSFER, can never take the straws below zero.
 */
final class SemenStockService
{
    /** Row-locks the stock, applies a signed delta, writes the matching movement. Never lets straws go below zero. */
    private static function apply(int $stockId, string $type, int $delta, array $extra = []): int
    {
        return DB::transaction(function () use ($stockId, $type, $delta, $extra) {
            $stock = DB::row('SELECT * FROM semen_stock WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$stockId]);
            if (!$stock) {
                throw new \DomainException(__('validation.invalid'));
            }
            $new = (int) $stock['straws'] + $delta;
            if ($new < 0) {
                throw new \DomainException(__('semen_stock.not_enough', ['have' => $stock['straws']]));
            }
            DB::update('semen_stock', ['straws' => $new, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $stockId]);
            $movementId = DB::insert('semen_movements', [
                'stock_id' => $stockId, 'type' => $type, 'movement_date' => $extra['movement_date'] ?? date('Y-m-d'),
                'straws_delta' => $delta, 'doses' => $extra['doses'] ?? null, 'recipient' => $extra['recipient'] ?? null,
                'mare_id' => $extra['mare_id'] ?? null, 'related_stock_id' => $extra['related_stock_id'] ?? null,
                'reason' => $extra['reason'] ?? null, 'notes' => $extra['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            Audit::log(
                'movement',
                'semen_stock',
                'semen_stock',
                $stockId,
                ['straws' => $stock['straws']],
                ['straws' => $new, 'type' => $type, 'delta' => $delta],
                __('semen_stock.audit_' . $type, ['delta' => abs($delta)])
            );
            Cache::bump();
            return $movementId;
        });
    }

    /** Generic single-leg in/out, used by the create form's "initial straws" and anywhere a plain in/out suffices. */
    public static function movement(int $stockId, string $type, int $straws, array $extra = []): int
    {
        if ($straws <= 0) {
            throw new \DomainException(__('validation.number'));
        }
        return self::apply($stockId, $type, $type === 'out' ? -$straws : $straws, $extra);
    }

    public static function recordIn(int $stockId, int $straws, string $date, ?string $notes): int
    {
        if ($straws <= 0) {
            throw new \DomainException(__('validation.number'));
        }
        return self::apply($stockId, 'in', $straws, ['movement_date' => $date, 'notes' => $notes]);
    }

    public static function recordOut(int $stockId, int $straws, string $date, ?int $doses, ?string $recipient, ?int $mareId, ?string $notes): int
    {
        if ($straws <= 0) {
            throw new \DomainException(__('validation.number'));
        }
        if (trim((string) $recipient) === '') {
            throw new \DomainException(__('validation.required'));
        }
        return self::apply($stockId, 'out', -$straws, ['movement_date' => $date, 'doses' => $doses, 'recipient' => $recipient, 'mare_id' => $mareId, 'notes' => $notes]);
    }

    /** $countedTotal is the physically counted straw count (not a delta); reason is required. */
    public static function recordAdjustment(int $stockId, int $countedTotal, string $date, string $reason, ?string $notes): int
    {
        if (trim($reason) === '') {
            throw new \DomainException(__('validation.required'));
        }
        if ($countedTotal < 0) {
            throw new \DomainException(__('validation.number'));
        }
        // Read the current count under the same row lock apply() takes, so a movement recorded at the same time
        // can't slip in between reading the count and writing the difference.
        return DB::transaction(function () use ($stockId, $countedTotal, $date, $reason, $notes) {
            $stock = DB::row('SELECT straws FROM semen_stock WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$stockId]);
            if (!$stock) {
                throw new \DomainException(__('validation.invalid'));
            }
            $delta = $countedTotal - (int) $stock['straws'];
            if ($delta === 0) {
                throw new \DomainException(__('semen_stock.no_change'));
            }
            DB::update('semen_stock', ['report_date' => $date], 'id = :id', ['id' => $stockId]);
            return self::apply($stockId, 'adjustment', $delta, ['movement_date' => $date, 'reason' => $reason, 'notes' => $notes]);
        });
    }

    /**
     * Finds an existing stock row in the destination canister with the same stallion/batch/color/size/owner,
     * or creates one (starting at 0 straws) so the transfer has somewhere to land. Call it inside the same
     * DB::transaction as recordTransfer(): a refused transfer then leaves no empty 0-straw row behind.
     * Archived rows are not reused: straws moved into one would drop out of every total (it is not unarchived
     * behind the user's back either — a new row is opened).
     */
    public static function findOrCreateDestination(array $source, int $destCanisterId): int
    {
        if ((int) $source['canister_id'] === $destCanisterId) {
            throw new \DomainException(__('semen_stock.same_canister'));
        }
        // The destination canister (and its tank) must still exist — not deleted to the Trash
        if (!DB::value('SELECT c.id FROM canisters c JOIN tanks tk ON tk.id = c.tank_id WHERE c.id = ? AND c.deleted_at IS NULL AND tk.deleted_at IS NULL', [$destCanisterId])) {
            throw new \DomainException(__('validation.pick_from_list'));
        }
        $existing = DB::row(
            'SELECT id FROM semen_stock WHERE canister_id = ? AND deleted_at IS NULL AND archived_at IS NULL
             AND COALESCE(stallion_id, 0) = COALESCE(?, 0) AND COALESCE(stallion_name_free, "") = COALESCE(?, "")
             AND COALESCE(batch_label, "") = COALESCE(?, "") AND COALESCE(color_id, 0) = COALESCE(?, 0)
             AND COALESCE(straw_size, "") = COALESCE(?, "") AND owner_type = ? AND COALESCE(owner_party_id, 0) = COALESCE(?, 0)',
            [$destCanisterId, $source['stallion_id'], $source['stallion_name_free'], $source['batch_label'], $source['color_id'], $source['straw_size'], $source['owner_type'], $source['owner_party_id']]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        return DB::insert('semen_stock', [
            'stallion_id' => $source['stallion_id'], 'stallion_name_free' => $source['stallion_name_free'],
            'batch_label' => $source['batch_label'], 'canister_id' => $destCanisterId,
            'straws_per_dose_min' => $source['straws_per_dose_min'], 'straws_per_dose_max' => $source['straws_per_dose_max'],
            'straw_size' => $source['straw_size'], 'color_id' => $source['color_id'],
            'owner_type' => $source['owner_type'], 'owner_party_id' => $source['owner_party_id'], 'created_by' => Auth::id(),
        ]);
    }

    /** Moves $straws from one stock row to another (which may be in a different tank/canister). */
    public static function recordTransfer(int $sourceId, int $destId, int $straws, string $date, ?string $notes): array
    {
        if ($straws <= 0) {
            throw new \DomainException(__('validation.number'));
        }
        if ($sourceId === $destId) {
            throw new \DomainException(__('semen_stock.same_stock'));
        }
        return DB::transaction(function () use ($sourceId, $destId, $straws, $date, $notes) {
            $outId = self::apply($sourceId, 'transfer', -$straws, ['movement_date' => $date, 'notes' => $notes, 'related_stock_id' => $destId]);
            $inId = self::apply($destId, 'transfer', $straws, ['movement_date' => $date, 'notes' => $notes, 'related_stock_id' => $sourceId]);
            return [$outId, $inId];
        });
    }

    /**
     * Doses in a stock row, counted conservatively with the most straws a dose can need (straws_per_dose_max).
     * The one rule for every place that shows doses (lists, summary, low-stock, printed report).
     */
    public static function doses(int $straws, array $row): int
    {
        $per = max(1, (int) ($row['straws_per_dose_max'] ?? 0), (int) ($row['straws_per_dose_min'] ?? 0));
        return intdiv(max(0, $straws), $per);
    }

    /** The same rule as doses() in SQL, for a semen_stock row aliased as $alias. */
    public static function dosesSql(string $alias = 't'): string
    {
        DB::assertIdent($alias);
        return "FLOOR($alias.straws / GREATEST($alias.straws_per_dose_max, $alias.straws_per_dose_min, 1))";
    }

    /** Every stallion's straws/doses across all canisters — for the grouped summary on the stock list. */
    public static function stallionSummary(): array
    {
        return DB::all("SELECT COALESCE(st.name_en, t.stallion_name_free) AS name, t.stallion_id,
                SUM(t.straws) AS straws, SUM(" . self::dosesSql() . ") AS doses_min, COUNT(*) AS rows_count
            FROM semen_stock t LEFT JOIN horses st ON st.id = t.stallion_id
            WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
            GROUP BY t.stallion_id, COALESCE(st.name_en, t.stallion_name_free)
            ORDER BY name");
    }

    /** Stallions whose doses across ALL their stock rows (every tank/canister) are at or below the low-stock threshold. */
    public static function lowStockCount(): int
    {
        $threshold = (int) Settings::get('semen_stock.low_threshold', 2);
        return (int) DB::value('SELECT COUNT(*) FROM (SELECT 1 FROM semen_stock t LEFT JOIN horses st ON st.id = t.stallion_id
            WHERE t.deleted_at IS NULL AND t.archived_at IS NULL
            GROUP BY t.stallion_id, COALESCE(st.name_en, t.stallion_name_free)
            HAVING SUM(' . self::dosesSql() . ') <= ?) x', [$threshold]);
    }

    public static function totalDoses(): int
    {
        return (int) DB::value('SELECT COALESCE(SUM(' . self::dosesSql() . '),0) FROM semen_stock t WHERE t.deleted_at IS NULL AND t.archived_at IS NULL');
    }
}
