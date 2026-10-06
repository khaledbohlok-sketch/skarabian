<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\DB;
use App\Core\ValidationException;

class Attendance extends Resource
{
    public string $key = 'attendance';
    public string $table = 'attendance';
    public string $module = 'hr';
    public string $recordType = 'attendance';
    public string $title = 'nav.attendance';
    public string $singular = 'attendance.singular';
    public array $search = ['e.name_en', 'e.emp_no'];
    public string $sort = 't.work_date DESC, e.name_en';
    public ?string $parentField = 'employee_id';
    public ?string $parentResource = 'employees';

    public const STATUSES = ['present' => 'attendance.status_present', 'absent' => 'attendance.status_absent', 'late' => 'attendance.status_late', 'leave' => 'attendance.status_leave', 'holiday' => 'attendance.status_holiday', 'sick' => 'attendance.status_sick'];

    public function listActions(): array
    {
        return [['attendance.daily_sheet', '/portal/attendance/sheet', '']];
    }

    public function fields(): array
    {
        return [
            'employee_id'    => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'required' => true, 'no_add' => true],
            'work_date'      => ['type' => 'date', 'label' => 'common.date', 'required' => true, 'default' => fn () => date('Y-m-d'), 'col' => 4],
            'status'         => ['type' => 'select', 'label' => 'common.status', 'options' => self::STATUSES, 'required' => true, 'default' => 'present', 'col' => 4],
            'overtime_hours' => ['type' => 'decimal', 'label' => 'attendance.overtime', 'min' => 0, 'col' => 4, 'default' => 0, 'empty' => 0],
            'check_in'       => ['type' => 'time', 'label' => 'attendance.check_in', 'col' => 6],
            'check_out'      => ['type' => 'time', 'label' => 'attendance.check_out', 'col' => 6],
            'notes'          => ['type' => 'text', 'label' => 'common.notes', 'max' => 255, 'col' => 12],
        ];
    }

    public function from(): string
    {
        return '`attendance` t JOIN employees e ON e.id = t.employee_id';
    }

    public function columns(): array
    {
        return [
            'date'     => ['label' => 'common.date', 'sql' => 't.work_date', 'fmt' => 'date', 'sort' => true],
            'employee' => ['label' => 'employees.singular', 'sql' => 'e.name_en', 'sort' => true, 'hide_in_parent' => true],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'attendance.status'],
            'in'       => ['label' => 'attendance.check_in', 'sql' => "TIME_FORMAT(t.check_in, '%H:%i')"],
            'out'      => ['label' => 'attendance.check_out', 'sql' => "TIME_FORMAT(t.check_out, '%H:%i')"],
            'ot'       => ['label' => 'attendance.overtime', 'sql' => 't.overtime_hours', 'fmt' => 'num'],
        ];
    }

    public function filters(): array
    {
        return [
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'employees.singular', 'sql' => 't.employee_id'],
            'status'      => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'from'        => ['type' => 'date_from', 'label' => 'common.from', 'sql' => 't.work_date'],
            'to'          => ['type' => 'date_to', 'label' => 'common.to', 'sql' => 't.work_date'],
        ];
    }

    public function label(array $row): string
    {
        return (string) DB::value('SELECT name_en FROM employees WHERE id = ?', [$row['employee_id']]) . ' — ' . fmt_date($row['work_date']);
    }

    public function prepare(array $data, ?array $old): array
    {
        if (DB::value('SELECT id FROM attendance WHERE employee_id = ? AND work_date = ? AND id <> ? AND deleted_at IS NULL', [$data['employee_id'], $data['work_date'], $old['id'] ?? 0])) {
            throw ValidationException::one('work_date', 'attendance.duplicate');
        }
        if (!empty($data['check_in']) && !empty($data['check_out']) && $data['check_out'] < $data['check_in']) {
            throw ValidationException::one('check_out', 'attendance.out_before_in');
        }
        \App\Services\HrService::purgeDeletedAttendance((int) $data['employee_id'], (string) $data['work_date']);
        return $data;
    }
}
