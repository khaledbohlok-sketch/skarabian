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
use App\Services\AttendanceCard;
use App\Services\AttendanceScan;
use App\Services\Cache;
use App\Services\HrService;

class HrController extends Controller
{
    public function deactivateLogin(string $id): void
    {
        if (!Auth::can('hr', 'edit') && !Auth::can('users', 'edit')) {
            Auth::deny('hr.edit');
        }
        $linked = HrService::linkedUser((int) $id);
        if ($linked && !HrService::mayDeactivate($linked)) {
            Auth::deny('users.edit');
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

    /** HR cancels a leave request still waiting for approval, or approved leave that has not started yet. */
    public function cancelLeave(string $id): void
    {
        Auth::requireLogin();
        if (!Auth::can('hr', 'edit') && !Auth::can('hr', 'approve')) {
            Auth::deny('hr.edit');
        }
        try {
            HrService::cancelLeave((int) $id);
            $this->flash('success', __('leave.cancelled'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/leave-requests/' . (int) $id);
    }

    /** "Reissue card": a lost ID card stops working at the gate; the new card is printed in Studio → Staff ID Cards. */
    public function reissueCard(string $id): void
    {
        Auth::requirePerm('hr', 'edit');
        $e = DB::row('SELECT * FROM employees WHERE id = ? AND deleted_at IS NULL', [(int) $id]) ?? Response::notFound();
        if (!AttendanceCard::available()) {
            $this->flash('error', __('attendance.needs_update'));
            $this->redirect('/portal/employees/' . $e['id']);
        }
        $n = AttendanceCard::reissue((int) $e['id'], (string) $e['name_en']);
        $this->flash('success', __('attendance.card_reissued', ['n' => $n]));
        $this->redirect('/portal/employees/' . $e['id']);
    }

    /** Daily attendance sheet: every active employee on one screen. */
    public function sheet(): void
    {
        Auth::requirePerm('hr', 'create');
        $date = (string) Request::input('date', date('Y-m-d'));
        if (!valid_date($date) || $date > date('Y-m-d', strtotime('+1 day'))) {
            $date = date('Y-m-d');
        }
        $scans = AttendanceScan::available();
        $employees = DB::all("SELECT e.id, e.name_en, e.name_ar, e.emp_no, p.value_en AS position, a.id AS att_id, a.status, a.check_in, a.check_out, a.overtime_hours, a.notes"
            . ($scans ? ', a.source' : '') . "
            FROM employees e LEFT JOIN lookups p ON p.id = e.position_id
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ? AND a.deleted_at IS NULL
            WHERE e.deleted_at IS NULL AND e.status IN ('active','on_leave') ORDER BY e.name_en", [$date]);
        if (Request::isPost()) {
            $rows = (array) ($_POST['att'] ?? []);
            $valid = ['present', 'absent', 'late', 'leave', 'holiday', 'sick'];
            $n = 0;
            DB::transaction(function () use ($rows, $employees, $date, $valid, $scans, &$n) {
                $time = fn ($v) => is_string($v) && preg_match('/^\d{2}:\d{2}$/', $v) ? $v : null;
                $values = fn (array $r, string $p) => ['status' => (string) ($r[$p . 'status'] ?? ''), 'check_in' => $time($r[$p . 'in'] ?? null), 'check_out' => $time($r[$p . 'out'] ?? null),
                    'overtime_hours' => max(0, min(24, (float) ($r[$p . 'ot'] ?? 0))), 'notes' => mb_substr(trim((string) ($r[$p . 'notes'] ?? '')), 0, 255) ?: null];
                foreach ($employees as $e) {
                    $r = $rows[$e['id']] ?? null;
                    if (!is_array($r) || !in_array($r['status'] ?? '', $valid, true)) {
                        continue;
                    }
                    $data = $values($r, '');
                    // Only what was changed on the page is written: a box left as it was shown keeps what is saved now
                    // (a gate scan may have come in after the page was opened). A page without the shown values writes all.
                    $changed = array_key_exists('o_status', $r)
                        ? array_keys(array_filter($data, fn ($v, $k) => (string) $v !== (string) $values($r, 'o_')[$k], ARRAY_FILTER_USE_BOTH))
                        : array_keys($data);
                    $now = DB::row('SELECT * FROM attendance WHERE employee_id = ? AND work_date = ? AND deleted_at IS NULL FOR UPDATE', [$e['id'], $date]);
                    if ($now) {
                        if (!$changed) {
                            continue;
                        }
                        $upd = array_intersect_key($data, array_flip($changed)) + ['updated_at' => date('Y-m-d H:i:s')];
                        if ($scans) {
                            $upd['source'] = 'manual'; // checked by a person
                        }
                        DB::update('attendance', $upd, 'id = :id', ['id' => $now['id']]);
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
        $this->view('portal/hr/sheet', [
            'title' => __('attendance.daily_sheet'), 'date' => $date, 'employees' => $employees, 'scans' => $scans,
            'working' => AttendanceScan::isWorkingDay($date), 'end' => AttendanceScan::end(),
            'canScan' => $scans && Auth::can('attendance_scan', 'create'), 'canSettings' => $scans && Auth::can('hr', 'edit'),
        ]);
    }
}
