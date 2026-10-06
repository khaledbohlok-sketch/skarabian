<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;
use App\Services\Cache;
use App\Services\HrService;

class HrController extends Controller
{
    public function deactivateLogin(string $id): void
    {
        if (!Auth::can('hr', 'edit') && !Auth::can('users', 'edit')) {
            Auth::deny('hr.edit');
        }
        $ok = HrService::deactivateLogin((int) $id);
        $this->flash($ok ? 'success' : 'error', __($ok ? 'employees.login_deactivated' : 'employees.no_active_login'));
        $this->redirect('/portal/employees/' . (int) $id);
    }

    public function decideLeave(string $id): void
    {
        Auth::requirePerm('hr', 'approve');
        $a = DB::row("SELECT id FROM approvals WHERE type = 'leave' AND record_id = ? AND status = 'pending'", [(int) $id]);
        if (!$a) {
            Response::notFound();
        }
        try {
            Approvals::decide((int) $a['id'], Request::post('decision') === 'approve', mb_substr((string) Request::post('note'), 0, 500) ?: null);
            $this->flash('success', __('common.saved'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/leave-requests/' . (int) $id);
    }

    /** Daily attendance sheet: every active employee on one screen. */
    public function sheet(): void
    {
        Auth::requirePerm('hr', 'create');
        $date = (string) Request::input('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d', strtotime('+1 day'))) {
            $date = date('Y-m-d');
        }
        $employees = DB::all("SELECT e.id, e.name_en, e.emp_no, p.value_en AS position, a.id AS att_id, a.status, a.check_in, a.check_out, a.overtime_hours, a.notes
            FROM employees e LEFT JOIN lookups p ON p.id = e.position_id
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ? AND a.deleted_at IS NULL
            WHERE e.deleted_at IS NULL AND e.status IN ('active','on_leave') ORDER BY e.name_en", [$date]);
        if (Request::isPost()) {
            $rows = (array) ($_POST['att'] ?? []);
            $valid = ['present', 'absent', 'late', 'leave', 'holiday', 'sick'];
            $n = 0;
            DB::transaction(function () use ($rows, $employees, $date, $valid, &$n) {
                foreach ($employees as $e) {
                    $r = $rows[$e['id']] ?? null;
                    if (!is_array($r) || !in_array($r['status'] ?? '', $valid, true)) {
                        continue;
                    }
                    $time = fn ($v) => is_string($v) && preg_match('/^\d{2}:\d{2}$/', $v) ? $v : null;
                    $data = ['status' => $r['status'], 'check_in' => $time($r['in'] ?? null), 'check_out' => $time($r['out'] ?? null),
                             'overtime_hours' => max(0, min(24, (float) ($r['ot'] ?? 0))), 'notes' => mb_substr((string) ($r['notes'] ?? ''), 0, 255) ?: null];
                    if ($e['att_id']) {
                        DB::update('attendance', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $e['att_id']]);
                    } else {
                        HrService::purgeDeletedAttendance((int) $e['id'], $date);
                        DB::insert('attendance', $data + ['employee_id' => $e['id'], 'work_date' => $date, 'created_by' => Auth::id()]);
                    }
                    $n++;
                }
            });
            Audit::log('update', 'hr', 'attendance', null, null, ['date' => $date, 'rows' => $n], 'Attendance sheet saved');
            Cache::bump();
            $this->flash('success', __('attendance.sheet_saved', ['n' => $n]));
            $this->redirect('/portal/attendance/sheet?date=' . $date);
        }
        $this->view('portal/hr/sheet', ['title' => __('attendance.daily_sheet'), 'date' => $date, 'employees' => $employees]);
    }
}
