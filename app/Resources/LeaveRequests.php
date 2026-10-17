<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Approvals;
use App\Services\LeaveService;

/**
 * Leave requests: the manager / HR approves (never the employee); approved annual leave reduces the balance.
 * Days are working days (the weekly day off and public holidays are not counted); each request keeps whether its
 * type is paid. Statuses: pending, approved, rejected, cancelled.
 */
class LeaveRequests extends Resource
{
    public string $key = 'leave-requests';
    public string $table = 'leave_requests';
    public string $module = 'hr';
    public string $recordType = 'leave_request';
    public string $title = 'nav.leave';
    public string $singular = 'leave.singular';
    public array $search = ['e.name_en', 't.reason'];
    public string $sort = "FIELD(t.status,'pending','approved','rejected','cancelled'), t.start_date DESC";
    public ?string $parentField = 'employee_id';
    public ?string $parentResource = 'employees';

    public const TYPES = ['annual' => 'leave.type_annual', 'sick' => 'leave.type_sick', 'unpaid' => 'leave.type_unpaid', 'emergency' => 'leave.type_emergency', 'other' => 'leave.type_other'];
    public const STATUSES = ['pending' => 'leave.status_pending', 'approved' => 'leave.status_approved', 'rejected' => 'leave.status_rejected', 'cancelled' => 'leave.status_cancelled'];

    public function fields(): array
    {
        return [
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'required' => true, 'no_add' => true],
            'leave_type'  => ['type' => 'segment', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'annual', 'col' => 12],
            'start_date'  => ['type' => 'date', 'label' => 'common.from', 'required' => true, 'col' => 4],
            'end_date'    => ['type' => 'date', 'label' => 'common.to', 'required' => true, 'col' => 4],
            'days'        => ['type' => 'decimal', 'label' => 'leave.days', 'min' => 0.5, 'col' => 4, 'help' => 'leave.days_help', 'unit' => 'days'],
            'reason'      => ['type' => 'text', 'label' => 'leave.reason', 'max' => 255, 'col' => 8],
        ];
    }

    public function from(): string
    {
        return '`leave_requests` t JOIN employees e ON e.id = t.employee_id';
    }

    public function columns(): array
    {
        return [
            'employee' => ['label' => 'employees.singular', 'sql' => self::nameSql('e'), 'sort' => true, 'hide_in_parent' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.leave_type', 'fmt' => 'enum', 'prefix' => 'leave.type'],
            'start'    => ['label' => 'common.from', 'sql' => 't.start_date', 'fmt' => 'date', 'sort' => true],
            'end'      => ['label' => 'common.to', 'sql' => 't.end_date', 'fmt' => 'date'],
            'days'     => ['label' => 'leave.days', 'sql' => 't.days', 'fmt' => 'num'],
        ] + (DB::hasColumn('leave_requests', 'is_paid') ? ['paid' => ['label' => 'leave.paid_col', 'sql' => 'COALESCE(t.is_paid, 1)', 'fmt' => 'enum', 'prefix' => 'leave.paid']] : []) + [
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'leave.status'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'      => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'sql' => 't.employee_id'],
            'type'        => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.leave_type', 'options' => self::TYPES],
        ] + (DB::hasColumn('leave_requests', 'is_paid') ? ['paid' => ['type' => 'select', 'label' => 'leave.paid_col', 'sql' => 'COALESCE(t.is_paid, 1)', 'options' => ['1' => 'leave.paid_1', '0' => 'leave.paid_0']]] : []);
    }

    public function headerBadges(array $row): string
    {
        return status_badge($row['status'], 'leave.status') . (isset($row['is_paid']) && (int) $row['is_paid'] === 0 ? ' <span class="badge">' . e(__('leave.paid_0')) . '</span>' : '');
    }

    public function label(array $row): string
    {
        return (string) DB::value('SELECT name_en FROM employees WHERE id = ?', [$row['employee_id']]) . ' — ' . fmt_date($row['start_date']);
    }

    public function canEdit(array $row): bool
    {
        return $row['status'] === 'pending' && parent::canEdit($row);
    }

    /**
     * Approved leave can't be deleted: the balance was already taken and the days were marked as leave in
     * attendance, which deleting would leave behind. The Delete button is hidden for it; afterDelete() is the
     * hard stop (e.g. a deletion requested while the leave was pending and approved by the Owner afterwards).
     */
    public function canDelete(array $row): bool
    {
        return ($row['status'] ?? '') !== 'approved' && parent::canDelete($row);
    }

    public function afterDelete(array $row): void
    {
        $status = DB::value('SELECT status FROM leave_requests WHERE id = ?', [$row['id']]);
        if ($status === 'approved') {
            throw new \DomainException(__('leave.cannot_delete_approved'));
        }
        // A deleted pending request no longer waits for a decision
        DB::run("UPDATE approvals SET status = 'cancelled', decided_at = NOW(), decided_by = ? WHERE type = 'leave' AND record_type = 'leave_request' AND record_id = ? AND status = 'pending'", [Auth::id(), $row['id']]);
    }

    /** Brought back from the Trash while still pending: it waits for a decision again (deleting it cancelled its request). */
    public function afterRestore(array $row): void
    {
        if (($row['status'] ?? '') === 'pending') {
            $name = DB::value('SELECT name_en FROM employees WHERE id = ?', [$row['employee_id']]);
            $days = rtrim(rtrim(number_format((float) $row['days'], 1), '0'), '.');
            Approvals::request('leave', 'leave_request', (int) $row['id'], __('leave.approval_title', ['name' => $name, 'days' => $days]), null, [], (int) ($row['created_by'] ?? 0) ?: null);
        }
    }

    public function prepare(array $data, ?array $old): array
    {
        if ($data['end_date'] < $data['start_date']) {
            throw ValidationException::one('end_date', 'leave.end_before_start');
        }
        // new dates on a request with the old number of days: counted again (the form shows the number it had)
        $redated = $old && ($old['start_date'] !== $data['start_date'] || $old['end_date'] !== $data['end_date'])
            && abs((float) ($data['days'] ?? 0) - (float) $old['days']) < 0.001;
        if (empty($data['days']) || $redated) {
            // working days only: the weekly day off and public holidays are not leave
            $data['days'] = LeaveService::workingDays($data['start_date'], $data['end_date']);
            if ($data['days'] <= 0) {
                throw ValidationException::one('end_date', 'leave.no_working_days');
            }
        }
        $overlap = DB::value("SELECT id FROM leave_requests WHERE employee_id = ? AND status IN ('pending','approved') AND deleted_at IS NULL AND id <> ? AND start_date <= ? AND end_date >= ?",
            [$data['employee_id'], $old['id'] ?? 0, $data['end_date'], $data['start_date']]);
        if ($overlap) {
            throw ValidationException::one('start_date', 'leave.overlap');
        }
        if (DB::hasColumn('leave_requests', 'is_paid') && (!$old || $old['leave_type'] !== $data['leave_type'] || $old['is_paid'] === null)) {
            $data['is_paid'] = LeaveService::isPaid($data['leave_type']) ? 1 : 0; // the type's setting at the time of the request
        }
        if ($data['leave_type'] === 'annual') {
            // What is left after the other annual leave still waiting for approval
            $emp = DB::row('SELECT * FROM employees WHERE id = ?', [$data['employee_id']]);
            $pos = $emp ? LeaveService::balance($emp) : ['balance' => 0.0, 'pending' => 0.0];
            $own = $old && $old['leave_type'] === 'annual' && $old['status'] === 'pending' ? (float) $old['days'] : 0.0;
            $left = $pos['balance'] - ($pos['pending'] - $own);
            if ((float) $data['days'] > $left) {
                throw ValidationException::one('days', 'leave.not_enough', ['left' => rtrim(rtrim(number_format(max(0, $left), 1), '0'), '.')]);
            }
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created) {
            $name = DB::value('SELECT name_en FROM employees WHERE id = ?', [$data['employee_id']]);
            Approvals::request('leave', 'leave_request', $id, __('leave.approval_title', ['name' => $name, 'days' => $data['days']]));
        }
    }

    public function actions(array $row): array
    {
        $a = [];
        if ($row['status'] === 'pending') {
            $ap = DB::row("SELECT * FROM approvals WHERE type = 'leave' AND record_id = ? AND status = 'pending'", [$row['id']]);
            if ($ap && \App\Services\Approvals::canDecide($ap)) {
                $url = '/portal/leave-requests/' . $row['id'] . '/decide';
                $a[] = ['label' => 'common.approve', 'url' => $url, 'method' => 'post', 'class' => 'btn-primary', 'fields' => ['decision' => 'approve']];
                $a[] = ['label' => 'common.reject', 'url' => $url, 'method' => 'post', 'class' => 'btn-danger', 'fields' => ['decision' => 'reject'], 'confirm' => 'approvals.confirm_reject'];
            }
        }
        if ($row['status'] === 'approved' && \App\Services\Studio::canCreate('letters')) {
            $a[] = ['label' => 'leave.print_approval', 'url' => '/portal/studio/new/letters?type=leave&record_id=' . $row['employee_id'] . '&leave_id=' . $row['id']];
        }
        // HR cancels a request still waiting, or approved leave that has not started (its days come back)
        if (($row['status'] === 'pending' || ($row['status'] === 'approved' && $row['start_date'] > date('Y-m-d'))) && (Auth::can('hr', 'edit') || Auth::can('hr', 'approve'))) {
            $a[] = ['label' => 'leave.cancel', 'url' => '/portal/leave-requests/' . $row['id'] . '/cancel', 'method' => 'post', 'class' => 'btn', 'confirm' => 'leave.confirm_cancel'];
        }
        return $a;
    }
}
