<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Resources\LeaveRequests;
use App\Services\Approvals;
use App\Services\Studio;

/**
 * "My HR": every user linked to an employee record sees their own leave balance, leave requests, payslips
 * and HR letters, and can ask for leave. Nobody approves their own request.
 */
class MyHrController extends Controller
{
    private function employee(): array
    {
        Auth::requireLogin();
        $emp = (int) (Auth::user()['employee_id'] ?? 0);
        $e = $emp ? DB::row('SELECT e.*, p.value_en AS position_en, p.value_ar AS position_ar, d.value_en AS dept_en, d.value_ar AS dept_ar
            FROM employees e LEFT JOIN lookups p ON p.id = e.position_id LEFT JOIN lookups d ON d.id = e.department_id WHERE e.id = ? AND e.deleted_at IS NULL', [$emp]) : null;
        if (!$e) {
            $this->view('portal/my_hr/none', ['title' => __('nav.my_hr')]);
        }
        return $e;
    }

    public function index(): void
    {
        $e = $this->employee();
        $pending = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'pending' AND deleted_at IS NULL", [$e['id']]);
        $leave = DB::all('SELECT * FROM leave_requests WHERE employee_id = ? AND deleted_at IS NULL ORDER BY start_date DESC LIMIT 30', [$e['id']]);
        $payslips = DB::all("SELECT l.id, l.net_qar, l.basic_qar, l.allowances_qar, l.overtime_qar, l.deductions_qar, l.advances_qar, l.paid_at, r.period, r.status,
                d.id AS doc_id, d.ref_no
            FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id LEFT JOIN documents d ON d.id = l.payslip_document_id AND d.deleted_at IS NULL AND d.is_void = 0
            WHERE l.employee_id = ? AND r.status IN ('approved','paid') ORDER BY r.period DESC LIMIT 24", [$e['id']]);
        $letters = DB::all("SELECT id, ref_no, doc_type, title, doc_date FROM documents WHERE record_type = 'employee' AND record_id = ? AND deleted_at IS NULL AND is_void = 0
            AND doc_type IN ('" . implode("','", Studio::OWN_TYPES) . "') ORDER BY doc_date DESC LIMIT 20", [$e['id']]);
        $month = date('Y-m');
        $att = DB::pairs("SELECT status, COUNT(*) FROM attendance WHERE employee_id = ? AND work_date LIKE ? AND deleted_at IS NULL GROUP BY status", [$e['id'], $month . '%']);
        $ot = (float) DB::value('SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE employee_id = ? AND work_date LIKE ? AND deleted_at IS NULL', [$e['id'], $month . '%']);
        $loans = DB::all("SELECT id, type, issue_date, amount_qar, balance_qar, monthly_deduction FROM employee_loans WHERE employee_id = ? AND deleted_at IS NULL AND balance_qar > 0 ORDER BY id DESC", [$e['id']]);
        $docs = [];
        foreach (['qid_expiry' => 'employees.qid_expiry', 'passport_expiry' => 'employees.passport_expiry', 'visa_expiry' => 'employees.visa_expiry', 'health_card_expiry' => 'employees.health_card_expiry'] as $col => $label) {
            if ($e[$col]) {
                $docs[] = ['label' => $label, 'date' => $e[$col], 'days' => (int) floor((strtotime($e[$col]) - strtotime(date('Y-m-d'))) / 86400)];
            }
        }
        $this->view('portal/my_hr/index', [
            'title' => __('nav.my_hr'), 'e' => $e, 'balance' => (float) $e['leave_balance'], 'pending' => $pending, 'leave' => $leave,
            'payslips' => $payslips, 'letters' => $letters, 'att' => $att, 'ot' => $ot, 'loans' => $loans, 'docs' => $docs,
        ]);
    }

    public function requestLeave(): void
    {
        $e = $this->employee();
        $data = [
            'employee_id' => (int) $e['id'],
            'leave_type'  => array_key_exists((string) Request::post('leave_type'), LeaveRequests::TYPES) ? (string) Request::post('leave_type') : '',
            'start_date'  => (string) Request::post('start_date'),
            'end_date'    => (string) Request::post('end_date'),
            'days'        => null,
            'reason'      => mb_substr(trim((string) Request::post('reason')), 0, 255) ?: null,
        ];
        $errors = [];
        if ($data['leave_type'] === '') {
            $errors['leave_type'] = __('validation.required');
        }
        foreach (['start_date', 'end_date'] as $f) {
            $d = \DateTime::createFromFormat('!Y-m-d', $data[$f]);
            if (!$d || $d->format('Y-m-d') !== $data[$f]) {
                $errors[$f] = __('validation.date');
            }
        }
        if (!$errors && $data['start_date'] < date('Y-m-d', strtotime('-30 days'))) {
            $errors['start_date'] = __('my_hr.too_old');
        }
        if (!$errors) {
            try {
                $data = (new LeaveRequests())->prepare($data, null);
            } catch (ValidationException $ex) {
                $errors = $ex->errors;
            }
        }
        if ($errors) {
            Session::errors($errors);
            Session::keepOld($_POST);
            $this->flash('error', __('validation.fix_errors'));
            $this->redirect('/portal/my-hr#leave');
        }
        $id = DB::insert('leave_requests', $data + ['status' => 'pending', 'created_by' => Auth::id()]);
        Audit::log('create', 'hr', 'leave_request', $id, null, $data, 'Leave requested by employee');
        (new LeaveRequests())->afterSave($id, $data, null, true);
        $this->flash('success', __('my_hr.leave_sent'));
        $this->redirect('/portal/my-hr#leave');
    }

    public function cancelLeave(string $id): void
    {
        $e = $this->employee();
        $lr = DB::row("SELECT * FROM leave_requests WHERE id = ? AND employee_id = ? AND status = 'pending' AND deleted_at IS NULL", [(int) $id, $e['id']]) ?? Response::notFound();
        DB::update('leave_requests', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $lr['id']]);
        Approvals::cancelFor('leave_request', (int) $lr['id']);
        Audit::log('update', 'hr', 'leave_request', (int) $lr['id'], ['status' => 'pending'], ['status' => 'cancelled'], 'Leave request cancelled by employee');
        $this->flash('success', __('my_hr.leave_cancelled'));
        $this->redirect('/portal/my-hr#leave');
    }
}
