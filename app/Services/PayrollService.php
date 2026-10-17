<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Monthly payroll run.
 *   create   one line per active employee: basic + allowances (pro-rated for joiners/leavers), overtime from
 *            attendance (hourly = day rate / 8, paid at 125%), deductions for absent days and unpaid leave within
 *            the employment period, and the monthly repayment of advances and loans. One day rate everywhere:
 *            basic / days in the month (the same rate the pro-rating of joiners and leavers uses).
 *   submit   → Owner / General Manager approval (no self-approval)
 *   approve  → one expense bill per employee (category Salaries) + payslip in SK Arabian Studio
 *   pay      → all or selected employees: payment recorded on each bill, loan balances reduced by the line's
 *            advances (oldest loan first); the split per loan is kept in payroll_loan_allocations so a deleted
 *            salary payment gives back exactly what it took
 */
final class PayrollService
{
    public const OVERTIME_RATE = 1.25;

    public static function periodBounds(string $period): array
    {
        $start = $period . '-01';
        return [$start, date('Y-m-t', strtotime($start))];
    }

    public static function create(string $period): int
    {
        if (!preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $period)) {
            throw new \DomainException(__('payroll.bad_period'));
        }
        if (DB::value('SELECT id FROM payroll_runs WHERE period = ? AND deleted_at IS NULL', [$period])) {
            throw new \DomainException(__('payroll.exists', ['period' => $period]));
        }
        DB::run('DELETE FROM payroll_runs WHERE period = ? AND deleted_at IS NOT NULL', [$period]);
        return DB::transaction(function () use ($period) {
            $id = DB::insert('payroll_runs', ['period' => $period, 'status' => 'draft', 'created_by' => Auth::id()]);
            self::fillLines($id, $period);
            Audit::log('create', 'payroll', 'payroll', $id, null, ['period' => $period], __('payroll.run_title', ['period' => $period]));
            return $id;
        });
    }

    /** (Re)calculates every line of a draft run from the employee records, attendance, leave and loans. */
    public static function fillLines(int $runId, string $period): void
    {
        [$start, $end] = self::periodBounds($period);
        // the way each salary is paid: what was already chosen on the run, else the employee's usual way
        $methods = self::methodsOn() ? array_column(DB::all('SELECT employee_id, payment_method_id FROM payroll_lines WHERE payroll_run_id = ? AND payment_method_id IS NOT NULL', [$runId]), 'payment_method_id', 'employee_id') : null;
        DB::run('DELETE FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
        $emps = DB::all("SELECT * FROM employees WHERE deleted_at IS NULL AND (hire_date IS NULL OR hire_date <= ?) AND (end_date IS NULL OR end_date >= ?)
            AND (status IN ('active','on_leave') OR (status = 'left' AND end_date BETWEEN ? AND ?)) ORDER BY name_en", [$end, $start, $start, $end]);
        foreach ($emps as $e) {
            $line = ['payroll_run_id' => $runId, 'employee_id' => $e['id']] + self::computeLine($e, $start, $end);
            if ($methods !== null) {
                $line['payment_method_id'] = $methods[$e['id']] ?? ($e['salary_method_id'] ?: null);
            }
            DB::insert('payroll_lines', $line);
        }
        self::total($runId);
    }

    // ------------------------------------------------------------------ how each salary is paid (database update 023)

    /**
     * Each salary line with its own way of paying ("Paid by", database update 023). Switched off on 9 Oct 2026 — the Owner
     * wanted plain ticks for who got paid instead; the columns stay, unused. On again with the setting payroll.paid_by = 1.
     */
    public static function methodsOn(): bool
    {
        return Settings::get('payroll.paid_by', '0') === '1' && DB::hasColumn('payroll_lines', 'payment_method_id') && DB::hasColumn('employees', 'salary_method_id');
    }

    /** The ways that can be chosen (the "Payment methods" dropdown list): id => row with value_en / value_ar. */
    public static function methods(): array
    {
        $out = [];
        foreach (DB::all("SELECT id, value_en, value_ar FROM lookups WHERE type = 'payment_method' AND active = 1 ORDER BY sort, value_en") as $m) {
            $out[(int) $m['id']] = $m;
        }
        return $out;
    }

    /** A way's name in the interface language (or a given one); "Not set" for none. */
    public static function methodLabel(?int $id, ?string $lang = null): string
    {
        $m = $id ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$id]) : null;
        if (!$m) {
            return __('payroll.method_not_set');
        }
        return ($lang ?? lang()) === 'ar' && trim((string) $m['value_ar']) !== '' ? (string) $m['value_ar'] : (string) $m['value_en'];
    }

    /**
     * The unpaid salaries of a run grouped by the way they are paid: method id (0 = not set) => label, count, amount and
     * the account offered first (the one used last time for that way, else a cash account for cash and a bank for the rest).
     */
    public static function payGroups(array $lines): array
    {
        $last = json_decode((string) Settings::get('payroll.method_accounts', '{}'), true) ?: [];
        $groups = [];
        foreach ($lines as $l) {
            if ($l['paid_at'] || (float) $l['net_qar'] <= 0) {
                continue;
            }
            $k = (int) ($l['payment_method_id'] ?? 0);
            $groups[$k] ??= ['label' => self::methodLabel($k ?: null), 'n' => 0, 'amount' => 0.0, 'account' => null];
            $groups[$k]['n']++;
            $groups[$k]['amount'] += (float) $l['net_qar'];
        }
        foreach ($groups as $k => &$g) {
            $acc = (int) ($last[(string) $k] ?? 0);
            if (!$acc || !DB::value('SELECT 1 FROM accounts WHERE id = ? AND active = 1 AND deleted_at IS NULL', [$acc])) {
                $cash = $k && preg_match('/cash/i', (string) DB::value('SELECT value_en FROM lookups WHERE id = ?', [$k]));
                $acc = (int) DB::value("SELECT id FROM accounts WHERE active = 1 AND deleted_at IS NULL AND type " . ($cash ? "IN ('cash','petty_cash')" : "= 'bank'") . ' ORDER BY id LIMIT 1');
            }
            $g['account'] = $acc ?: null;
            $g['account_name'] = $acc ? (string) DB::value('SELECT name FROM accounts WHERE id = ?', [$acc]) : '';
        }
        unset($g);
        uksort($groups, fn ($a, $b) => ($a === 0) <=> ($b === 0) ?: $a <=> $b); // "Not set" last
        return $groups;
    }

    /** One day's pay: basic / days in that month — for pro-rating, absences, unpaid leave and overtime alike. */
    public static function dayRate(float $basic, int $daysInMonth): float
    {
        return $daysInMonth > 0 ? $basic / $daysInMonth : 0.0;
    }

    /** Calendar days two date ranges (Y-m-d, both ends included) have in common; 0 when they don't overlap. */
    public static function overlapDays(string $from1, string $to1, string $from2, string $to2): int
    {
        $from = max($from1, $from2);
        $to = min($to1, $to2);
        return $to < $from ? 0 : (int) round((strtotime($to . ' 12:00') - strtotime($from . ' 12:00')) / 86400) + 1;
    }

    public static function computeLine(array $e, string $start, string $end): array
    {
        $daysInMonth = (int) date('t', strtotime($start));
        // The part of the month the employee was employed: absences and unpaid leave only count inside it
        $from = max($start, $e['hire_date'] ?: $start);
        $to = min($end, $e['end_date'] ?: $end);
        $worked = self::overlapDays($start, $end, $from, $to);
        $factor = $worked >= $daysInMonth ? 1.0 : $worked / $daysInMonth;
        $basicFull = (float) $e['basic_salary_qar'];
        $basic = round($basicFull * $factor, 2);
        $allow = round(((float) $e['housing_allowance'] + (float) $e['transport_allowance'] + (float) $e['other_allowance']) * $factor, 2);
        $daily = self::dayRate($basicFull, $daysInMonth);
        $absent = 0;
        $unpaid = 0;
        $hours = 0.0;
        if ($worked > 0) {
            $hours = (float) DB::value('SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL', [$e['id'], $from, $to]);
            // an "absent" day inside approved leave is leave (paid, or counted once below as unpaid), not an absence
            $absent = (int) DB::value("SELECT COUNT(*) FROM attendance a WHERE a.employee_id = ? AND a.work_date BETWEEN ? AND ? AND a.status = 'absent' AND a.deleted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM leave_requests lr WHERE lr.employee_id = a.employee_id AND lr.status = 'approved' AND lr.deleted_at IS NULL
                                AND a.work_date BETWEEN lr.start_date AND lr.end_date)", [$e['id'], $from, $to]);
            // unpaid leave: as each request was saved (HR settings → paid leave types); "unpaid" type before update 020
            $unpaidSql = DB::hasColumn('leave_requests', 'is_paid') ? "COALESCE(is_paid, leave_type <> 'unpaid') = 0" : "leave_type = 'unpaid'";
            foreach (DB::all("SELECT start_date, end_date FROM leave_requests WHERE employee_id = ? AND $unpaidSql AND status = 'approved' AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?", [$e['id'], $to, $from]) as $l) {
                $unpaid += self::overlapDays($from, $to, $l['start_date'], $l['end_date']);
            }
        }
        $overtime = round($hours * $daily / 8 * self::OVERTIME_RATE, 2);
        $deductions = round(($absent + $unpaid) * $daily, 2);
        $gross = max(0.0, $basic + $allow + $overtime - $deductions);
        $advances = (float) DB::value('SELECT COALESCE(SUM(LEAST(monthly_deduction, balance_qar)), 0) FROM employee_loans WHERE employee_id = ? AND balance_qar > 0 AND issue_date <= ? AND deleted_at IS NULL', [$e['id'], $end]);
        // what is still owed, less the repayments already set aside on another month's salary not paid yet
        $reserved = (float) DB::value('SELECT COALESCE(SUM(l.advances_qar), 0) FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id
            WHERE l.employee_id = ? AND l.paid_at IS NULL AND r.deleted_at IS NULL AND r.period <> ?', [$e['id'], substr($start, 0, 7)]);
        $advances = min($advances, max(0.0, self::openLoanBalance((int) $e['id'], $end) - $reserved));
        $advances = round(min($advances, $gross), 2);
        $notes = [];
        if ($factor < 1) {
            $notes[] = __('payroll.note_days', ['n' => $worked, 'of' => $daysInMonth]);
        }
        if ($hours > 0) {
            $notes[] = __('payroll.note_overtime', ['n' => rtrim(rtrim(number_format($hours, 2), '0'), '.')]);
        }
        if ($absent) {
            $notes[] = __('payroll.note_absent', ['n' => $absent]);
        }
        if ($unpaid) {
            $notes[] = __('payroll.note_unpaid', ['n' => $unpaid]);
        }
        return ['basic_qar' => $basic, 'allowances_qar' => $allow, 'overtime_qar' => $overtime, 'deductions_qar' => $deductions, 'advances_qar' => $advances,
                'net_qar' => round($gross - $advances, 2), 'notes' => $notes ? mb_substr(implode(' · ', $notes), 0, 255) : null];
    }

    public static function total(int $runId): float
    {
        $t = round((float) DB::value('SELECT COALESCE(SUM(net_qar), 0) FROM payroll_lines WHERE payroll_run_id = ?', [$runId]), 2);
        DB::update('payroll_runs', ['total_net_qar' => $t, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $runId]);
        return $t;
    }

    /**
     * Manual changes on a draft run: overtime, deductions, advances, notes and the way each salary is paid. With
     * $remember, a way chosen on a line also becomes the employee's usual way for the next months.
     */
    public static function saveLines(int $runId, array $input, bool $remember = false): void
    {
        DB::transaction(function () use ($runId, $input, $remember) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || $run['status'] !== 'draft') {
                throw new \DomainException(__('payroll.not_draft'));
            }
            [, $end] = self::periodBounds($run['period']);
            $methodsOn = self::methodsOn();
            $valid = $methodsOn ? self::methods() : [];
            $usual = [];
            foreach (DB::all('SELECT * FROM payroll_lines WHERE payroll_run_id = ?', [$runId]) as $l) {
                $in = $input[$l['id']] ?? null;
                if (!is_array($in)) {
                    continue;
                }
                $num = function (string $k) use ($in, $l) {
                    $v = Money::parse($in[$k] ?? null);
                    if ($v !== null && $v < 0) {
                        throw new \DomainException(__('payroll.negative'));
                    }
                    return $v === null ? (float) $l[$k] : round($v, 2);
                };
                $d = ['overtime_qar' => $num('overtime_qar'), 'deductions_qar' => $num('deductions_qar'), 'advances_qar' => $num('advances_qar'),
                      'notes' => mb_substr(trim((string) ($in['notes'] ?? $l['notes'])), 0, 255) ?: null];
                // Advances repay the employee's open advances/loans: never more than what they still owe
                if ($d['advances_qar'] > 0) {
                    $owed = self::openLoanBalance((int) $l['employee_id'], $end);
                    if ($d['advances_qar'] > $owed + 0.004) {
                        throw new \DomainException(__('payroll.advances_over_balance', ['max' => money($owed, 'QAR', false)]));
                    }
                }
                $d['net_qar'] = round((float) $l['basic_qar'] + (float) $l['allowances_qar'] + $d['overtime_qar'] - $d['deductions_qar'] - $d['advances_qar'], 2);
                if ($d['net_qar'] < 0) {
                    throw new \DomainException(__('payroll.net_negative'));
                }
                if ($methodsOn && array_key_exists('payment_method_id', $in)) {
                    $m = (int) $in['payment_method_id'] ?: null;
                    // a way from the list (one no longer offered may stay on a line that already had it)
                    if ($m !== null && !isset($valid[$m]) && $m !== (int) $l['payment_method_id']) {
                        throw new \DomainException(__('payroll.bad_method'));
                    }
                    $d['payment_method_id'] = $m;
                    if ($remember && $m !== null) {
                        $usual[(int) $l['employee_id']] = $m;
                    }
                }
                DB::update('payroll_lines', $d, 'id = :id', ['id' => $l['id']]);
            }
            $t = self::total($runId);
            Audit::log('update', 'payroll', 'payroll', $runId, ['total' => $run['total_net_qar']], ['total' => $t], __('payroll.run_title', ['period' => $run['period']]));
            // "Remember for next months": the employee's usual way, recorded like any change to the employee
            foreach ($usual as $empId => $m) {
                $e = DB::row('SELECT id, name_en, salary_method_id FROM employees WHERE id = ?', [$empId]);
                if ($e && (int) $e['salary_method_id'] !== $m) {
                    DB::update('employees', ['salary_method_id' => $m, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $empId]);
                    Audit::log('update', 'hr', 'employee', $empId, ['salary_method_id' => $e['salary_method_id']], ['salary_method_id' => $m], (string) $e['name_en']);
                }
            }
        });
    }

    /** True once the month has started. A later month can be prepared as a draft, but not sent for approval, approved or paid. */
    public static function started(string $period): bool
    {
        return $period . '-01' <= date('Y-m-d');
    }

    private static function assertStarted(string $period): void
    {
        if (!self::started($period)) {
            throw new \DomainException(__('payroll.not_started', ['date' => fmt_date($period . '-01')]));
        }
    }

    public static function submit(int $runId): string
    {
        $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL', [$runId]);
        if (!$run || $run['status'] !== 'draft') {
            throw new \DomainException(__('payroll.not_draft'));
        }
        self::assertStarted($run['period']);
        $n = (int) DB::value('SELECT COUNT(*) FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
        if (!$n) {
            throw new \DomainException(__('payroll.no_lines'));
        }
        if (self::methodsOn()) {
            // a line without a way takes the employee's usual way (e.g. a run prepared before the way was set on the employee)
            DB::run('UPDATE payroll_lines l JOIN employees e ON e.id = l.employee_id SET l.payment_method_id = e.salary_method_id
                WHERE l.payroll_run_id = ? AND l.payment_method_id IS NULL AND e.salary_method_id IS NOT NULL', [$runId]);
        }
        DB::update('payroll_runs', ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $runId]);
        Approvals::request('payroll', 'payroll', $runId, __('payroll.approval_title', ['period' => $run['period'], 'n' => $n]), (float) $run['total_net_qar']);
        return 'pending';
    }

    /**
     * Approval handler: payslips are generated in SK Arabian Studio. No bill yet — a salary goes to Finance when it is
     * paid (the Owner's choice, 9 Oct 2026: paying three employees must not put all of them in Finance).
     */
    public static function approve(int $runId, array $payload, array $approval): void
    {
        $run = DB::row("SELECT * FROM payroll_runs WHERE id = ? AND status = 'pending' FOR UPDATE", [$runId]);
        if (!$run) {
            return;
        }
        self::assertStarted($run['period']); // waits for the month (the approval stays open)
        DB::update('payroll_runs', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $runId]);
        foreach (DB::all('SELECT l.* FROM payroll_lines l WHERE l.payroll_run_id = ?', [$runId]) as $l) {
            if ((float) $l['net_qar'] <= 0) {
                continue;
            }
            if (method_exists(Studio::class, 'generateFromRecord')) {
                $doc = Studio::generateFromRecord('pay', (int) $l['id'], (int) $approval['requested_by']);
                if ($doc) {
                    DB::update('payroll_lines', ['payslip_document_id' => $doc], 'id = :id', ['id' => $l['id']]);
                }
            }
        }
    }

    /** The salary's expense bill (category Salaries), made when the salary is paid; kept on the line. */
    private static function salaryBill(array $line, array $run): int
    {
        if ($line['bill_id'] && DB::value('SELECT 1 FROM bills WHERE id = ? AND deleted_at IS NULL', [$line['bill_id']])) {
            return (int) $line['bill_id'];
        }
        [, $end] = self::periodBounds($run['period']);
        $cat = DB::value("SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = 'Salaries' AND deleted_at IS NULL");
        $name = (string) DB::value('SELECT name_en FROM employees WHERE id = ?', [$line['employee_id']]);
        $billId = FinanceService::create([
            'type' => 'expense', 'category_id' => $cat ?: null, 'employee_id' => $line['employee_id'], 'payroll_run_id' => $run['id'],
            'description' => __('payroll.bill_description', ['period' => $run['period'], 'name' => $name]),
            'amount_original' => $line['net_qar'], 'amount_qar' => (float) $line['net_qar'], 'bill_date' => $end, 'due_date' => date('Y-m-d', strtotime($end . ' +7 days')),
            'reference_no' => 'PAYROLL-' . $run['period'],
        ]);
        DB::update('payroll_lines', ['bill_id' => $billId], 'id = :id', ['id' => $line['id']]);
        return $billId;
    }

    public static function reject(int $runId, array $payload, array $approval): void
    {
        DB::update('payroll_runs', ['status' => 'draft'], "id = :id AND status = 'pending'", ['id' => $runId]);
    }

    /**
     * The Owner ticks who got paid straight from a draft (or from a run waiting for approval): paying approves the run
     * first, as the Owner's own approval would. Not when the Owner's own requests need a second approver (Security
     * settings), nor before the month has started.
     */
    public static function canQuickPay(array $run): bool
    {
        if (!in_array($run['status'], ['draft', 'pending'], true) || !self::started($run['period']) || !Auth::isOwner() || Approvals::ownerSecond()) {
            return false;
        }
        if ($run['status'] === 'pending') {
            $a = DB::row("SELECT * FROM approvals WHERE type = 'payroll' AND record_id = ? AND status = 'pending'", [$run['id']]);
            return $a && Approvals::canDecide($a);
        }
        return true;
    }

    /** Before the Owner pays from a draft: the page's changes saved, the run sent and approved (salary bills and payslips made). */
    public static function approveForPay(int $runId, array $lineInput): void
    {
        DB::transaction(function () use ($runId, $lineInput) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$runId]);
            if (!$run || !self::canQuickPay($run)) {
                throw new \DomainException(__('payroll.not_approved'));
            }
            if ($run['status'] === 'draft') {
                if ($lineInput) {
                    self::saveLines($runId, $lineInput);
                }
                self::submit($runId);
            }
            $approvalId = (int) DB::value("SELECT id FROM approvals WHERE type = 'payroll' AND record_id = ? AND status = 'pending'", [$runId]);
            Approvals::decide($approvalId, true, __('payroll.approved_on_pay'));
        });
    }

    /**
     * Pays all or the selected employees of an approved run. $account is one account for every salary paid with
     * $methodId, or the account for each way of paying [method id => account id, 0 = lines without a way]: each salary
     * is then paid the way chosen on its line ($methodId is used for the lines without one). All or nothing: a way
     * without an account stops the whole payment. Returns the number of salaries paid.
     */
    public static function pay(int $runId, array $lineIds, int|array $account, mixed $methodId, string $date): int
    {
        return DB::transaction(function () use ($runId, $lineIds, $account, $methodId, $date) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || !in_array($run['status'], ['approved', 'paid'], true)) {
                throw new \DomainException(__('payroll.not_approved'));
            }
            self::assertStarted($run['period']);
            $params = ['run' => $runId];
            $in = DB::in(array_map('intval', $lineIds) ?: [0], 'l', $params);
            $lines = DB::all("SELECT * FROM payroll_lines WHERE payroll_run_id = :run AND paid_at IS NULL AND net_qar > 0 AND id IN $in", $params);
            // every salary's account and way first: a way without an account stops the payment before anything is paid
            $plan = [];
            $used = [];
            foreach ($lines as $l) {
                if (is_array($account)) {
                    $way = (int) ($l['payment_method_id'] ?? 0);
                    $acc = (int) ($account[$way] ?? 0);
                    if (!$acc) {
                        throw new \DomainException(__('payroll.account_for', ['method' => self::methodLabel($way ?: null)]));
                    }
                    $plan[$l['id']] = [$acc, $way ?: ($methodId ?: null)];
                    $used[$way] = $acc;
                } else {
                    $plan[$l['id']] = [$account, $methodId];
                }
            }
            $n = 0;
            [, $end] = self::periodBounds($run['period']);
            foreach ($lines as $l) {
                [$acc, $meth] = $plan[$l['id']];
                // The repayment was set when the line was made: if the advances/loans now owe less (repaid by another
                // month, changed or deleted since), only what is owed is kept back and the rest is paid with the salary
                $adv = (float) $l['advances_qar'];
                if ($adv > 0 && !$l['bill_id']) {
                    $owed = self::openLoanBalance((int) $l['employee_id'], $end);
                    if ($adv > $owed + 0.004) {
                        $back = round($adv - $owed, 2);
                        $fix = ['advances_qar' => round($owed, 2), 'net_qar' => round((float) $l['net_qar'] + $back, 2),
                                'notes' => mb_substr(trim(($l['notes'] ? $l['notes'] . ' · ' : '') . __('payroll.note_advance_returned', ['amount' => money($back, 'QAR', false)])), 0, 255)];
                        DB::update('payroll_lines', $fix, 'id = :id', ['id' => $l['id']]);
                        Audit::log('update', 'payroll', 'payroll', $runId, ['line' => $l['id'], 'advances_qar' => $adv, 'net_qar' => $l['net_qar']], ['line' => $l['id']] + $fix,
                            __('payroll.run_title', ['period' => $run['period']]));
                        $l = $fix + $l;
                    }
                }
                $billId = self::salaryBill($l, $run); // the salary goes to Finance now, as it is paid
                if (FinanceService::payInFull($billId, $acc, $meth, $date, null, (int) $l['id'])) {
                    DB::update('payroll_lines', ['paid_at' => $date . ' ' . date('H:i:s')], 'id = :id', ['id' => $l['id']]);
                    self::applyLoanRepayment($l, $run['period']);
                    $n++;
                }
            }
            self::total($runId); // (a repayment given back above changes the total)
            self::refreshStatus($runId);
            if ($used) {
                // offered first next time for the same way
                $last = json_decode((string) Settings::get('payroll.method_accounts', '{}'), true) ?: [];
                foreach ($used as $way => $acc) {
                    $last[(string) $way] = $acc;
                }
                Settings::set('payroll.method_accounts', json_encode($last));
            }
            Audit::log('pay', 'payroll', 'payroll', $runId, null, ['paid' => $n] + (is_array($account) ? ['accounts_by_method' => $used] : ['account_id' => $account]),
                __('payroll.run_title', ['period' => $run['period']]));
            return $n;
        });
    }

    /** What the employee still owes on advances/loans issued up to $upTo. */
    public static function openLoanBalance(int $employeeId, string $upTo): float
    {
        return round((float) DB::value('SELECT COALESCE(SUM(balance_qar), 0) FROM employee_loans WHERE employee_id = ? AND balance_qar > 0 AND issue_date <= ? AND deleted_at IS NULL',
            [$employeeId, $upTo]), 2);
    }

    /**
     * Splits a repayment over open loans, oldest first, never more than a loan's balance.
     * $loans: [['id' =>, 'balance_qar' =>], ...] in repayment order. Returns [loan id => amount].
     */
    public static function allocateRepayment(float $amount, array $loans): array
    {
        $out = [];
        $amount = round($amount, 2);
        foreach ($loans as $loan) {
            if ($amount <= 0) {
                break;
            }
            $take = round(min($amount, (float) $loan['balance_qar']), 2);
            if ($take > 0) {
                $out[(int) $loan['id']] = $take;
                $amount = round($amount - $take, 2);
            }
        }
        return $out;
    }

    /** payroll_loan_allocations exists once database update 020 is applied. */
    private static function allocationsAvailable(): bool
    {
        static $ok = null;
        return $ok ??= (bool) DB::value("SHOW TABLES LIKE 'payroll_loan_allocations'");
    }

    /** A paid salary line repays the employee's open advances/loans with its full advances amount, oldest first; the split is recorded. */
    private static function applyLoanRepayment(array $line, string $period): void
    {
        $amount = (float) $line['advances_qar'];
        if ($amount <= 0) {
            return;
        }
        [, $end] = self::periodBounds($period);
        $loans = DB::all('SELECT id, balance_qar FROM employee_loans WHERE employee_id = ? AND balance_qar > 0 AND issue_date <= ? AND deleted_at IS NULL ORDER BY issue_date, id FOR UPDATE',
            [$line['employee_id'], $end]);
        foreach (self::allocateRepayment($amount, $loans) as $loanId => $take) {
            DB::run('UPDATE employee_loans SET balance_qar = balance_qar - ?, updated_at = NOW() WHERE id = ?', [$take, $loanId]);
            if (self::allocationsAvailable()) {
                DB::insert('payroll_loan_allocations', ['payroll_line_id' => $line['id'], 'loan_id' => $loanId, 'amount_qar' => $take]);
            }
        }
    }

    /** A deleted salary payment came back from the Trash: the line is paid again and the loan repayment taken again. */
    public static function paymentRestored(int $lineId, string $date): void
    {
        $l = DB::row('SELECT l.*, r.period FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id WHERE l.id = ?', [$lineId]);
        if (!$l || $l['paid_at']) {
            return;
        }
        DB::update('payroll_lines', ['paid_at' => substr($date, 0, 10) . ' ' . date('H:i:s')], 'id = :id', ['id' => $lineId]);
        self::applyLoanRepayment($l, $l['period']);
        self::refreshStatus((int) $l['payroll_run_id']);
    }

    /** A salary payment was deleted (after approval): the line is unpaid again and exactly what it repaid is given back to each loan. */
    public static function paymentRemoved(int $lineId): void
    {
        $l = DB::row('SELECT * FROM payroll_lines WHERE id = ?', [$lineId]);
        if (!$l || !$l['paid_at']) {
            return;
        }
        DB::update('payroll_lines', ['paid_at' => null], 'id = :id', ['id' => $lineId]);
        $allocs = self::allocationsAvailable() ? DB::all('SELECT * FROM payroll_loan_allocations WHERE payroll_line_id = ?', [$lineId]) : [];
        if ($allocs) {
            foreach ($allocs as $a) {
                DB::run('UPDATE employee_loans SET balance_qar = LEAST(amount_qar, balance_qar + ?), updated_at = NOW() WHERE id = ?', [$a['amount_qar'], $a['loan_id']]);
            }
            DB::run('DELETE FROM payroll_loan_allocations WHERE payroll_line_id = ?', [$lineId]);
        } else {
            // Paid before the split was recorded (database update 020): give back the advances, newest loan first
            $amount = (float) $l['advances_qar'];
            foreach (DB::all('SELECT id, amount_qar, balance_qar FROM employee_loans WHERE employee_id = ? AND balance_qar < amount_qar AND deleted_at IS NULL ORDER BY issue_date DESC, id DESC', [$l['employee_id']]) as $loan) {
                if ($amount <= 0) {
                    break;
                }
                $give = min($amount, (float) $loan['amount_qar'] - (float) $loan['balance_qar']);
                DB::run('UPDATE employee_loans SET balance_qar = balance_qar + ? WHERE id = ?', [$give, $loan['id']]);
                $amount = round($amount - $give, 2);
            }
        }
        self::refreshStatus((int) $l['payroll_run_id']);
    }

    private static function refreshStatus(int $runId): void
    {
        $unpaid = (int) DB::value('SELECT COUNT(*) FROM payroll_lines WHERE payroll_run_id = ? AND paid_at IS NULL AND net_qar > 0', [$runId]);
        DB::run("UPDATE payroll_runs SET status = ? WHERE id = ? AND status IN ('approved','paid')", [$unpaid ? 'approved' : 'paid', $runId]);
        Cache::bump();
    }

    /** True once any salary of the run is paid, fully or in part: that payment has to be deleted before the run. */
    public static function hasPayments(int $runId): bool
    {
        return (bool) DB::value('SELECT 1 FROM payroll_lines l LEFT JOIN bills b ON b.id = l.bill_id AND b.deleted_at IS NULL
            WHERE l.payroll_run_id = ? AND (l.paid_at IS NOT NULL OR b.paid_qar > 0) LIMIT 1', [$runId]);
    }

    /**
     * Takes a run sent or approved before its month started back to a draft (the Owner's check page): its approval is
     * cancelled, its salary bills (none paid) are removed and its payslips voided. The lines stay, ready for the month.
     */
    public static function backToDraft(int $runId): void
    {
        DB::transaction(function () use ($runId) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$runId]);
            if (!$run || $run['status'] === 'draft') {
                return;
            }
            if (self::hasPayments($runId)) {
                throw new \DomainException(__('payroll.cannot_delete_paid'));
            }
            $lines = DB::all('SELECT * FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
            Approvals::cancelFor('payroll', $runId);
            $now = date('Y-m-d H:i:s');
            $billIds = [];
            foreach ($lines as $l) {
                if ($l['bill_id']) {
                    DB::update('bills', ['deleted_at' => $now], 'id = :id AND deleted_at IS NULL', ['id' => $l['bill_id']]);
                    Approvals::cancelFor('bill', (int) $l['bill_id']);
                    $billIds[] = (int) $l['bill_id'];
                }
                if ($l['payslip_document_id']) {
                    DB::update('documents', ['is_void' => 1], 'id = :id', ['id' => $l['payslip_document_id']]);
                    Audit::log('void', 'studio', 'document', (int) $l['payslip_document_id'], ['is_void' => 0], ['is_void' => 1, 'reason' => 'payroll back to draft'],
                        (string) DB::value('SELECT ref_no FROM documents WHERE id = ?', [$l['payslip_document_id']]));
                }
            }
            DB::run('UPDATE payroll_lines SET bill_id = NULL, payslip_document_id = NULL WHERE payroll_run_id = ?', [$runId]);
            DB::update('payroll_runs', ['status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'updated_at' => $now], 'id = :id', ['id' => $runId]);
            Audit::log('update', 'payroll', 'payroll', $runId, ['status' => $run['status']], ['status' => 'draft', 'bills_removed' => $billIds],
                __('payroll.run_title', ['period' => $run['period']]) . ' — back to draft (month not started)');
        });
        Cache::bump();
    }

    /**
     * Deletes a payroll run with everything it booked: the salary payments already made are removed (the accounts'
     * balances go back) and what they repaid is given back to the loans, the salary bills are deleted, the payslips
     * voided. Each removed payment is in the Activity log. (Before 9 Oct 2026 a run with paid salaries could not be
     * deleted until those payments were deleted one by one.)
     */
    public static function delete(int $runId): void
    {
        DB::transaction(function () use ($runId) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$runId]);
            if (!$run) {
                throw new \DomainException(__('payroll.cannot_delete'));
            }
            $lines = DB::all('SELECT * FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
            $billIds = array_values(array_filter(array_map(fn ($l) => (int) $l['bill_id'], $lines)));
            $now = date('Y-m-d H:i:s');
            // the salary payments: made from the run, or recorded on a salary bill in Finance
            $p = ['run' => $runId];
            $byBill = $billIds ? ' OR bill_id IN ' . DB::in($billIds, 'b', $p) : '';
            $payments = DB::all('SELECT * FROM bill_payments WHERE deleted_at IS NULL AND (payroll_line_id IN (SELECT id FROM payroll_lines WHERE payroll_run_id = :run)' . $byBill . ')', $p);
            foreach ($payments as $pay) {
                DB::update('bill_payments', ['deleted_at' => $now], 'id = :id', ['id' => $pay['id']]);
                Audit::log('delete', 'finance', 'bill_payment', (int) $pay['id'], $pay, null, __('payroll.payment_removed_with_run', ['period' => $run['period']]));
            }
            foreach ($lines as $l) {
                if ($l['paid_at']) {
                    self::paymentRemoved((int) $l['id']); // what the salary repaid goes back to the employee's loans
                }
            }
            Approvals::cancelFor('payroll', $runId);
            foreach ($billIds as $billId) {
                DB::update('bills', ['deleted_at' => $now], 'id = :id AND deleted_at IS NULL', ['id' => $billId]);
                Approvals::cancelFor('bill', $billId);
            }
            foreach (array_filter(array_map(fn ($l) => (int) $l['payslip_document_id'], $lines)) as $docId) {
                DB::update('documents', ['is_void' => 1], 'id = :id', ['id' => $docId]);
                Audit::log('void', 'studio', 'document', $docId, ['is_void' => 0], ['is_void' => 1, 'reason' => 'payroll deleted'], (string) DB::value('SELECT ref_no FROM documents WHERE id = ?', [$docId]));
            }
            DB::run('DELETE FROM payroll_runs WHERE id = ?', [$runId]);
            Audit::log('delete', 'payroll', 'payroll', $runId, $run + ['bills' => $billIds, 'payments' => array_map(fn ($x) => (int) $x['id'], $payments)], null,
                __('payroll.run_title', ['period' => $run['period']]));
        });
        Cache::bump();
    }
}
