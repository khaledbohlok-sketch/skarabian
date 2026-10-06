<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Monthly payroll run.
 *   create   one line per active employee: basic + allowances (pro-rated for joiners/leavers), overtime from
 *            attendance (hourly = basic / 30 / 8, paid at 125%), deductions for absent days and unpaid leave
 *            (daily = basic / 30), and the monthly repayment of advances and loans
 *   submit   → Owner / General Manager approval (no self-approval)
 *   approve  → one expense bill per employee (category Salaries) + payslip in SK Arabian Studio
 *   pay      → all or selected employees: payment recorded on each bill, loan balances reduced
 */
final class PayrollService
{
    public const OVERTIME_RATE = 1.25;
    public const MONTH_DAYS = 30;

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
        DB::run('DELETE FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
        $emps = DB::all("SELECT * FROM employees WHERE deleted_at IS NULL AND (hire_date IS NULL OR hire_date <= ?) AND (end_date IS NULL OR end_date >= ?)
            AND (status IN ('active','on_leave') OR (status = 'left' AND end_date BETWEEN ? AND ?)) ORDER BY name_en", [$end, $start, $start, $end]);
        foreach ($emps as $e) {
            DB::insert('payroll_lines', ['payroll_run_id' => $runId, 'employee_id' => $e['id']] + self::computeLine($e, $start, $end));
        }
        self::total($runId);
    }

    public static function computeLine(array $e, string $start, string $end): array
    {
        $daysInMonth = (int) date('t', strtotime($start));
        $from = max($start, $e['hire_date'] ?: $start);
        $to = min($end, $e['end_date'] ?: $end);
        $worked = max(0, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $factor = $worked >= $daysInMonth ? 1.0 : $worked / $daysInMonth;
        $basicFull = (float) $e['basic_salary_qar'];
        $basic = round($basicFull * $factor, 2);
        $allow = round(((float) $e['housing_allowance'] + (float) $e['transport_allowance'] + (float) $e['other_allowance']) * $factor, 2);
        $daily = $basicFull / self::MONTH_DAYS;
        $hours = (float) DB::value('SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL', [$e['id'], $start, $end]);
        $overtime = round($hours * $daily / 8 * self::OVERTIME_RATE, 2);
        $absent = (int) DB::value("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND status = 'absent' AND deleted_at IS NULL", [$e['id'], $start, $end]);
        $unpaid = 0;
        foreach (DB::all("SELECT start_date, end_date FROM leave_requests WHERE employee_id = ? AND leave_type = 'unpaid' AND status = 'approved' AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?", [$e['id'], $end, $start]) as $l) {
            $unpaid += (int) ((strtotime(min($end, $l['end_date'])) - strtotime(max($start, $l['start_date']))) / 86400) + 1;
        }
        $deductions = round(($absent + $unpaid) * $daily, 2);
        $gross = max(0.0, $basic + $allow + $overtime - $deductions);
        $advances = (float) DB::value('SELECT COALESCE(SUM(LEAST(monthly_deduction, balance_qar)), 0) FROM employee_loans WHERE employee_id = ? AND balance_qar > 0 AND issue_date <= ? AND deleted_at IS NULL', [$e['id'], $end]);
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

    /** Manual changes on a draft run: overtime, deductions, advances and notes per employee. */
    public static function saveLines(int $runId, array $input): void
    {
        DB::transaction(function () use ($runId, $input) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || $run['status'] !== 'draft') {
                throw new \DomainException(__('payroll.not_draft'));
            }
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
                $d['net_qar'] = round((float) $l['basic_qar'] + (float) $l['allowances_qar'] + $d['overtime_qar'] - $d['deductions_qar'] - $d['advances_qar'], 2);
                if ($d['net_qar'] < 0) {
                    throw new \DomainException(__('payroll.net_negative'));
                }
                DB::update('payroll_lines', $d, 'id = :id', ['id' => $l['id']]);
            }
            $t = self::total($runId);
            Audit::log('update', 'payroll', 'payroll', $runId, ['total' => $run['total_net_qar']], ['total' => $t], __('payroll.run_title', ['period' => $run['period']]));
        });
    }

    public static function submit(int $runId): string
    {
        $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL', [$runId]);
        if (!$run || $run['status'] !== 'draft') {
            throw new \DomainException(__('payroll.not_draft'));
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM payroll_lines WHERE payroll_run_id = ?', [$runId]);
        if (!$n) {
            throw new \DomainException(__('payroll.no_lines'));
        }
        DB::update('payroll_runs', ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $runId]);
        Approvals::request('payroll', 'payroll', $runId, __('payroll.approval_title', ['period' => $run['period'], 'n' => $n]), (float) $run['total_net_qar']);
        return 'pending';
    }

    /** Approval handler: one expense bill per employee; payslips are generated in SK Arabian Studio. */
    public static function approve(int $runId, array $payload, array $approval): void
    {
        $run = DB::row("SELECT * FROM payroll_runs WHERE id = ? AND status = 'pending' FOR UPDATE", [$runId]);
        if (!$run) {
            return;
        }
        DB::update('payroll_runs', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $runId]);
        [, $end] = self::periodBounds($run['period']);
        $cat = DB::value("SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = 'Salaries' AND deleted_at IS NULL");
        foreach (DB::all('SELECT l.*, e.name_en FROM payroll_lines l JOIN employees e ON e.id = l.employee_id WHERE l.payroll_run_id = ?', [$runId]) as $l) {
            if ((float) $l['net_qar'] <= 0) {
                continue;
            }
            $billId = FinanceService::create([
                'type' => 'expense', 'category_id' => $cat ?: null, 'employee_id' => $l['employee_id'], 'payroll_run_id' => $runId,
                'description' => __('payroll.bill_description', ['period' => $run['period'], 'name' => $l['name_en']]),
                'amount_original' => $l['net_qar'], 'amount_qar' => (float) $l['net_qar'], 'bill_date' => $end, 'due_date' => date('Y-m-d', strtotime($end . ' +7 days')),
                'reference_no' => 'PAYROLL-' . $run['period'],
            ]);
            DB::update('payroll_lines', ['bill_id' => $billId], 'id = :id', ['id' => $l['id']]);
            if (method_exists(Studio::class, 'generateFromRecord')) {
                $doc = Studio::generateFromRecord('pay', (int) $l['id'], (int) $approval['requested_by']);
                if ($doc) {
                    DB::update('payroll_lines', ['payslip_document_id' => $doc], 'id = :id', ['id' => $l['id']]);
                }
            }
        }
    }

    public static function reject(int $runId, array $payload, array $approval): void
    {
        DB::update('payroll_runs', ['status' => 'draft'], "id = :id AND status = 'pending'", ['id' => $runId]);
    }

    /** Pays all or the selected employees of an approved run. Returns the number of salaries paid. */
    public static function pay(int $runId, array $lineIds, int $accountId, mixed $methodId, string $date): int
    {
        return DB::transaction(function () use ($runId, $lineIds, $accountId, $methodId, $date) {
            $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || !in_array($run['status'], ['approved', 'paid'], true)) {
                throw new \DomainException(__('payroll.not_approved'));
            }
            $params = ['run' => $runId];
            $in = DB::in(array_map('intval', $lineIds) ?: [0], 'l', $params);
            $lines = DB::all("SELECT * FROM payroll_lines WHERE payroll_run_id = :run AND paid_at IS NULL AND bill_id IS NOT NULL AND id IN $in", $params);
            $n = 0;
            foreach ($lines as $l) {
                if (FinanceService::payInFull((int) $l['bill_id'], $accountId, $methodId, $date, null, (int) $l['id'])) {
                    DB::update('payroll_lines', ['paid_at' => $date . ' ' . date('H:i:s')], 'id = :id', ['id' => $l['id']]);
                    self::applyLoanRepayment((int) $l['employee_id'], (float) $l['advances_qar']);
                    $n++;
                }
            }
            self::refreshStatus($runId);
            Audit::log('pay', 'payroll', 'payroll', $runId, null, ['paid' => $n, 'account_id' => $accountId], __('payroll.run_title', ['period' => $run['period']]));
            return $n;
        });
    }

    /** Reduces the employee's open advances/loans, oldest first. */
    private static function applyLoanRepayment(int $employeeId, float $amount): void
    {
        foreach (DB::all('SELECT id, balance_qar, monthly_deduction FROM employee_loans WHERE employee_id = ? AND balance_qar > 0 AND deleted_at IS NULL ORDER BY issue_date, id', [$employeeId]) as $loan) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, (float) $loan['balance_qar'], (float) $loan['monthly_deduction']);
            DB::run('UPDATE employee_loans SET balance_qar = balance_qar - ?, updated_at = NOW() WHERE id = ?', [$take, $loan['id']]);
            $amount = round($amount - $take, 2);
        }
    }

    /** A salary payment was deleted (after approval): the line is unpaid again and the loan repayment is given back. */
    public static function paymentRemoved(int $lineId): void
    {
        $l = DB::row('SELECT * FROM payroll_lines WHERE id = ?', [$lineId]);
        if (!$l || !$l['paid_at']) {
            return;
        }
        DB::update('payroll_lines', ['paid_at' => null], 'id = :id', ['id' => $lineId]);
        $amount = (float) $l['advances_qar'];
        foreach (DB::all('SELECT id, amount_qar, balance_qar FROM employee_loans WHERE employee_id = ? AND balance_qar < amount_qar AND deleted_at IS NULL ORDER BY issue_date DESC, id DESC', [$l['employee_id']]) as $loan) {
            if ($amount <= 0) {
                break;
            }
            $give = min($amount, (float) $loan['amount_qar'] - (float) $loan['balance_qar']);
            DB::run('UPDATE employee_loans SET balance_qar = balance_qar + ? WHERE id = ?', [$give, $loan['id']]);
            $amount = round($amount - $give, 2);
        }
        self::refreshStatus((int) $l['payroll_run_id']);
    }

    private static function refreshStatus(int $runId): void
    {
        $unpaid = (int) DB::value('SELECT COUNT(*) FROM payroll_lines WHERE payroll_run_id = ? AND paid_at IS NULL AND net_qar > 0', [$runId]);
        DB::run("UPDATE payroll_runs SET status = ? WHERE id = ? AND status IN ('approved','paid')", [$unpaid ? 'approved' : 'paid', $runId]);
        Cache::bump();
    }

    /** Only a draft (or a run waiting for approval) can be deleted; nothing has been booked yet. */
    public static function delete(int $runId): void
    {
        $run = DB::row('SELECT * FROM payroll_runs WHERE id = ?', [$runId]);
        if (!$run || !in_array($run['status'], ['draft', 'pending'], true)) {
            throw new \DomainException(__('payroll.cannot_delete'));
        }
        Approvals::cancelFor('payroll', $runId);
        DB::run('DELETE FROM payroll_runs WHERE id = ?', [$runId]);
        Audit::log('delete', 'payroll', 'payroll', $runId, $run, null, __('payroll.run_title', ['period' => $run['period']]));
    }
}
