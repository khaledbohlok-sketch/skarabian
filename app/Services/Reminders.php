<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Everything coming up or overdue, gathered from the records the user may see: staff documents, vet and
 * farrier due dates, foalings and unpaid bills. Used by the Studio "Reminders" sheet and the daily summary.
 * Each item: cat, title, detail, date, days (from today, negative = overdue), status (over|soon), url.
 */
final class Reminders
{
    public static function all(int $window = 60, bool $all = false): array
    {
        $can = fn (string $m) => $all || Auth::can($m);
        $limit = date('Y-m-d', strtotime("+$window days"));
        $out = [];
        $add = function (string $cat, string $title, ?string $detail, string $date, ?string $url) use (&$out) {
            $days = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
            $out[] = ['cat' => $cat, 'title' => $title, 'detail' => $detail, 'date' => $date, 'days' => $days, 'status' => $days < 0 ? 'over' : 'soon', 'url' => $url];
        };
        if ($can('hr')) {
            $docs = ['qid_expiry' => 'Qatar ID', 'passport_expiry' => 'Passport', 'visa_expiry' => 'Visa / residence permit', 'health_card_expiry' => 'Health card'];
            foreach ($docs as $col => $label) {
                foreach (DB::all("SELECT id, name_en, $col AS d FROM employees WHERE deleted_at IS NULL AND status <> 'left' AND $col IS NOT NULL AND $col <= ?", [$limit]) as $r) {
                    $add('qid', $r['name_en'] . ' · ' . $label . ($r['d'] < date('Y-m-d') ? ' expired' : ' expires'), null, $r['d'], '/portal/employees/' . $r['id']);
                }
            }
        }
        if ($can('horse_health')) {
            foreach (DB::all("SELECT r.id, r.type, r.title, r.next_due_date AS d, h.name_en FROM health_records r JOIN horses h ON h.id = r.horse_id
                WHERE r.deleted_at IS NULL AND h.deleted_at IS NULL AND h.status <> 'deceased' AND r.next_due_date IS NOT NULL AND r.next_due_date <= ?
                AND NOT EXISTS (SELECT 1 FROM health_records n WHERE n.horse_id = r.horse_id AND n.type = r.type AND n.record_date >= r.next_due_date AND n.deleted_at IS NULL)", [$limit]) as $r) {
                $add('vet', $r['name_en'] . ' · ' . __('health.type_' . $r['type']), $r['title'], $r['d'], '/portal/health-records/' . $r['id']);
            }
        }
        if ($can('horse_breeding')) {
            foreach (DB::all("SELECT b.id, b.expected_foaling_date AS d, m.name_en FROM breeding_records b JOIN horses m ON m.id = b.mare_id
                WHERE b.deleted_at IS NULL AND b.status = 'pregnant' AND b.expected_foaling_date IS NOT NULL AND b.expected_foaling_date <= ?", [$limit]) as $r) {
                $add('foal', $r['name_en'] . ' · ' . __('breeding.foaling_due'), null, $r['d'], '/portal/breeding-records/' . $r['id']);
            }
        }
        if ($can('finance')) {
            foreach (DB::all("SELECT b.id, b.number, b.type, b.due_date AS d, b.amount_qar - b.paid_qar AS due, p.name_en FROM bills b LEFT JOIN parties p ON p.id = b.party_id
                WHERE b.deleted_at IS NULL AND b.status IN ('approved','partially_paid','overdue') AND b.due_date IS NOT NULL AND b.due_date <= ?", [$limit]) as $r) {
                $add('invoice', $r['number'] . ($r['name_en'] ? ' · ' . $r['name_en'] : ''), __('bills.type_' . $r['type']) . ' · ' . money($r['due'], 'QAR', false), $r['d'], '/portal/bills/' . $r['id']);
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }
}
