<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Finance reports. Every money report shows Approved/Paid, Pending and Total separately (the old system counted
 * only paid bills, so months with pending bills showed QAR 0), and net = income − expenses (negative in red).
 *
 * A report is ['title', 'period', 'cards' => [[label, amount, sub]], 'sections' => [[title, columns, rows, totals]]];
 * a column is key => [label key, fmt (text|money|num|pct)].
 */
final class ReportService
{
    private const OK = Dashboard::APPROVED;
    private const PEND = Dashboard::PENDING;

    public static function types(): array
    {
        return [
            'profit-loss' => ['reports.pl', 'reports.pl_help', fn () => Auth::can('reports')],
            'expenses'    => ['reports.expenses', 'reports.expenses_help', fn () => Auth::can('reports')],
            'horses'      => ['reports.horses', 'reports.horses_help', fn () => Auth::can('reports') && Auth::can('finance', 'sensitive')],
            'cash-flow'   => ['reports.cash', 'reports.cash_help', fn () => Auth::can('reports') && Auth::can('finance', 'sensitive')],
            'aging'       => ['reports.aging', 'reports.aging_help', fn () => Auth::can('reports')],
            'payroll'     => ['reports.payroll', 'reports.payroll_help', fn () => Auth::can('reports') && Auth::can('payroll', 'sensitive')],
        ];
    }

    /** Period from presets (this month, last month, this quarter, this year, last year) or custom dates. */
    public static function period(array $in): array
    {
        $p = (string) ($in['period'] ?? 'this_year');
        $y = (int) date('Y');
        [$from, $to] = match ($p) {
            'this_month'   => [date('Y-m-01'), date('Y-m-t')],
            'last_month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            'this_quarter' => [sprintf('%d-%02d-01', $y, (int) (floor(((int) date('n') - 1) / 3) * 3 + 1)), date('Y-m-t', strtotime(sprintf('%d-%02d-01', $y, (int) (floor(((int) date('n') - 1) / 3) * 3 + 3))))],
            'last_year'    => [($y - 1) . '-01-01', ($y - 1) . '-12-31'],
            'custom'       => [(string) ($in['from'] ?? ''), (string) ($in['to'] ?? '')],
            default        => [$y . '-01-01', $y . '-12-31'],
        };
        $ok = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
        if (!$ok($from) || !$ok($to) || $from > $to) {
            [$p, $from, $to] = ['this_year', $y . '-01-01', $y . '-12-31'];
        }
        return ['key' => $p, 'from' => $from, 'to' => $to];
    }

    public static function build(string $type, array $in): array
    {
        $per = self::period($in);
        $r = match ($type) {
            'profit-loss' => self::profitLoss($per),
            'expenses'    => self::expenses($per, (string) ($in['by'] ?? 'category')),
            'horses'      => self::horses($per),
            'cash-flow'   => self::cashFlow($per),
            'aging'       => self::aging(),
            'payroll'     => self::payroll($per),
        };
        return $r + ['period' => $per, 'type' => $type, 'title' => __(self::types()[$type][0])];
    }

    /** Columns for the four money figures; income says "received" where expenses say "paid". */
    private static function bucketColumns(bool $income): array
    {
        return ['paid' => [$income ? 'finance.b_received' : 'finance.b_paid', 'money'], 'unpaid' => [$income ? 'finance.b_not_received' : 'finance.b_unpaid', 'money'],
                'pending' => ['finance.b_pending', 'money'], 'total' => ['common.total', 'money']];
    }

    /** "Paid 1,000 · Unpaid 2,000 · Pending approval 0" for a report card. */
    private static function bucketLine(array $f, bool $income): string
    {
        return __($income ? 'finance.b_received' : 'finance.b_paid') . ' ' . money($f['paid'], 'QAR', false)
            . ' · ' . __($income ? 'finance.b_not_received' : 'finance.b_unpaid') . ' ' . money($f['unpaid'], 'QAR', false)
            . ' · ' . __('finance.b_pending') . ' ' . money($f['pending'], 'QAR', false);
    }

    /** Drafts are left out of every total: the section says how many and how much. */
    private static function draftNote(float $draft, int $n): ?string
    {
        return $draft > 0 ? __('finance.drafts_not_counted', ['n' => $n, 'amount' => money($draft, 'QAR', false)]) : null;
    }

    private static function profitLoss(array $per): array
    {
        $rows = DB::all("SELECT b.type, COALESCE(c.name_en, '—') AS category, c.name_ar, " . FinanceService::bucketSums('b') . ",
              SUM(b.status = 'draft') AS drafts
            FROM bills b LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.deleted_at IS NULL AND b.status <> 'cancelled' AND b.type IN ('income','expense') AND b.bill_date BETWEEN ? AND ?
            GROUP BY b.type, c.id, c.name_en, c.name_ar ORDER BY b.type DESC, SUM(b.amount_qar) DESC", [$per['from'], $per['to']]);
        $sec = [];
        $zero = ['paid' => 0.0, 'unpaid' => 0.0, 'pending' => 0.0, 'draft' => 0.0, 'total' => 0.0, 'drafts' => 0];
        $tot = ['income' => $zero, 'expense' => $zero];
        foreach (['income', 'expense'] as $t) {
            $list = [];
            foreach (array_filter($rows, fn ($r) => $r['type'] === $t) as $r) {
                $f = FinanceService::buckets($r);
                foreach ($f as $k => $v) {
                    $tot[$t][$k] += $v;
                }
                $tot[$t]['drafts'] += (int) $r['drafts'];
                if ($f['total'] != 0) { // a category with only drafts has nothing to count
                    $list[] = ['category' => loc(['name_en' => $r['category'], 'name_ar' => $r['name_ar']], 'name')] + $f;
                }
            }
            $sec[] = ['title' => __('bills.type_' . $t), 'columns' => ['category' => ['finance.category', 'text']] + self::bucketColumns($t === 'income'), 'rows' => $list,
                      'totals' => ['category' => __('common.total')] + $tot[$t], 'note' => self::draftNote($tot[$t]['draft'], $tot[$t]['drafts'])];
        }
        // Month by month (approved = paid + unpaid; drafts left out)
        $m = DB::all("SELECT DATE_FORMAT(b.bill_date, '%Y-%m') AS m, " . FinanceService::bucketSums('b', "b.type = 'income'", '_inc') . ', '
            . FinanceService::bucketSums('b', "b.type = 'expense'", '_exp') . "
            FROM bills b WHERE b.deleted_at IS NULL AND b.status <> 'cancelled' AND b.bill_date BETWEEN ? AND ? GROUP BY m ORDER BY m", [$per['from'], $per['to']]);
        $months = [];
        foreach ($m as $r) {
            $inc = FinanceService::buckets($r, '_inc');
            $exp = FinanceService::buckets($r, '_exp');
            $months[] = ['month' => $r['m'], 'inc_ok' => $inc['paid'] + $inc['unpaid'], 'inc_pend' => $inc['pending'], 'exp_ok' => $exp['paid'] + $exp['unpaid'],
                         'exp_pend' => $exp['pending'], 'net' => round($inc['total'] - $exp['total'], 2)];
        }
        $i = $tot['income'];
        $x = $tot['expense'];
        $sec[] = ['title' => __('reports.by_month'), 'columns' => ['month' => ['common.month', 'text'], 'inc_ok' => ['reports.income_ok', 'money'], 'inc_pend' => ['reports.income_pending', 'money'],
                  'exp_ok' => ['reports.expense_ok', 'money'], 'exp_pend' => ['reports.expense_pending', 'money'], 'net' => ['reports.net', 'money']], 'rows' => $months,
                  'totals' => ['month' => __('common.total'), 'inc_ok' => $i['paid'] + $i['unpaid'], 'inc_pend' => $i['pending'], 'exp_ok' => $x['paid'] + $x['unpaid'],
                               'exp_pend' => $x['pending'], 'net' => round($i['total'] - $x['total'], 2)]];
        return ['cards' => [
            [__('reports.total_income'), $i['total'], self::bucketLine($i, true)],
            [__('reports.total_expenses'), $x['total'], self::bucketLine($x, false)],
            [__('reports.net'), round($i['total'] - $x['total'], 2), __('reports.net_help')],
            [__('reports.net_approved'), round($i['paid'] + $i['unpaid'] - $x['paid'] - $x['unpaid'], 2), __('reports.net_approved_help')],
        ], 'sections' => $sec];
    }

    private static function expenses(array $per, string $by): array
    {
        [$group, $label, $join] = match ($by) {
            'supplier' => ['b.party_id', "COALESCE(p.name_en, '—')", 'LEFT JOIN parties p ON p.id = b.party_id'],
            'horse'    => ['b.horse_id', "COALESCE(h.name_en, '—')", 'LEFT JOIN horses h ON h.id = b.horse_id'],
            default    => ['b.category_id', "COALESCE(c.name_en, '—')", 'LEFT JOIN finance_categories c ON c.id = b.category_id'],
        };
        $rows = DB::all("SELECT $label AS name, " . FinanceService::bucketSums('b') . ", SUM(b.status <> 'draft') AS n, SUM(b.status = 'draft') AS drafts
            FROM bills b $join WHERE b.deleted_at IS NULL AND b.type = 'expense' AND b.status <> 'cancelled' AND b.bill_date BETWEEN ? AND ?
            GROUP BY $group, name ORDER BY SUM(b.amount_qar) DESC", [$per['from'], $per['to']]);
        $draft = array_sum(array_map(fn ($r) => (float) $r['draft'], $rows));
        $drafts = array_sum(array_map(fn ($r) => (int) $r['drafts'], $rows));
        $rows = array_filter($rows, fn ($r) => FinanceService::buckets($r)['total'] != 0); // only drafts: nothing to count
        $all = array_sum(array_map(fn ($r) => FinanceService::buckets($r)['total'], $rows)) ?: 1;
        $list = array_values(array_map(function ($r) use ($all) {
            $f = FinanceService::buckets($r);
            return ['name' => $r['name'], 'n' => (int) $r['n']] + $f + ['share' => round($f['total'] / $all * 100, 1)];
        }, $rows));
        $sum = fn ($k) => array_sum(array_column($list, $k));
        return ['by' => $by, 'cards' => [], 'sections' => [[
            'title' => __('reports.by_' . (in_array($by, ['supplier', 'horse'], true) ? $by : 'category')),
            'columns' => ['name' => [$by === 'supplier' ? 'po.supplier' : ($by === 'horse' ? 'bills.horse' : 'finance.category'), 'text'], 'n' => ['nav.bills', 'num']]
                + self::bucketColumns(false) + ['share' => ['reports.share', 'pct']],
            'rows' => $list, 'totals' => ['name' => __('common.total'), 'n' => $sum('n'), 'paid' => $sum('paid'), 'unpaid' => $sum('unpaid'), 'pending' => $sum('pending'),
                                          'total' => $sum('total'), 'share' => $list ? 100 : 0],
            'note' => self::draftNote($draft, $drafts),
        ]]];
    }

    private static function horses(array $per): array
    {
        $p = [$per['from'], $per['to']];
        // filtered outside (not with HAVING without GROUP BY): MySQL 8's ONLY_FULL_GROUP_BY can refuse that form
        $rows = DB::all("SELECT * FROM (SELECT h.id, h.name_en,
              COALESCE((SELECT SUM(b.amount_qar) FROM bills b WHERE b.horse_id = h.id AND b.type = 'expense' AND b.status NOT IN ('cancelled','draft') AND b.deleted_at IS NULL AND b.bill_date BETWEEN ? AND ?), 0) AS bills,
              COALESCE((SELECT SUM(m.total_qar) FROM stock_movements m WHERE m.horse_id = h.id AND m.direction IN ('out','adjust') AND m.movement_date BETWEEN ? AND ?), 0) AS stock,
              COALESCE((SELECT SUM(b.amount_qar) FROM bills b WHERE b.horse_id = h.id AND b.type = 'income' AND b.status NOT IN ('cancelled','draft') AND b.deleted_at IS NULL AND b.bill_date BETWEEN ? AND ?), 0) AS income
            FROM horses h WHERE h.deleted_at IS NULL AND h.is_external = 0) x WHERE x.bills + x.stock + x.income <> 0 ORDER BY (x.bills + x.stock) DESC", array_merge($p, $p, $p));
        $list = array_map(fn ($r) => ['name' => $r['name_en'], 'bills' => (float) $r['bills'], 'stock' => (float) $r['stock'], 'cost' => (float) $r['bills'] + (float) $r['stock'],
                                      'income' => (float) $r['income'], 'net' => round((float) $r['income'] - (float) $r['bills'] - (float) $r['stock'], 2)], $rows);
        $sum = fn ($k) => array_sum(array_column($list, $k));
        return ['cards' => [], 'sections' => [[
            'title' => __('reports.horses'), 'note' => __('reports.horses_note'),
            'columns' => ['name' => ['bills.horse', 'text'], 'bills' => ['reports.horse_bills', 'money'], 'stock' => ['reports.horse_stock', 'money'], 'cost' => ['reports.horse_cost', 'money'],
                          'income' => ['bills.type_income', 'money'], 'net' => ['reports.net', 'money']],
            'rows' => $list, 'totals' => ['name' => __('common.total'), 'bills' => $sum('bills'), 'stock' => $sum('stock'), 'cost' => $sum('cost'), 'income' => $sum('income'), 'net' => $sum('net')],
        ]]];
    }

    private static function cashFlow(array $per): array
    {
        $rows = DB::all("SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS m,
              SUM(CASE WHEN b.type = 'income' THEN p.amount_qar ELSE 0 END) AS cin,
              SUM(CASE WHEN b.type <> 'income' THEN p.amount_qar ELSE 0 END) AS cout
            FROM bill_payments p JOIN bills b ON b.id = p.bill_id WHERE p.deleted_at IS NULL AND p.payment_date BETWEEN ? AND ? GROUP BY m ORDER BY m", [$per['from'], $per['to']]);
        $list = array_map(fn ($r) => ['month' => $r['m'], 'in' => (float) $r['cin'], 'out' => (float) $r['cout'], 'net' => round((float) $r['cin'] - (float) $r['cout'], 2)], $rows);
        $sum = fn ($k) => array_sum(array_column($list, $k));
        $acc = array_map(fn ($a) => ['name' => $a['name'], 'type' => __('accounts.type_' . $a['type']), 'balance' => (float) $a['balance']], FinanceService::accountBalances($per['to']));
        return ['cards' => [[__('reports.money_in'), $sum('in'), ''], [__('reports.money_out'), $sum('out'), ''], [__('reports.net'), round($sum('in') - $sum('out'), 2), ''],
                            [__('reports.cash_end'), array_sum(array_column($acc, 'balance')), __('reports.cash_end_help', ['date' => fmt_date($per['to'])])]],
            'sections' => [
                ['title' => __('reports.by_month'), 'columns' => ['month' => ['common.month', 'text'], 'in' => ['reports.money_in', 'money'], 'out' => ['reports.money_out', 'money'], 'net' => ['reports.net', 'money']],
                 'rows' => $list, 'totals' => ['month' => __('common.total'), 'in' => $sum('in'), 'out' => $sum('out'), 'net' => round($sum('in') - $sum('out'), 2)]],
                ['title' => __('reports.balances_on', ['date' => fmt_date($per['to'])]), 'columns' => ['name' => ['accounts.name', 'text'], 'type' => ['common.type', 'text'], 'balance' => ['accounts.balance', 'money']],
                 'rows' => $acc, 'totals' => ['name' => __('common.total'), 'type' => '', 'balance' => array_sum(array_column($acc, 'balance'))]],
            ]];
    }

    /**
     * Unpaid approved amounts by how many days they are past their due date. A bill without a due date counts from its
     * bill date plus the client's / supplier's payment terms (from the bill date when it has none).
     */
    private static function aging(): array
    {
        $due = FinanceService::dueSql('b', 'p');
        $bucket = "CASE WHEN $due >= CURDATE() THEN 'current'
                        WHEN DATEDIFF(CURDATE(), $due) <= 30 THEN 'd30' WHEN DATEDIFF(CURDATE(), $due) <= 60 THEN 'd60'
                        WHEN DATEDIFF(CURDATE(), $due) <= 90 THEN 'd90' ELSE 'd90p' END";
        $sec = [];
        $cards = [];
        foreach (['receivable' => "b.type = 'income'", 'payable' => "b.type <> 'income'"] as $k => $cond) {
            // One row per party; a bill without a party that belongs to an employee (salaries) is owed to that employee
            $rows = DB::all("SELECT CASE WHEN p.id IS NOT NULL THEN CONCAT('p', p.id) WHEN e.id IS NOT NULL THEN CONCAT('e', e.id) ELSE '-' END AS k,
                    COALESCE(p.name_en, e.name_en, '—') AS name, $bucket AS bk, SUM(b.amount_qar - b.paid_qar) AS due
                FROM bills b LEFT JOIN parties p ON p.id = b.party_id LEFT JOIN employees e ON e.id = b.employee_id
                WHERE b.deleted_at IS NULL AND $cond AND b.status IN ('approved','partially_paid','overdue')
                GROUP BY k, name, bk");
            $by = [];
            foreach ($rows as $r) {
                $by[$r['k']] ??= ['name' => $r['name'], 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90p' => 0.0, 'total' => 0.0];
                $by[$r['k']][$r['bk']] += (float) $r['due'];
                $by[$r['k']]['total'] += (float) $r['due'];
            }
            // Two different parties (or a party and an employee) with the same name stay apart and are told apart
            $names = array_count_values(array_column($by, 'name'));
            foreach ($by as $key => &$row) {
                if ($names[$row['name']] > 1 && $key !== '-') {
                    $row['name'] .= ' (' . ($key[0] === 'e' ? __('employees.singular') . ' ' : '') . '#' . substr((string) $key, 1) . ')';
                }
            }
            unset($row);
            usort($by, fn ($a, $b) => $b['total'] <=> $a['total']);
            $sum = fn ($c) => array_sum(array_column($by, $c));
            $sec[] = ['title' => __('reports.aging_' . $k), 'columns' => ['name' => ['bills.party', 'text'], 'current' => ['reports.not_due', 'money'], 'd30' => ['reports.d30', 'money'],
                      'd60' => ['reports.d60', 'money'], 'd90' => ['reports.d90', 'money'], 'd90p' => ['reports.d90p', 'money'], 'total' => ['common.total', 'money']],
                      'rows' => array_values($by), 'totals' => ['name' => __('common.total'), 'current' => $sum('current'), 'd30' => $sum('d30'), 'd60' => $sum('d60'), 'd90' => $sum('d90'), 'd90p' => $sum('d90p'), 'total' => $sum('total')]];
            $cards[] = [__('reports.aging_' . $k), $sum('total'), __('reports.overdue_part') . ' ' . money($sum('total') - $sum('current'), 'QAR', false)];
        }
        $sec[0]['note'] = __('reports.aging_note', ['days' => FinanceService::defaultTerms()]);
        return ['cards' => $cards, 'sections' => $sec, 'no_period' => true];
    }

    private static function payroll(array $per): array
    {
        $from = substr($per['from'], 0, 7);
        $to = substr($per['to'], 0, 7);
        $runs = DB::all("SELECT r.period, r.status, COUNT(l.id) AS n, SUM(l.basic_qar) AS basic, SUM(l.allowances_qar) AS allow, SUM(l.overtime_qar) AS ot,
              SUM(l.deductions_qar + l.advances_qar) AS ded, SUM(l.net_qar) AS net
            FROM payroll_runs r LEFT JOIN payroll_lines l ON l.payroll_run_id = r.id WHERE r.deleted_at IS NULL AND r.period BETWEEN ? AND ? GROUP BY r.id, r.period, r.status ORDER BY r.period", [$from, $to]);
        $list = array_map(fn ($r) => ['period' => $r['period'], 'status' => __('payroll.status_' . $r['status']), 'n' => (int) $r['n'], 'basic' => (float) $r['basic'], 'allow' => (float) $r['allow'],
                                      'ot' => (float) $r['ot'], 'ded' => (float) $r['ded'], 'net' => (float) $r['net']], $runs);
        $emps = DB::all("SELECT e.name_en, COUNT(l.id) AS n, SUM(l.net_qar) AS net FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id JOIN employees e ON e.id = l.employee_id
            WHERE r.deleted_at IS NULL AND r.status IN ('approved','paid') AND r.period BETWEEN ? AND ? GROUP BY e.id, e.name_en ORDER BY net DESC", [$from, $to]);
        $sum = fn ($rows, $k) => array_sum(array_column($rows, $k));
        $emps = array_map(fn ($r) => ['name' => $r['name_en'], 'n' => (int) $r['n'], 'net' => (float) $r['net']], $emps);
        return ['cards' => [], 'sections' => [
            ['title' => __('reports.payroll_runs'), 'columns' => ['period' => ['payroll.period', 'text'], 'status' => ['common.status', 'text'], 'n' => ['nav.employees', 'num'], 'basic' => ['employees.basic', 'money'],
             'allow' => ['payroll.allowances', 'money'], 'ot' => ['payroll.overtime', 'money'], 'ded' => ['payroll.deductions_all', 'money'], 'net' => ['payroll.net', 'money']],
             'rows' => $list, 'totals' => ['period' => __('common.total'), 'status' => '', 'n' => '', 'basic' => $sum($list, 'basic'), 'allow' => $sum($list, 'allow'), 'ot' => $sum($list, 'ot'), 'ded' => $sum($list, 'ded'), 'net' => $sum($list, 'net')]],
            ['title' => __('reports.payroll_employees'), 'columns' => ['name' => ['employees.singular', 'text'], 'n' => ['reports.months', 'num'], 'net' => ['payroll.net', 'money']],
             'rows' => $emps, 'totals' => ['name' => __('common.total'), 'n' => '', 'net' => $sum($emps, 'net')]],
        ]];
    }
}
