<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Recurring bills (Finance → Recurring bills): each template creates the same bill on a schedule — weekly, monthly,
 * quarterly or yearly from its start date — through the daily "recurring" task. A bill over the approval limit waits
 * for the Owner / General Manager exactly like one typed in by hand.
 */
final class RecurringBillService
{
    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    /** True once the database update that adds recurring bills (migration 015) has been applied. */
    public static function available(): bool
    {
        static $ok = null;
        return $ok ??= (bool) DB::value("SHOW TABLES LIKE 'recurring_bills'");
    }

    /** Creates every bill that is due up to $today, missed dates included. Returns how many were created. */
    public static function createDue(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $n = 0;
        foreach (DB::column('SELECT id FROM recurring_bills WHERE active = 1 AND deleted_at IS NULL AND next_date <= ? ORDER BY next_date, id', [$today]) as $id) {
            for ($guard = 0; $guard < 400 && self::createNext((int) $id, $today); $guard++) {
                $n++;
            }
        }
        if ($n) {
            Cache::bump();
        }
        return $n;
    }

    /** The template's next bill if it is due: created, and the template moved on to the following date. */
    private static function createNext(int $id, string $today): bool
    {
        return DB::transaction(function () use ($id, $today) {
            $t = DB::row('SELECT * FROM recurring_bills WHERE id = ? AND active = 1 AND deleted_at IS NULL FOR UPDATE', [$id]);
            if (!$t || $t['next_date'] > $today) {
                return false;
            }
            if ($t['end_date'] && $t['next_date'] > $t['end_date']) {
                DB::update('recurring_bills', ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]); // its last bill is done
                return false;
            }
            // a bill for this date may already exist (the template's dates were moved back by hand): never twice
            $billId = DB::value('SELECT id FROM bills WHERE recurring_bill_id = ? AND bill_date = ?', [$id, $t['next_date']]);
            $created = !$billId;
            $billId = $billId ?: self::createBill($t, $t['next_date']);
            DB::update('recurring_bills', [
                'next_date' => self::nextDate($t['next_date'], $t['frequency'], $t['start_date']), 'last_bill_id' => $billId,
                'bills_created' => (int) $t['bills_created'] + ($created ? 1 : 0), 'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);
            return true;
        });
    }

    /** Each bill counts as entered by whoever last saved the template: over the limit it waits for approval, which
     * that person can't give themselves, unless they are the Owner (whose own bills need none). */
    private static function createBill(array $t, string $date): int
    {
        $rate = Money::rate($t['currency']);
        $amountQar = Money::toQar((float) $t['amount_original'], $rate);
        $by = (int) ($t['saved_by'] ?: $t['created_by']) ?: null;
        $byOwner = $by && DB::value("SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL", [$by]) === 'owner';
        $pending = $t['type'] !== 'income' && $amountQar > FinanceService::limit() && !$byOwner;
        $data = [
            'type' => $t['type'], 'category_id' => (int) $t['category_id'], 'subcategory_id' => $t['subcategory_id'] ?: null, 'party_id' => $t['party_id'] ?: null,
            'description' => mb_substr(($t['description'] ?: $t['name']) . ' — ' . self::periodLabel($date, $t['frequency']), 0, 255),
            'currency' => $t['currency'], 'exchange_rate' => $rate, 'amount_original' => (float) $t['amount_original'], 'amount_qar' => $amountQar,
            'bill_date' => $date, 'due_date' => (int) $t['due_days'] > 0 ? date('Y-m-d', strtotime($date . ' +' . (int) $t['due_days'] . ' days')) : null,
            'horse_id' => $t['horse_id'] ?: null, 'employee_id' => $t['employee_id'] ?: null,
            'reference_no' => 'REC-' . $t['id'], 'recurring_bill_id' => (int) $t['id'], 'created_by' => $by,
            'status' => $pending ? 'pending' : 'approved',
        ];
        if (!$pending) {
            $data['approved_by'] = $by; // as when they enter one by hand under the limit
        }
        $billId = FinanceService::create($data);
        if ($pending) {
            // titled from the saved bill, which has its number by now
            Approvals::request('bill', 'bill', $billId, FinanceService::title(DB::row('SELECT * FROM bills WHERE id = ?', [$billId])), $amountQar, [], $by);
        }
        return $billId;
    }

    /** "October 2026", "Q4 2026", "2026", or the date for a weekly bill — added to the bill's description. */
    public static function periodLabel(string $date, string $frequency): string
    {
        return match ($frequency) {
            'weekly' => fmt_date($date),
            'quarterly' => 'Q' . (int) ceil((int) substr($date, 5, 2) / 3) . ' ' . substr($date, 0, 4),
            'yearly' => substr($date, 0, 4),
            default => fmt_month($date),
        };
    }

    /** The date after $date: weekly is 7 days on; monthly, quarterly and yearly keep the start date's day of the
     * month (a template started on the 31st falls on the last day of shorter months, and back on the 31st after). */
    public static function nextDate(string $date, string $frequency, string $start): string
    {
        if ($frequency === 'weekly') {
            return date('Y-m-d', strtotime($date . ' +7 days'));
        }
        $add = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12][$frequency] ?? 1;
        $m = (int) substr($date, 5, 2) + $add;
        $y = (int) substr($date, 0, 4) + intdiv($m - 1, 12);
        $m = ($m - 1) % 12 + 1;
        $day = min((int) substr($start, 8, 2), (int) date('t', mktime(0, 0, 0, $m, 1, $y)));
        return sprintf('%04d-%02d-%02d', $y, $m, $day);
    }

    /** The first scheduled date on or after $from: used when a template starts in the past or is switched back on,
     * so it doesn't create a pile of old bills at once. */
    public static function firstOnOrAfter(string $start, string $frequency, string $from): string
    {
        $d = $start;
        for ($i = 0; $d < $from && $i < 2000; $i++) {
            $d = self::nextDate($d, $frequency, $start);
        }
        return $d;
    }
}
