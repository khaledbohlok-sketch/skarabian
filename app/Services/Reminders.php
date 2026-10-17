<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Everything coming up or overdue, gathered from the records the user may see: staff documents, vet and
 * farrier due dates, pregnancy checks, foalings, nitrogen tank refills and unpaid bills. Used by the Studio
 * "Reminders" sheet and the daily reminders (Cron).
 * Each item: cat, title, detail, date, days (from today, negative = overdue), status (over|soon), url — title and
 * detail in the current language — plus msg / detail_msg, the same as translatable messages (Notifier::msg()) so
 * a notification made from the item is shown in each reader's own language.
 */
final class Reminders
{
    public const DOCS = ['qid_expiry' => 'hr.qid', 'passport_expiry' => 'hr.passport', 'visa_expiry' => 'hr.visa', 'health_card_expiry' => 'hr.health_card'];

    public static function all(int $window = 60, bool $all = false): array
    {
        $can = fn (string $m) => $all || Auth::can($m);
        $limit = date('Y-m-d', strtotime("+$window days"));
        $today = date('Y-m-d');
        $out = [];
        $add = function (string $cat, array $msg, array|string|null $detail, string $date, ?string $url) use (&$out) {
            $days = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
            $out[] = ['cat' => $cat, 'title' => Notifier::render($msg), 'detail' => $detail !== null ? Notifier::render($detail) : null, 'date' => $date, 'days' => $days,
                      'status' => $days < 0 ? 'over' : 'soon', 'url' => $url, 'msg' => $msg, 'detail_msg' => $detail];
        };
        $name = fn (array $r, string $p = '') => ['en' => (string) $r[$p . 'name_en'], 'ar' => (string) ($r[$p . 'name_ar'] ?? '')];
        if ($can('hr')) {
            foreach (self::DOCS as $col => $label) {
                foreach (DB::all("SELECT id, name_en, name_ar, $col AS d FROM employees WHERE deleted_at IS NULL AND status <> 'left' AND $col IS NOT NULL AND $col <= ?", [$limit]) as $r) {
                    $add('qid', Notifier::msg($r['d'] < $today ? 'notify.doc_expired' : 'notify.doc_expires', ['name' => $name($r), 'doc' => Notifier::msg($label)]), null, $r['d'], '/portal/employees/' . $r['id']);
                }
            }
        }
        if ($can('horse_health')) {
            foreach (DB::all("SELECT r.id, r.type, r.title, r.next_due_date AS d, h.name_en, h.name_ar FROM health_records r JOIN horses h ON h.id = r.horse_id
                WHERE r.deleted_at IS NULL AND h.deleted_at IS NULL AND h.status IN ('active','in_shelter') AND r.next_due_date IS NOT NULL AND r.next_due_date <= ?
                AND NOT EXISTS (SELECT 1 FROM health_records n WHERE n.horse_id = r.horse_id AND n.type = r.type AND n.record_date >= r.next_due_date AND n.deleted_at IS NULL)", [$limit]) as $r) {
                $add('vet', Notifier::msg('notify.horse_item', ['name' => $name($r), 'item' => Notifier::msg('health.type_' . $r['type'])]), $r['title'], $r['d'], '/portal/health-records/' . $r['id']);
            }
        }
        if ($can('horse_breeding')) {
            foreach (HorseService::pregnancyChecksDue($limit) as $r) {
                $add('check', Notifier::msg('notify.preg_check', ['name' => $name($r), 'day' => $r['day']]),
                    Notifier::msg('notify.preg_check_detail', ['date' => ['date' => $r['start_date']]]), $r['due_date'], '/portal/breeding-records/' . $r['id']);
            }
            foreach (DB::all("SELECT b.id, b.expected_foaling_date AS d, m.name_en, m.name_ar FROM breeding_records b JOIN horses m ON m.id = b.mare_id
                WHERE b.deleted_at IS NULL AND b.status = 'pregnant' AND b.expected_foaling_date IS NOT NULL AND b.expected_foaling_date <= ?", [$limit]) as $r) {
                $add('foal', Notifier::msg('notify.horse_item', ['name' => $name($r), 'item' => Notifier::msg('breeding.foaling_due')]), null, $r['d'], '/portal/breeding-records/' . $r['id']);
            }
        }
        if ($can('semen_stock')) {
            foreach (self::tanksDue($limit) as $t) {
                $add('tank', Notifier::msg('notify.tank_refill', ['tank' => $t['name']]),
                    $t['last_refill_date'] ? Notifier::msg('notify.tank_last_refill', ['date' => ['date' => $t['last_refill_date']]]) : null, $t['due_date'], '/portal/tanks/' . $t['id'] . '?tab=refills');
            }
        }
        if ($can('finance')) {
            $due = FinanceService::dueSql('b', 'p'); // without a due date: the bill date plus the payment terms
            foreach (DB::all("SELECT b.id, b.number, b.type, $due AS d, b.amount_qar - b.paid_qar AS due, p.name_en, p.name_ar FROM bills b LEFT JOIN parties p ON p.id = b.party_id
                WHERE b.deleted_at IS NULL AND b.status IN ('approved','partially_paid','overdue') AND $due <= ?", [$limit]) as $r) {
                $add('invoice', $r['name_en'] ? Notifier::msg('notify.bill_party', ['number' => $r['number'], 'name' => $name($r)]) : Notifier::msg('notify.bill', ['number' => $r['number']]),
                    Notifier::msg('notify.bill_detail', ['type' => Notifier::msg('bills.type_' . $r['type']), 'amount' => ['money' => (float) $r['due']]]), $r['d'], '/portal/bills/' . $r['id']);
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /**
     * Liquid-nitrogen tanks whose next refill (last refill + refill interval) falls on or before $until — overdue
     * ones included. A tank with no interval or never refilled has no due date. Rows: tanks columns + due_date, days.
     */
    public static function tanksDue(string $until): array
    {
        $rows = DB::all('SELECT t.*, DATE_ADD(t.last_refill_date, INTERVAL t.refill_interval_days DAY) AS due_date FROM tanks t
            WHERE t.deleted_at IS NULL AND t.last_refill_date IS NOT NULL AND t.refill_interval_days > 0
              AND DATE_ADD(t.last_refill_date, INTERVAL t.refill_interval_days DAY) <= ? ORDER BY due_date', [$until]);
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $i => $t) {
            $rows[$i]['days'] = (int) floor((strtotime($t['due_date']) - $today) / 86400);
        }
        return $rows;
    }
}
