<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Leave rules: which days count (the weekly day off and public holidays do not), which types are paid, and the annual
 * leave balance.
 *
 * Balance ("calculated" mode, switched on from the Owner's check page): an opening balance on a date, plus the days
 * earned from then on (the yearly entitlement spread over the year, counted from the hire date when later), minus the
 * approved annual leave taken since. Sick and other leave never touch it. Before the switch ("typed" mode) the balance
 * is the number typed on the employee form, as it always was.
 */
final class LeaveService
{
    /** Days of the week off (0 = Sunday … 6 = Saturday; default Friday). */
    public static function weekendDays(): array
    {
        $raw = (string) Settings::get('hr.weekend_days', '5');
        return array_values(array_unique(array_map('intval', array_filter(explode(',', $raw), fn ($d) => preg_match('/^[0-6]$/', trim($d))))));
    }

    /** Public holidays between two dates: [Y-m-d => name]. */
    public static function holidays(string $from, string $to): array
    {
        if (!DB::hasColumn('public_holidays', 'holiday_date')) {
            return [];
        }
        $out = [];
        foreach (DB::all('SELECT holiday_date, name_en, name_ar FROM public_holidays WHERE deleted_at IS NULL AND holiday_date BETWEEN ? AND ? ORDER BY holiday_date', [$from, $to]) as $h) {
            $out[$h['holiday_date']] = loc(['name_en' => $h['name_en'], 'name_ar' => $h['name_ar']]);
        }
        return $out;
    }

    /** Leave days between two dates (inclusive): without the weekly day off and public holidays. */
    public static function workingDays(string $start, string $end): float
    {
        if ($end < $start) {
            return 0.0;
        }
        $off = self::weekendDays();
        $hol = self::holidays($start, $end);
        $n = 0;
        for ($d = new \DateTime($start), $last = new \DateTime($end); $d <= $last; $d->modify('+1 day')) {
            if (!in_array((int) $d->format('w'), $off, true) && !isset($hol[$d->format('Y-m-d')])) {
                $n++;
            }
        }
        return (float) $n;
    }

    /** Leave types that are paid (Settings → Leave; default annual, sick and emergency). */
    public static function paidTypes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Settings::get('hr.paid_leave_types', 'annual,sick,emergency')))));
    }

    public static function isPaid(string $type): bool
    {
        return in_array($type, self::paidTypes(), true);
    }

    public static function calculated(): bool
    {
        return Settings::get('hr.leave_balance_mode', 'typed') === 'calculated' && DB::hasColumn('employees', 'leave_opening_date');
    }

    /**
     * The annual leave position of one employee: entitlement per year, earned, used, pending and remaining days.
     * $e needs id, hire_date, annual_leave_days, leave_balance, leave_opening_balance, leave_opening_date.
     */
    public static function balance(array $e, ?string $asOf = null): array
    {
        $asOf ??= date('Y-m-d');
        $year = (float) ($e['annual_leave_days'] ?? 30);
        $pending = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'pending' AND deleted_at IS NULL", [$e['id']]);
        if (!self::calculated()) {
            $left = (float) ($e['leave_balance'] ?? 0);
            return ['mode' => 'typed', 'per_year' => $year, 'opening' => null, 'since' => null, 'earned' => null, 'used' => null, 'pending' => $pending, 'balance' => $left, 'remaining' => max(0.0, $left - $pending)];
        }
        [$opening, $since] = self::opening($e);
        $earned = self::earned($year, $since, $asOf, $e['hire_date'] ?? null);
        $used = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'approved' AND deleted_at IS NULL AND start_date >= ?",
            [$e['id'], $since]);
        $left = round($opening + $earned - $used, 1);
        return ['mode' => 'calculated', 'per_year' => $year, 'opening' => $opening, 'since' => $since, 'earned' => round($earned, 1), 'used' => $used,
                'pending' => $pending, 'balance' => $left, 'remaining' => max(0.0, round($left - $pending, 1))];
    }

    /** The opening balance and its date: the one set for the employee, else 0 at the start of this year (or the hire date when later). */
    public static function opening(array $e): array
    {
        if (!empty($e['leave_opening_date'])) {
            return [(float) ($e['leave_opening_balance'] ?? 0), (string) $e['leave_opening_date']];
        }
        $start = date('Y-01-01');
        if (!empty($e['hire_date']) && $e['hire_date'] > $start) {
            $start = (string) $e['hire_date'];
        }
        return [0.0, $start];
    }

    /**
     * Days earned from $since to $asOf at $perYear a year (none before the hire date). The opening balance is what was
     * left at the start of $since, so nothing is earned on that day itself: a full year from 1 January earns $perYear
     * on the next 1 January.
     */
    public static function earned(float $perYear, string $since, string $asOf, ?string $hired): float
    {
        $from = $hired && $hired > $since ? $hired : $since;
        if ($asOf <= $from) {
            return 0.0;
        }
        $days = (new \DateTime($from))->diff(new \DateTime($asOf))->days;
        return $perYear * $days / 365;
    }

    /** The balance an employee would get when calculated balances are switched on (for the Owner's preview). */
    public static function proposedOpening(array $e): array
    {
        // A balance HR typed in (anything but the default 0) is taken as "left today"; otherwise days are earned from
        // the start of this year (or the hire date when later).
        if ((float) ($e['leave_balance'] ?? 0) > 0) {
            // approved annual leave from today on already came off the typed figure; the calculation takes it off
            // again as used, so it is added back here
            $ahead = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'approved' AND deleted_at IS NULL AND start_date >= ?",
                [$e['id'], date('Y-m-d')]);
            return [(float) $e['leave_balance'] + $ahead, date('Y-m-d')];
        }
        $start = date('Y-01-01');
        if (!empty($e['hire_date']) && $e['hire_date'] > $start) {
            $start = (string) $e['hire_date'];
        }
        return [0.0, $start];
    }
}
