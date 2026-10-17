<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;
use App\Services\Cache;
use App\Services\Dashboard;
use App\Services\PayrollService;
use App\Services\Pickers;
use App\Services\Settings;
use App\Services\TrashService;

class PayrollController extends Controller
{
    public function index(): void
    {
        Auth::requirePerm('payroll', 'view');
        $runs = DB::all('SELECT r.*, (SELECT COUNT(*) FROM payroll_lines l WHERE l.payroll_run_id = r.id) AS employees,
            (SELECT COUNT(*) FROM payroll_lines l WHERE l.payroll_run_id = r.id AND l.paid_at IS NOT NULL) AS paid,
            (SELECT COALESCE(SUM(l.net_qar), 0) FROM payroll_lines l WHERE l.payroll_run_id = r.id AND l.paid_at IS NOT NULL) AS paid_net
            FROM payroll_runs r WHERE r.deleted_at IS NULL ORDER BY r.period DESC LIMIT 60');
        $existing = array_column($runs, 'period');
        $next = date('Y-m');
        while (in_array($next, $existing, true)) {
            $next = date('Y-m', strtotime($next . '-01 +1 month'));
        }
        $this->view('portal/payroll/index', ['title' => __('nav.payroll'), 'runs' => $runs, 'next' => $next, 'due' => Dashboard::payrollDue(), 'sens' => Auth::can('payroll', 'sensitive')]);
    }

    public function create(): void
    {
        Auth::requirePerm('payroll', 'create');
        try {
            $period = (string) Request::post('period');
            $id = PayrollService::create($period);
            Cache::bump();
            // a later month: prepared as a draft, sent for approval once the month starts
            $this->flash('success', __('payroll.created') . (PayrollService::started($period) ? '' : ' ' . __('payroll.draft_until', ['date' => fmt_date($period . '-01')])));
            $this->redirect('/portal/payroll/' . $id);
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/portal/payroll');
        }
    }

    private function run(string $id): array
    {
        Auth::requirePerm('payroll', 'view');
        $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$run) {
            Response::notFound();
        }
        return $run;
    }

    public function show(string $id): void
    {
        $run = $this->run($id);
        $methodsOn = PayrollService::methodsOn();
        // with database update 023: the employee's usual way (offered on a line that has none yet) and the way a paid salary was paid
        $ways = $methodsOn ? ', e.salary_method_id, (SELECT bp.payment_method_id FROM bill_payments bp WHERE bp.payroll_line_id = l.id AND bp.deleted_at IS NULL ORDER BY bp.id DESC LIMIT 1) AS paid_method_id' : '';
        $lines = DB::all('SELECT l.*, e.name_en, e.name_ar, e.emp_no, ' . (\App\Core\Lang::isRtl() ? 'COALESCE(p.value_ar, p.value_en)' : 'p.value_en') . ' AS position, b.status AS bill_status' . $ways . ' FROM payroll_lines l JOIN employees e ON e.id = l.employee_id
            LEFT JOIN lookups p ON p.id = e.position_id LEFT JOIN bills b ON b.id = l.bill_id WHERE l.payroll_run_id = ? ORDER BY e.name_en', [$run['id']]);
        $approval = $run['status'] === 'pending' ? DB::row("SELECT * FROM approvals WHERE type = 'payroll' AND record_id = ? AND status = 'pending'", [$run['id']]) : null;
        $this->view('portal/payroll/show', [
            'title' => __('payroll.run_title', ['period' => fmt_month($run['period'] . '-01') ?: $run['period']]), 'run' => $run, 'lines' => $lines, // "Payroll October 2026"
            'sens' => Auth::can('payroll', 'sensitive'), 'canEdit' => Auth::can('payroll', 'edit'),
            'approval' => $approval, 'canDecide' => $approval && Approvals::canDecide($approval),
            'methodsOn' => $methodsOn, 'methods' => $methodsOn ? PayrollService::methods() : [],
            'groups' => $methodsOn && in_array($run['status'], ['approved', 'paid'], true) ? PayrollService::payGroups($lines) : [],
            'quickPay' => PayrollService::canQuickPay($run), 'payAccount' => $this->payAccount(),
        ]);
    }

    /** The account offered first in "Pay salaries": the one used last time, else the first cash account. [id, name] or null. */
    private function payAccount(): ?array
    {
        $id = (int) Settings::get('payroll.last_account', '0');
        $acc = $id ? DB::row('SELECT id, name FROM accounts WHERE id = ? AND active = 1 AND deleted_at IS NULL', [$id]) : null;
        $acc ??= DB::row("SELECT id, name FROM accounts WHERE active = 1 AND deleted_at IS NULL ORDER BY type = 'cash' DESC, id LIMIT 1");
        return $acc ?: null;
    }

    public function saveLines(string $id): void
    {
        Auth::requirePerm('payroll', 'edit');
        if (!Auth::can('payroll', 'sensitive')) {
            Auth::deny('payroll.sensitive');
        }
        $run = $this->run($id);
        try {
            if (Request::post('action') === 'recalc') {
                if ($run['status'] !== 'draft') {
                    throw new \DomainException(__('payroll.not_draft'));
                }
                PayrollService::fillLines((int) $run['id'], $run['period']);
                $this->flash('success', __('payroll.recalculated'));
            } else {
                PayrollService::saveLines((int) $run['id'], (array) ($_POST['lines'] ?? []), Request::post('remember_methods') === '1');
                $this->flash('success', __('common.saved'));
            }
            Cache::bump();
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/payroll/' . $run['id']);
    }

    public function submit(string $id): void
    {
        Auth::requirePerm('payroll', 'edit');
        $run = $this->run($id);
        try {
            PayrollService::submit((int) $run['id']);
            $this->flash('success', __('approvals.sent'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/payroll/' . $run['id']);
    }

    /** "Pay all" or "Pay selected". */
    public function pay(string $id): void
    {
        Auth::requirePerm('payroll', 'edit');
        if (!Auth::can('finance', 'create') && !Auth::isOwner()) {
            Auth::deny('finance.create');
        }
        $run = $this->run($id);
        $date = (string) Request::post('date');
        $ids = Request::post('scope') === 'all'
            ? DB::column('SELECT id FROM payroll_lines WHERE payroll_run_id = ? AND paid_at IS NULL', [$run['id']])
            : array_map('intval', (array) ($_POST['line_ids'] ?? []));
        try {
            if (is_array($_POST['accounts'] ?? null) && PayrollService::methodsOn()) {
                // each way of paying has its own account (way id => account id; 0 = salaries without a way)
                $account = [];
                foreach ($_POST['accounts'] as $way => $acc) {
                    if ((int) $acc && !Pickers::valid('accounts', (int) $acc)) {
                        throw new \DomainException(__('bills.account_required'));
                    }
                    if ((int) $acc) {
                        $account[(int) $way] = (int) $acc;
                    }
                }
            } else {
                $account = (int) Request::post('account_id');
                if (!Pickers::valid('accounts', $account)) {
                    throw new \DomainException(__('bills.account_required'));
                }
            }
            if (!valid_date($date) || $date > date('Y-m-d', strtotime('+1 day'))) {
                throw new \DomainException(__('validation.date'));
            }
            if (!$ids) {
                throw new \DomainException(__('payroll.none_selected'));
            }
            $method = (int) Request::post('payment_method_id') ?: null;
            if (in_array($run['status'], ['draft', 'pending'], true)) {
                // the Owner ticks who got paid straight from the draft: approved first (salary bills and payslips), then
                // paid — all or nothing
                $n = DB::transaction(function () use ($run, $ids, $account, $method, $date) {
                    PayrollService::approveForPay((int) $run['id'], $run['status'] === 'draft' ? (array) ($_POST['lines'] ?? []) : []);
                    return PayrollService::pay((int) $run['id'], $ids, $account, $method, $date);
                });
                $this->flash('success', __('payroll.approved_and_paid', ['n' => $n]));
            } else {
                $n = PayrollService::pay((int) $run['id'], $ids, $account, $method, $date);
                $this->flash('success', __('payroll.paid_n', ['n' => $n]));
            }
            if (is_int($account)) {
                Settings::set('payroll.last_account', (string) $account); // offered first next time
            }
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/payroll/' . $run['id']);
    }

    /** The Owner deletes at once; anyone else's deletion waits for the Owner's approval. */
    public function delete(string $id): void
    {
        Auth::requirePerm('payroll', 'delete');
        $run = $this->run($id);
        try {
            // the Owner deletes at once, unless the Owner's own requests need a second approver (another Owner decides)
            if (Auth::isOwner() && !(Approvals::ownerSecond() && Approvals::otherOwner())) {
                PayrollService::delete((int) $run['id']);
                $this->flash('success', __('payroll.deleted'));
                $this->redirect('/portal/payroll');
            }
            // anyone else asks the Owner (a run with paid salaries too: approving it removes those payments with the run)
            TrashService::requestDeletion('payroll', (int) $run['id'], __('payroll.run_title', ['period' => $run['period']]),
                ['kind' => 'payroll', 'module' => 'payroll', 'url' => '/portal/payroll/' . $run['id']], (float) $run['total_net_qar']);
            $this->flash('success', __('approvals.delete_requested'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/payroll/' . $run['id']);
    }
}
