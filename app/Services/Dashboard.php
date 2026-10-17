<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Dashboard figures. Every widget is computed only when the user's role may see that module.
 * Money figures ALWAYS separate approved/paid from pending (the old system counted only paid bills),
 * and net = income − expenses (negative when below zero).
 */
final class Dashboard
{
    public const APPROVED = "('approved','partially_paid','paid','overdue')";
    public const PENDING = "('draft','pending')";

    public static function horses(): array
    {
        return Cache::remember('dash.horses', 300, function () {
            $cats = DB::pairs("SELECT category, COUNT(*) FROM horses WHERE deleted_at IS NULL AND is_external = 0 AND archived_at IS NULL AND status IN ('active','in_shelter') GROUP BY category");
            return [
                'cats' => $cats, 'total' => array_sum($cats),
                'pregnant' => (int) DB::value("SELECT COUNT(DISTINCT mare_id) FROM breeding_records WHERE status = 'pregnant' AND deleted_at IS NULL"),
                'foaling30' => DB::all("SELECT b.id, b.expected_foaling_date, m.id AS mare_id, m.name_en, m.name_ar FROM breeding_records b JOIN horses m ON m.id = b.mare_id
                    WHERE b.status = 'pregnant' AND b.deleted_at IS NULL AND b.expected_foaling_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY b.expected_foaling_date LIMIT 10"),
            ];
        });
    }

    /** Pregnancy checks (14 / 30 / 45 / 60 days after cover or transfer) overdue or due within 3 days, not yet recorded. */
    public static function pregnancyChecks(): array
    {
        return array_slice(HorseService::pregnancyChecksDue(date('Y-m-d', strtotime('+3 days'))), 0, 10);
    }

    /** Nitrogen tanks whose refill is overdue or due within 3 days. */
    public static function tanksDue(): array
    {
        return Reminders::tanksDue(date('Y-m-d', strtotime('+3 days')));
    }

    public static function healthDue(): array
    {
        $params = [];
        $scope = '';
        if (Auth::horseScopeAssigned()) {
            $scope = ' AND r.horse_id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 's', $params);
        }
        // Only the latest record of each type per horse counts (a newer vaccination replaces the old due date)
        return DB::all("SELECT r.id, r.type, r.title, r.next_due_date, h.id AS horse_id, h.name_en, h.name_ar FROM health_records r JOIN horses h ON h.id = r.horse_id
            WHERE r.deleted_at IS NULL AND r.next_due_date IS NOT NULL AND r.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
              AND h.deleted_at IS NULL AND h.status IN ('active','in_shelter')
              AND NOT EXISTS (SELECT 1 FROM health_records n WHERE n.horse_id = r.horse_id AND n.type = r.type AND n.deleted_at IS NULL AND n.record_date > r.record_date)
              $scope ORDER BY r.next_due_date LIMIT 15", $params);
    }

    public static function embryos(): array
    {
        return Cache::remember('dash.embryos', 300, fn () => DB::pairs('SELECT status, COUNT(*) FROM embryos WHERE deleted_at IS NULL AND archived_at IS NULL GROUP BY status'));
    }

    /** QID / passport / visa / health card: expired and expiring within 60 days are separate counts. */
    public static function documents(): array
    {
        return Cache::remember('dash.docs', 300, function () {
            $out = ['expired' => [], 'soon' => []];
            foreach (['qid_expiry' => 'hr.qid', 'passport_expiry' => 'hr.passport', 'visa_expiry' => 'hr.visa', 'health_card_expiry' => 'hr.health_card'] as $col => $label) {
                foreach (DB::all("SELECT id, name_en, $col AS d FROM employees WHERE deleted_at IS NULL AND status <> 'left' AND $col IS NOT NULL AND $col <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY $col") as $r) {
                    $out[$r['d'] < date('Y-m-d') ? 'expired' : 'soon'][] = ['id' => $r['id'], 'name' => $r['name_en'], 'doc' => $label, 'date' => $r['d']];
                }
            }
            return $out;
        });
    }

    public static function employees(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL AND status IN ('active','on_leave')");
    }

    /** Income and expenses for a period, approved and pending kept separate. */
    /** This period's income and expenses as paid / approved-unpaid / pending approval / draft (drafts not in the totals). */
    public static function money(string $from, string $to): array
    {
        $r = DB::row('SELECT ' . FinanceService::bucketSums('bills', "bills.type = 'income'", '_inc') . ', '
            . FinanceService::bucketSums('bills', "bills.type = 'expense'", '_exp')
            . ' FROM bills WHERE deleted_at IS NULL AND bill_date BETWEEN ? AND ?', [$from, $to]) ?? [];
        $inc = FinanceService::buckets($r, '_inc');
        $exp = FinanceService::buckets($r, '_exp');
        return ['inc' => $inc, 'exp' => $exp, 'net' => round($inc['total'] - $exp['total'], 2)];
    }

    public static function bills(): array
    {
        $over = DB::row("SELECT COUNT(*) AS n, COALESCE(SUM(amount_qar - paid_qar),0) AS total FROM bills WHERE deleted_at IS NULL AND status = 'overdue'") ?? [];
        return [
            'pending' => (int) DB::value("SELECT COUNT(*) FROM bills WHERE deleted_at IS NULL AND status = 'pending'"),
            // due date as the reports count it: the bill's own, else the bill date plus the payment terms
            'overdue' => DB::all("SELECT b.id, b.number, b.description, b.amount_qar - b.paid_qar AS due, " . FinanceService::dueSql('b', 'p') . " AS due_date, p.name_en AS party
                FROM bills b LEFT JOIN parties p ON p.id = b.party_id WHERE b.deleted_at IS NULL AND b.status = 'overdue' ORDER BY due_date LIMIT 8"),
            'overdue_count' => (int) ($over['n'] ?? 0), // all of them ('overdue' lists the first 8)
            'overdue_total' => (float) ($over['total'] ?? 0),
        ];
    }

    /** Cash position per active account = opening balance + money received − money paid ± transfers. */
    public static function cash(): array
    {
        return FinanceService::accountBalances(null, true);
    }

    public static function inventory(): array
    {
        return Cache::remember('dash.inv', 300, fn () => [
            'low' => DB::all('SELECT i.id, i.name_en, i.name_ar, i.quantity, i.min_quantity, i.unit FROM inventory_items i WHERE i.deleted_at IS NULL AND i.min_quantity > 0 AND i.quantity <= i.min_quantity ORDER BY i.quantity / i.min_quantity LIMIT 10'),
            'expiring' => DB::all('SELECT i.id, i.name_en, i.name_ar, i.expiry_date FROM inventory_items i WHERE i.deleted_at IS NULL AND i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND i.quantity > 0 ORDER BY i.expiry_date LIMIT 10'),
            'stores' => (int) DB::value('SELECT COUNT(*) FROM inventories WHERE deleted_at IS NULL'),
            'items' => (int) DB::value('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL'),
        ]);
    }

    /** Last 12 months income vs expenses (approved + pending). */
    public static function monthly(): array
    {
        return Cache::remember('dash.monthly', 600, function () {
            $start = date('Y-m-01', strtotime('-11 months', strtotime(date('Y-m-01')))); // from the 1st: on the 31st "-11 months" can skip a month
            $rows = DB::all("SELECT DATE_FORMAT(bill_date, '%Y-%m') AS m, type, SUM(amount_qar) AS total FROM bills
                WHERE deleted_at IS NULL AND status NOT IN ('cancelled','draft') AND bill_date >= ? AND type IN ('income','expense') GROUP BY m, type", [$start]);
            $out = [];
            for ($i = 11; $i >= 0; $i--) {
                $out[date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))))] = ['income' => 0.0, 'expense' => 0.0];
            }
            foreach ($rows as $r) {
                if (isset($out[$r['m']])) {
                    $out[$r['m']][$r['type']] = (float) $r['total'];
                }
            }
            return $out;
        });
    }

    public static function topCategories(string $from, string $to): array
    {
        return DB::all("SELECT COALESCE(c.name_en, '—') AS name, c.name_ar, SUM(b.amount_qar) AS total FROM bills b LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.deleted_at IS NULL AND b.type = 'expense' AND b.status NOT IN ('cancelled','draft') AND b.bill_date BETWEEN ? AND ? GROUP BY c.id, c.name_en, c.name_ar ORDER BY total DESC LIMIT 6", [$from, $to]);
    }

    public static function costPerHorse(string $from, string $to): array
    {
        // filtered outside (not with HAVING without GROUP BY): MySQL 8's ONLY_FULL_GROUP_BY can refuse that form
        return DB::all("SELECT * FROM (SELECT h.id, h.name_en, h.name_ar,
              COALESCE((SELECT SUM(b.amount_qar) FROM bills b WHERE b.horse_id = h.id AND b.type = 'expense' AND b.status NOT IN ('cancelled','draft') AND b.deleted_at IS NULL AND b.bill_date BETWEEN ? AND ?), 0)
            + COALESCE((SELECT SUM(m.total_qar) FROM stock_movements m WHERE m.horse_id = h.id AND m.direction IN ('out','adjust') AND m.movement_date BETWEEN ? AND ?), 0) AS total
            FROM horses h WHERE h.deleted_at IS NULL AND h.is_external = 0) x WHERE x.total > 0 ORDER BY x.total DESC LIMIT 8", [$from, $to, $from, $to]);
    }

    public static function budgetsExceeded(): array
    {
        $y = (int) date('Y');
        $m = (int) date('n');
        // compared outside (not with HAVING without GROUP BY): MySQL 8's ONLY_FULL_GROUP_BY can refuse that form
        return DB::all("SELECT * FROM (SELECT c.name_en, c.name_ar, bu.amount_qar, bu.period_type,
              (SELECT COALESCE(SUM(b.amount_qar),0) FROM bills b WHERE (b.category_id = bu.category_id OR b.subcategory_id = bu.category_id) AND b.type = 'expense' AND b.status NOT IN ('cancelled','draft') AND b.deleted_at IS NULL
                 AND YEAR(b.bill_date) = bu.year AND (bu.period_type = 'year' OR MONTH(b.bill_date) = bu.month)) AS spent
            FROM budgets bu JOIN finance_categories c ON c.id = bu.category_id
            WHERE bu.deleted_at IS NULL AND bu.year = ? AND (bu.period_type = 'year' OR bu.month = ?)) x WHERE x.spent > x.amount_qar", [$y, $m]);
    }

    public static function semen(): array
    {
        return Cache::remember('dash.semen', 300, fn () => [
            'doses' => SemenStockService::totalDoses(),
            'low' => SemenStockService::lowStockCount(),
        ]);
    }

    public static function payrollDue(): ?array
    {
        $period = date('Y-m');
        $run = DB::row('SELECT * FROM payroll_runs WHERE period = ? AND deleted_at IS NULL', [$period]);
        $estimate = (float) DB::value("SELECT COALESCE(SUM(COALESCE(basic_salary_qar,0) + COALESCE(housing_allowance,0) + COALESCE(transport_allowance,0) + COALESCE(other_allowance,0)),0) FROM employees WHERE deleted_at IS NULL AND status IN ('active','on_leave')");
        return ['period' => $period, 'run' => $run, 'estimate' => $estimate];
    }
}
