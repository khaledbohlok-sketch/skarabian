<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;

final class HrService
{
    /** Next employee number in the Studio's format: SK-001, SK-002 ... */
    public static function nextEmpNo(): string
    {
        $max = 0;
        foreach (DB::column("SELECT emp_no FROM employees WHERE emp_no LIKE 'SK-%'") as $no) {
            if (preg_match('/(\d+)$/', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('SK-%03d', $max + 1);
    }

    /** One click from the Employee profile (and automatic when the employee leaves): login disabled, sessions ended. */
    public static function deactivateLogin(int $employeeId): bool
    {
        $u = DB::row("SELECT id, name, status FROM users WHERE employee_id = ? AND deleted_at IS NULL", [$employeeId]);
        if (!$u || $u['status'] !== 'active') {
            return false;
        }
        DB::update('users', ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $u['id']]);
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$u['id']]);
        Audit::log('update', 'users', 'user', $u['id'], ['status' => 'active'], ['status' => 'inactive'], 'Login deactivated from employee profile');
        Notifier::owners('security', __('notify.login_deactivated', ['user' => $u['name']]), null, '/portal/users/' . $u['id']);
        return true;
    }

    public static function approveLeave(int $leaveId, array $payload, array $approval): void
    {
        $l = DB::row("SELECT * FROM leave_requests WHERE id = ? AND status = 'pending' FOR UPDATE", [$leaveId]);
        if (!$l) {
            return;
        }
        DB::update('leave_requests', ['status' => 'approved', 'decided_by' => \App\Core\Auth::id(), 'decided_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $leaveId]);
        if ($l['leave_type'] === 'annual') {
            if ((float) DB::value('SELECT leave_balance FROM employees WHERE id = ?', [$l['employee_id']]) < (float) $l['days']) {
                throw new \DomainException(__('leave.not_enough_now'));
            }
            DB::run('UPDATE employees SET leave_balance = leave_balance - ? WHERE id = ?', [$l['days'], $l['employee_id']]);
        }
        // Mark the days as leave in attendance (existing entries are kept)
        $d = new \DateTime($l['start_date']);
        $end = new \DateTime($l['end_date']);
        while ($d <= $end) {
            self::purgeDeletedAttendance((int) $l['employee_id'], $d->format('Y-m-d'));
            DB::run("INSERT IGNORE INTO attendance (employee_id, work_date, status, notes) VALUES (?, ?, 'leave', ?)", [$l['employee_id'], $d->format('Y-m-d'), 'Leave #' . $leaveId]);
            $d->modify('+1 day');
        }
        self::notifyEmployee((int) $l['employee_id'], __('leave.approved_msg', ['from' => fmt_date($l['start_date']), 'to' => fmt_date($l['end_date'])]));
    }

    public static function rejectLeave(int $leaveId, array $payload, array $approval): void
    {
        $l = DB::row('SELECT * FROM leave_requests WHERE id = ?', [$leaveId]);
        DB::update('leave_requests', ['status' => 'rejected', 'decided_by' => \App\Core\Auth::id(), 'decided_at' => date('Y-m-d H:i:s')], "id = :id AND status = 'pending'", ['id' => $leaveId]);
        if ($l) {
            self::notifyEmployee((int) $l['employee_id'], __('leave.rejected_msg', ['from' => fmt_date($l['start_date'])]));
        }
    }

    /**
     * Attendance has one row per employee per day. A row sitting in Trash for the same day is removed for good
     * before a new one is written, so the day can be recorded again.
     */
    public static function purgeDeletedAttendance(int $employeeId, string $date): void
    {
        $id = DB::value('SELECT id FROM attendance WHERE employee_id = ? AND work_date = ? AND deleted_at IS NOT NULL', [$employeeId, $date]);
        if ($id) {
            DB::run("DELETE FROM trash WHERE record_table = 'attendance' AND record_id = ?", [$id]);
            DB::run('DELETE FROM attendance WHERE id = ?', [$id]);
        }
    }

    private static function notifyEmployee(int $employeeId, string $msg): void
    {
        $uid = DB::value("SELECT id FROM users WHERE employee_id = ? AND status = 'active'", [$employeeId]);
        if ($uid) {
            Notifier::user((int) $uid, 'leave', $msg, null, '/portal/account');
        }
    }
}
