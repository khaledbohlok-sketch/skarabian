<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Approvals;

/** Leave requests: the manager / HR approves (never the employee); approved annual leave reduces the balance. */
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

    public function fields(): array
    {
        return [
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'required' => true, 'no_add' => true],
            'leave_type'  => ['type' => 'select', 'label' => 'common.type', 'options' => self::TYPES, 'required' => true, 'default' => 'annual', 'col' => 4],
            'start_date'  => ['type' => 'date', 'label' => 'common.from', 'required' => true, 'col' => 4],
            'end_date'    => ['type' => 'date', 'label' => 'common.to', 'required' => true, 'col' => 4],
            'days'        => ['type' => 'decimal', 'label' => 'leave.days', 'min' => 0.5, 'col' => 4, 'help' => 'leave.days_help'],
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
            'employee' => ['label' => 'employees.singular', 'sql' => 'e.name_en', 'sort' => true, 'hide_in_parent' => true],
            'type'     => ['label' => 'common.type', 'sql' => 't.leave_type', 'fmt' => 'enum', 'prefix' => 'leave.type'],
            'start'    => ['label' => 'common.from', 'sql' => 't.start_date', 'fmt' => 'date', 'sort' => true],
            'end'      => ['label' => 'common.to', 'sql' => 't.end_date', 'fmt' => 'date'],
            'days'     => ['label' => 'leave.days', 'sql' => 't.days', 'fmt' => 'num'],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'      => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => ['pending' => 'status.pending', 'approved' => 'approvals.status_approved', 'rejected' => 'status.rejected', 'cancelled' => 'status.cancelled']],
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'sql' => 't.employee_id'],
            'type'        => ['type' => 'select', 'label' => 'common.type', 'sql' => 't.leave_type', 'options' => self::TYPES],
        ];
    }

    public function label(array $row): string
    {
        return (string) DB::value('SELECT name_en FROM employees WHERE id = ?', [$row['employee_id']]) . ' — ' . fmt_date($row['start_date']);
    }

    public function canEdit(array $row): bool
    {
        return $row['status'] === 'pending' && parent::canEdit($row);
    }

    public function prepare(array $data, ?array $old): array
    {
        if ($data['end_date'] < $data['start_date']) {
            throw ValidationException::one('end_date', 'leave.end_before_start');
        }
        if (empty($data['days'])) {
            $data['days'] = (float) ((new \DateTime($data['start_date']))->diff(new \DateTime($data['end_date']))->days + 1);
        }
        $overlap = DB::value("SELECT id FROM leave_requests WHERE employee_id = ? AND status IN ('pending','approved') AND deleted_at IS NULL AND id <> ? AND start_date <= ? AND end_date >= ?",
            [$data['employee_id'], $old['id'] ?? 0, $data['end_date'], $data['start_date']]);
        if ($overlap) {
            throw ValidationException::one('start_date', 'leave.overlap');
        }
        if ($data['leave_type'] === 'annual') {
            // Balance left after other annual leave that is still waiting for approval
            $balance = (float) DB::value('SELECT leave_balance FROM employees WHERE id = ?', [$data['employee_id']]);
            $pending = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'pending' AND deleted_at IS NULL AND id <> ?",
                [$data['employee_id'], $old['id'] ?? 0]);
            if ((float) $data['days'] > $balance - $pending) {
                throw ValidationException::one('days', 'leave.not_enough', ['left' => rtrim(rtrim(number_format(max(0, $balance - $pending), 1), '0'), '.')]);
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
        return $a;
    }
}
