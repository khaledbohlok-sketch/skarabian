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

class PayrollController extends Controller
{
    public function index(): void
    {
        Auth::requirePerm('payroll', 'view');
        $runs = DB::all('SELECT r.*, (SELECT COUNT(*) FROM payroll_lines l WHERE l.payroll_run_id = r.id) AS employees,
            (SELECT COUNT(*) FROM payroll_lines l WHERE l.payroll_run_id = r.id AND l.paid_at IS NOT NULL) AS paid
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
            $id = PayrollService::create((string) Request::post('period'));
            Cache::bump();
            $this->flash('success', __('payroll.created'));
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
        $lines = DB::all('SELECT l.*, e.name_en, e.emp_no, p.value_en AS position, b.status AS bill_status FROM payroll_lines l JOIN employees e ON e.id = l.employee_id
            LEFT JOIN lookups p ON p.id = e.position_id LEFT JOIN bills b ON b.id = l.bill_id WHERE l.payroll_run_id = ? ORDER BY e.name_en', [$run['id']]);
        $approval = $run['status'] === 'pending' ? DB::row("SELECT * FROM approvals WHERE type = 'payroll' AND record_id = ? AND status = 'pending'", [$run['id']]) : null;
        $this->view('portal/payroll/show', [
            'title' => __('payroll.run_title', ['period' => $run['period']]), 'run' => $run, 'lines' => $lines,
            'sens' => Auth::can('payroll', 'sensitive'), 'canEdit' => Auth::can('payroll', 'edit'),
            'approval' => $approval, 'canDecide' => $approval && Approvals::canDecide($approval),
        ]);
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
                PayrollService::saveLines((int) $run['id'], (array) ($_POST['lines'] ?? []));
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
        $account = (int) Request::post('account_id');
        $date = (string) Request::post('date');
        $ids = Request::post('scope') === 'all'
            ? DB::column('SELECT id FROM payroll_lines WHERE payroll_run_id = ? AND paid_at IS NULL', [$run['id']])
            : array_map('intval', (array) ($_POST['line_ids'] ?? []));
        try {
            if (!Pickers::valid('accounts', $account)) {
                throw new \DomainException(__('bills.account_required'));
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d', strtotime('+1 day'))) {
                throw new \DomainException(__('validation.date'));
            }
            if (!$ids) {
                throw new \DomainException(__('payroll.none_selected'));
            }
            $method = (int) Request::post('payment_method_id') ?: null;
            $n = PayrollService::pay((int) $run['id'], $ids, $account, $method, $date);
            $this->flash('success', __('payroll.paid_n', ['n' => $n]));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/payroll/' . $run['id']);
    }

    public function delete(string $id): void
    {
        Auth::requirePerm('payroll', 'delete');
        $run = $this->run($id);
        try {
            PayrollService::delete((int) $run['id']);
            Cache::bump();
            $this->flash('success', __('payroll.deleted'));
            $this->redirect('/portal/payroll');
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/portal/payroll/' . $run['id']);
        }
    }
}
