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

    private static function profitLoss(array $per): array
    {
        $rows = DB::all("SELECT b.type, COALESCE(c.name_en, '—') AS category, c.name_ar,
              SUM(CASE WHEN b.status IN " . self::OK . " THEN b.amount_qar ELSE 0 END) AS ok,
              SUM(CASE WHEN b.status IN " . self::PEND . " THEN b.amount_qar ELSE 0 END) AS pend
            FROM bills b LEFT JOIN finance_categories c ON c.id = b.category_id
            WHERE b.deleted_at IS NULL AND b.status <> 'cancelled' AND b.type IN ('income','expense') AND b.bill_date BETWEEN ? AND ?
            GROUP BY b.type, c.id, c.name_en, c.name_ar ORDER BY b.type DESC, SUM(b.amount_qar) DESC", [$per['from'], $per['to']]);
        $cols = ['category' => ['finance.category', 'text'], 'ok' => ['finance.approved_paid', 'money'], 'pend' => ['finance.pending', 'money'], 'total' => ['common.total', 'money']];
        $sec = [];
        $tot = ['income' => ['ok' => 0.0, 'pend' => 0.0], 'expense' => ['ok' => 0.0, 'pend' => 0.0]];
        foreach (['income', 'expense'] as $t) {
            $list = [];
            foreach (array_filter($rows, fn ($r) => $r['type'] === $t) as $r) {
                $list[] = ['category' => loc(['name_en' => $r['category'], 'name_ar' => $r['name_ar']], 'name'), 'ok' => (float) $r['ok'], 'pend' => (float) $r['pend'], 'total' => (float) $r['ok'] + (float) $r['pend']];
                $tot[$t]['ok'] += (float) $r['ok'];
                $tot[$t]['pend'] += (float) $r['pend'];
            }
            $sec[] = ['title' => __('bills.type_' . $t), 'columns' => $cols, 'rows' => $list,
                      'totals' => ['category' => __('common.total'), 'ok' => $tot[$t]['ok'], 'pend' => $tot[$t]['pend'], 'total' => $tot[$t]['ok'] + $tot[$t]['pend']]];
        }
        // Month by month
        $m = DB::all("SELECT DATE_FORMAT(bill_date, '%Y-%m') AS m,
              SUM(CASE WHEN type = 'income' AND status IN " . self::OK . " THEN amount_qar ELSE 0 END) AS inc_ok,
              SUM(CASE WHEN type = 'income' AND status IN " . self::PEND . " THEN amount_qar ELSE 0 END) AS inc_pend,
              SUM(CASE WHEN type = 'expense' AND status IN " . self::OK . " THEN amount_qar ELSE 0 END) AS exp_ok,
              SUM(CASE WHEN type = 'expense' AND status IN " . self::PEND . " THEN amount_qar ELSE 0 END) AS exp_pend
            FROM bills WHERE deleted_at IS NULL AND status <> 'cancelled' AND bill_date BETWEEN ? AND ? GROUP BY m ORDER BY m", [$per['from'], $per['to']]);
        $months = [];
        foreach ($m as $r) {
            $inc = (float) $r['inc_ok'] + (float) $r['inc_pend'];
            $exp = (float) $r['exp_ok'] + (float) $r['exp_pend'];
            $months[] = ['month' => $r['m'], 'inc_ok' => (float) $r['inc_ok'], 'inc_pend' => (float) $r['inc_pend'], 'exp_ok' => (float) $r['exp_ok'], 'exp_pend' => (float) $r['exp_pend'], 'net' => round($inc - $exp, 2)];
        }
        $incT = $tot['income']['ok'] + $tot['income']['pend'];
        $expT = $tot['expense']['ok'] + $tot['expense']['pend'];
        $sec[] = ['title' => __('reports.by_month'), 'columns' => ['month' => ['common.month', 'text'], 'inc_ok' => ['reports.income_ok', 'money'], 'inc_pend' => ['reports.income_pending', 'money'],
                  'exp_ok' => ['reports.expense_ok', 'money'], 'exp_pend' => ['reports.expense_pending', 'money'], 'net' => ['reports.net', 'money']], 'rows' => $months,
                  'totals' => ['month' => __('common.total'), 'inc_ok' => $tot['income']['ok'], 'inc_pend' => $tot['income']['pend'], 'exp_ok' => $tot['expense']['ok'], 'exp_pend' => $tot['expense']['pend'], 'net' => round($incT - $expT, 2)]];
        return ['cards' => [
            [__('reports.total_income'), $incT, __('finance.approved_paid') . ' ' . money($tot['income']['ok'], 'QAR', false) . ' · ' . __('finance.pending') . ' ' . money($tot['income']['pend'], 'QAR', false)],
            [__('reports.total_expenses'), $expT, __('finance.approved_paid') . ' ' . money($tot['expense']['ok'], 'QAR', false) . ' · ' . __('finance.pending') . ' ' . money($tot['expense']['pend'], 'QAR', false)],
            [__('reports.net'), round($incT - $expT, 2), __('reports.net_help')],
            [__('reports.net_approved'), round($tot['income']['ok'] - $tot['expense']['ok'], 2), __('reports.net_approved_help')],
        ], 'sections' => $sec];
    }

    private static function expenses(array $per, string $by): array
    {
        [$group, $label, $join] = match ($by) {
            'supplier' => ['b.party_id', "COALESCE(p.name_en, '—')", 'LEFT JOIN parties p ON p.id = b.party_id'],
            'horse'    => ['b.horse_id', "COALESCE(h.name_en, '—')", 'LEFT JOIN horses h ON h.id = b.horse_id'],
            default    => ['b.category_id', "COALESCE(c.name_en, '—')", 'LEFT JOIN finance_categories c ON c.id = b.category_id'],
        };
        $rows = DB::all("SELECT $label AS name, SUM(CASE WHEN b.status IN " . self::OK . " THEN b.amount_qar ELSE 0 END) AS ok,
              SUM(CASE WHEN b.status IN " . self::PEND . " THEN b.amount_qar ELSE 0 END) AS pend, COUNT(*) AS n
            FROM bills b $join WHERE b.deleted_at IS NULL AND b.type = 'expense' AND b.status <> 'cancelled' AND b.bill_date BETWEEN ? AND ?
            GROUP BY $group, name ORDER BY SUM(b.amount_qar) DESC", [$per['from'], $per['to']]);
        $all = array_sum(array_map(fn ($r) => (float) $r['ok'] + (float) $r['pend'], $rows)) ?: 1;
        $list = array_map(fn ($r) => ['name' => $r['name'], 'n' => (int) $r['n'], 'ok' => (float) $r['ok'], 'pend' => (float) $r['pend'],
                                      'total' => (float) $r['ok'] + (float) $r['pend'], 'share' => round(((float) $r['ok'] + (float) $r['pend']) / $all * 100, 1)], $rows);
        $sum = fn ($k) => array_sum(array_column($list, $k));
        return ['by' => $by, 'cards' => [], 'sections' => [[
            'title' => __('reports.by_' . (in_array($by, ['supplier', 'horse'], true) ? $by : 'category')),
            'columns' => ['name' => [$by === 'supplier' ? 'po.supplier' : ($by === 'horse' ? 'bills.horse' : 'finance.category'), 'text'], 'n' => ['nav.bills', 'num'],
                          'ok' => ['finance.approved_paid', 'money'], 'pend' => ['finance.pending', 'money'], 'total' => ['common.total', 'money'], 'share' => ['reports.share', 'pct']],
            'rows' => $list, 'totals' => ['name' => __('common.total'), 'n' => $sum('n'), 'ok' => $sum('ok'), 'pend' => $sum('pend'), 'total' => $sum('total'), 'share' => $list ? 100 : 0],
        ]]];
    }

    private static function horses(array $per): array
    {
        $p = [$per['from'], $per['to']];
        $rows = DB::all("SELECT h.id, h.name_en,
              COALESCE((SELECT SUM(b.amount_qar) FROM bills b WHERE b.horse_id = h.id AND b.type = 'expense' AND b.status <> 'cancelled' AND b.deleted_at IS NULL AND b.bill_date BETWEEN ? AND ?), 0) AS bills,
              COALESCE((SELECT SUM(m.total_qar) FROM stock_movements m WHERE m.horse_id = h.id AND m.direction IN ('out','adjust') AND m.movement_date BETWEEN ? AND ?), 0) AS stock,
              COALESCE((SELECT SUM(b.amount_qar) FROM bills b WHERE b.horse_id = h.id AND b.type = 'income' AND b.status <> 'cancelled' AND b.deleted_at IS NULL AND b.bill_date BETWEEN ? AND ?), 0) AS income
            FROM horses h WHERE h.deleted_at IS NULL AND h.is_external = 0 HAVING bills + stock + income <> 0 ORDER BY (bills + stock) DESC", array_merge($p, $p, $p));
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

    /** Unpaid approved amounts by how many days they are past their due date. */
    private static function aging(): array
    {
        $bucket = "CASE WHEN b.due_date IS NULL OR b.due_date >= CURDATE() THEN 'current'
                        WHEN DATEDIFF(CURDATE(), b.due_date) <= 30 THEN 'd30' WHEN DATEDIFF(CURDATE(), b.due_date) <= 60 THEN 'd60'
                        WHEN DATEDIFF(CURDATE(), b.due_date) <= 90 THEN 'd90' ELSE 'd90p' END";
        $sec = [];
        $cards = [];
        foreach (['receivable' => "b.type = 'income'", 'payable' => "b.type <> 'income'"] as $k => $cond) {
            $rows = DB::all("SELECT COALESCE(p.name_en, '—') AS name, $bucket AS bk, SUM(b.amount_qar - b.paid_qar) AS due
                FROM bills b LEFT JOIN parties p ON p.id = b.party_id WHERE b.deleted_at IS NULL AND $cond AND b.status IN ('approved','partially_paid','overdue')
                GROUP BY b.party_id, name, bk");
            $by = [];
            foreach ($rows as $r) {
                $by[$r['name']] ??= ['name' => $r['name'], 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90p' => 0.0, 'total' => 0.0];
                $by[$r['name']][$r['bk']] += (float) $r['due'];
                $by[$r['name']]['total'] += (float) $r['due'];
            }
            usort($by, fn ($a, $b) => $b['total'] <=> $a['total']);
            $sum = fn ($c) => array_sum(array_column($by, $c));
            $sec[] = ['title' => __('reports.aging_' . $k), 'columns' => ['name' => ['bills.party', 'text'], 'current' => ['reports.not_due', 'money'], 'd30' => ['reports.d30', 'money'],
                      'd60' => ['reports.d60', 'money'], 'd90' => ['reports.d90', 'money'], 'd90p' => ['reports.d90p', 'money'], 'total' => ['common.total', 'money']],
                      'rows' => array_values($by), 'totals' => ['name' => __('common.total'), 'current' => $sum('current'), 'd30' => $sum('d30'), 'd60' => $sum('d60'), 'd90' => $sum('d90'), 'd90p' => $sum('d90p'), 'total' => $sum('total')]];
            $cards[] = [__('reports.aging_' . $k), $sum('total'), __('reports.overdue_part') . ' ' . money($sum('total') - $sum('current'), 'QAR', false)];
        }
        return ['cards' => $cards, 'sections' => $sec, 'no_period' => true];
    }

    private static function payroll(array $per): array
    {
        $from = substr($per['from'], 0, 7);
        $to = substr($per['to'], 0, 7);
        $runs = DB::all("SELECT r.period, r.status, COUNT(l.id) AS n, SUM(l.basic_qar) AS basic, SUM(l.allowances_qar) AS allow, SUM(l.overtime_qar) AS ot,
              SUM(l.deductions_qar + l.advances_qar) AS ded, SUM(l.net_qar) AS net
            FROM payroll_runs r LEFT JOIN payroll_lines l ON l.payroll_run_id = r.id WHERE r.deleted_at IS NULL AND r.period BETWEEN ? AND ? GROUP BY r.id ORDER BY r.period", [$from, $to]);
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
