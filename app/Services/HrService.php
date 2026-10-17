<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

final class HrService
{
    /**
     * Next employee number in the Studio's format (SK-001, SK-002 …) from a counter that never goes back: a number
     * stays used even when its employee is deleted, so it is never given to someone else.
     */
    public static function nextEmpNo(): string
    {
        self::syncEmpCounter();
        return sprintf('SK-%03d', Sequence::next('EMP', 'all'));
    }

    /** Moves the counter past every SK- number already used (also typed by hand, or of deleted employees). */
    public static function syncEmpCounter(): void
    {
        $max = 0;
        foreach (DB::column("SELECT emp_no FROM employees WHERE emp_no LIKE 'SK-%'") as $no) {
            if (preg_match('/^SK-(\d+)$/', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        DB::run('INSERT INTO sequences (name, period, `last_value`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `last_value` = GREATEST(`last_value`, ?)', ['EMP', 'all', $max, $max]);
    }

    /** The login linked to an employee record (with its role slug), or null. */
    public static function linkedUser(int $employeeId): ?array
    {
        return DB::row('SELECT u.id, u.name, u.status, r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.employee_id = ? AND u.deleted_at IS NULL', [$employeeId]);
    }

    /**
     * Same limits as the Users screen: only the Owner switches off an Owner or General Manager login,
     * nobody switches off their own, and the last active Owner is never switched off.
     */
    public static function mayDeactivate(array $user): bool
    {
        if ((int) $user['id'] === Auth::id()) {
            return false;
        }
        if (in_array($user['slug'], ['owner', 'general_manager'], true) && !Auth::isOwner()) {
            return false;
        }
        return $user['slug'] !== 'owner'
            || (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") > 1;
    }

    /** One click from the Employee profile (and automatic when the employee leaves): login disabled, sessions ended. */
    public static function deactivateLogin(int $employeeId): bool
    {
        $u = self::linkedUser($employeeId);
        if (!$u || $u['status'] !== 'active' || !self::mayDeactivate($u)) {
            return false;
        }
        DB::update('users', ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $u['id']]);
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$u['id']]);
        Audit::log('update', 'users', 'user', $u['id'], ['status' => 'active'], ['status' => 'inactive'], 'Login deactivated from employee profile');
        Notifier::owners('security', Notifier::msg('notify.login_deactivated', ['user' => $u['name']]), null, '/portal/users/' . $u['id']);
        return true;
    }

    public static function approveLeave(int $leaveId, array $payload, array $approval): void
    {
        $l = DB::row("SELECT * FROM leave_requests WHERE id = ? AND status = 'pending' FOR UPDATE", [$leaveId]);
        if (!$l) {
            return;
        }
        if ($l['leave_type'] === 'annual') {
            // checked while the request is still pending, so the balance does not count it yet
            $emp = DB::row('SELECT * FROM employees WHERE id = ?', [$l['employee_id']]);
            if (LeaveService::balance($emp)['balance'] < (float) $l['days']) {
                throw new \DomainException(__('leave.not_enough_now'));
            }
            if (!LeaveService::calculated()) {
                DB::run('UPDATE employees SET leave_balance = leave_balance - ? WHERE id = ?', [$l['days'], $l['employee_id']]);
            }
        }
        DB::update('leave_requests', ['status' => 'approved', 'decided_by' => \App\Core\Auth::id(), 'decided_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $leaveId]);
        // Mark the leave days in attendance (existing entries are kept); the weekly day off and public holidays are not leave
        $off = LeaveService::weekendDays();
        $hol = LeaveService::holidays($l['start_date'], $l['end_date']);
        $d = new \DateTime($l['start_date']);
        $end = new \DateTime($l['end_date']);
        while ($d <= $end) {
            $day = $d->format('Y-m-d');
            if (!in_array((int) $d->format('w'), $off, true) && !isset($hol[$day])) {
                self::purgeDeletedAttendance((int) $l['employee_id'], $day);
                DB::run("INSERT IGNORE INTO attendance (employee_id, work_date, status, notes) VALUES (?, ?, 'leave', ?)", [$l['employee_id'], $day, 'Leave #' . $leaveId]);
            }
            $d->modify('+1 day');
        }
        self::notifyEmployee((int) $l['employee_id'], Notifier::msg('leave.approved_msg', ['from' => ['date' => $l['start_date']], 'to' => ['date' => $l['end_date']]]));
    }

    /**
     * Cancels a leave request: one still waiting for approval, or approved leave that has not started yet (its days go
     * back to the balance and come off the attendance sheet).
     */
    public static function cancelLeave(int $leaveId): void
    {
        DB::transaction(function () use ($leaveId) {
            $l = DB::row('SELECT * FROM leave_requests WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$leaveId]);
            if (!$l || !in_array($l['status'], ['pending', 'approved'], true)) {
                throw new \DomainException(__('leave.cannot_cancel'));
            }
            if ($l['status'] === 'approved' && $l['start_date'] <= date('Y-m-d')) {
                throw new \DomainException(__('leave.already_started'));
            }
            DB::update('leave_requests', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $leaveId]);
            Approvals::cancelFor('leave_request', $leaveId);
            if ($l['status'] === 'approved') {
                if ($l['leave_type'] === 'annual' && !LeaveService::calculated()) {
                    DB::run('UPDATE employees SET leave_balance = leave_balance + ? WHERE id = ?', [$l['days'], $l['employee_id']]);
                }
                DB::run("UPDATE attendance SET deleted_at = NOW() WHERE employee_id = ? AND status = 'leave' AND notes = ? AND deleted_at IS NULL", [$l['employee_id'], 'Leave #' . $leaveId]);
            }
            Audit::log('update', 'hr', 'leave_request', $leaveId, ['status' => $l['status']], ['status' => 'cancelled'], 'Leave cancelled');
            self::notifyEmployee((int) $l['employee_id'], Notifier::msg('leave.cancelled_msg', ['from' => ['date' => $l['start_date']]]));
        });
        Cache::bump();
    }

    public static function rejectLeave(int $leaveId, array $payload, array $approval): void
    {
        $l = DB::row('SELECT * FROM leave_requests WHERE id = ?', [$leaveId]);
        DB::update('leave_requests', ['status' => 'rejected', 'decided_by' => \App\Core\Auth::id(), 'decided_at' => date('Y-m-d H:i:s')], "id = :id AND status = 'pending'", ['id' => $leaveId]);
        if ($l) {
            self::notifyEmployee((int) $l['employee_id'], Notifier::msg('leave.rejected_msg', ['from' => ['date' => $l['start_date']]]));
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

    private static function notifyEmployee(int $employeeId, string|array $msg): void
    {
        $uid = DB::value("SELECT id FROM users WHERE employee_id = ? AND status = 'active'", [$employeeId]);
        if ($uid) {
            Notifier::user((int) $uid, 'leave', $msg, null, '/portal/account');
        }
    }
}
