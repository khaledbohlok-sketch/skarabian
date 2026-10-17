<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Attendance at the gate (database update 024): a scanned ID card becomes the day's check-in or check-out, with the
 * server's time. The first scan of the day checks in — "late" after the usual start + grace and before the usual end
 * (a later check-in is an evening or night shift) — the next one checks out, and a check-out after midnight belongs to
 * the shift that started the day before (up to hr.max_shift_hours). Times already on the row, typed by a manager or
 * scanned before, are never replaced; a row on leave / sick / holiday / absent keeps its status for the manager.
 * Overtime stays what the manager types: payroll reads the attendance table exactly as before.
 */
final class AttendanceScan
{
    public const REPEAT_SECONDS = 120;  // a second scan within 2 minutes is ignored ("already scanned")
    public const OFFLINE_HOURS = 12;    // scans kept on the gate phone without internet: accepted up to 12 hours later
    public const MIN_SHIFT_MINUTES = 30; // a scan within 30 minutes of the check-in is not a check-out ("already checked in")
    /** How a row's times came in: qr_offline and typed are marked for the manager until the row is edited on the sheet. */
    public const TO_CHECK = ['qr_offline', 'typed'];
    /** Tests only: a fixed "now" (Unix time); null = the server's clock. */
    public static ?int $clock = null;

    public static function available(): bool
    {
        return AttendanceCard::available() && DB::hasColumn('attendance', 'source') && DB::hasColumn('attendance_scans', 'id');
    }

    public static function start(): string
    {
        return self::hhmm((string) Settings::get('hr.default_in', '06:00'), '06:00');
    }

    public static function end(): string
    {
        return self::hhmm((string) Settings::get('hr.default_out', '15:00'), '15:00');
    }

    public static function grace(): int
    {
        return max(0, min(240, (int) Settings::get('hr.grace_minutes', '10')));
    }

    public static function maxShift(): int
    {
        return max(8, min(24, (int) Settings::get('hr.max_shift_hours', '16')));
    }

    /** Status of a check-in at HH:MM: late after start + grace and before the usual end, otherwise present. */
    public static function statusFor(string $hhmm): string
    {
        $limit = date('H:i', (int) strtotime('2000-01-01 ' . self::start() . ' +' . self::grace() . ' minutes'));
        return $hhmm > $limit && $hhmm < self::end() ? 'late' : 'present';
    }

    /**
     * One scanned card. $qr is the text read from the card; $source 'qr', or 'qr_offline' with $at = the gate phone's
     * time for a scan kept while it had no internet. Returns what the gate screen shows:
     * result in | late | out | warning (recorded, the manager will check) | ignored | rejected, with the reason.
     */
    public static function scan(string $qr, string $source = 'qr', ?int $at = null, ?string $device = null): array
    {
        [$empId, $cardNo, $problem] = AttendanceCard::parse($qr);
        if ($problem !== '') {
            return self::refuse(null, $problem, $source, $at, $device, null);
        }
        return self::record((int) $empId, (int) $cardNo, $source === 'qr_offline' ? 'qr_offline' : 'qr', $at, $device);
    }

    /** A staff number typed at the gate (the caller has checked the manager PIN): as a scan of the employee's card. */
    public static function typed(string $empNo, ?string $device = null): array
    {
        $empNo = trim($empNo);
        $id = $empNo === '' ? 0 : (int) DB::value('SELECT id FROM employees WHERE emp_no = ? AND deleted_at IS NULL', [$empNo]);
        return $id ? self::record($id, null, 'typed', null, $device) : self::refuse(null, 'unknown', 'typed', null, $device, null);
    }

    private static function record(int $empId, ?int $cardNo, string $source, ?int $at, ?string $device): array
    {
        $now = self::$clock ?? time();
        $emp = DB::row('SELECT id, name_en, name_ar, emp_no, status, card_no, photo_id FROM employees WHERE id = ? AND deleted_at IS NULL', [$empId]);
        if (!$emp) {
            return self::refuse(null, 'unknown', $source, null, $device, $cardNo);
        }
        if ($source !== 'qr_offline') {
            $at = $now; // the server's clock, whatever the phone says
        } elseif ($at === null || $at < $now - self::OFFLINE_HOURS * 3600 || $at > $now + 300) {
            return self::refuse($emp, $at !== null && $at > $now ? 'clock' : 'too_old', $source, null, $device, $cardNo);
        }
        if (in_array($emp['status'], ['left', 'suspended'], true)) {
            return self::refuse($emp, 'not_active', $source, $at, $device, $cardNo);
        }
        if ($cardNo !== null && $cardNo !== (int) $emp['card_no']) {
            return self::refuse($emp, 'reissued', $source, $at, $device, $cardNo);
        }
        return DB::transaction(function () use ($emp, $cardNo, $source, $at, $device) {
            $id = (int) $emp['id'];
            DB::value('SELECT id FROM employees WHERE id = ? FOR UPDATE', [$id]); // one scan at a time per person
            $last = DB::value("SELECT MAX(scanned_at) FROM attendance_scans WHERE employee_id = ? AND action IN ('in','out')", [$id]);
            if ($last && abs($at - (int) strtotime((string) $last)) < self::REPEAT_SECONDS) {
                return self::finish($emp, 'ignored', 'repeat', $source, $at, null, $device, $cardNo);
            }
            $date = date('Y-m-d', $at);
            $time = date('H:i:s', $at);
            $row = self::row($id, $date);

            // 1. checked in today and not out yet: this is the check-out (a scan soon after the check-in is a mistake)
            if ($row && $row['check_in'] !== null && $row['check_out'] === null) {
                if ($at - (int) strtotime($date . ' ' . $row['check_in']) < self::MIN_SHIFT_MINUTES * 60) {
                    return self::finish($emp, 'ignored', 'just_in', $source, $at, $date, $device, $cardNo);
                }
                self::save($row, ['check_out' => $time], $source);
                return self::finish($emp, 'out', '', $source, $at, $date, $device, $cardNo);
            }
            // 2. no check-in today: a night shift still open from the day before ends now
            if (!$row || $row['check_in'] === null) {
                $yDate = date('Y-m-d', (int) strtotime($date . ' -1 day'));
                $y = self::row($id, $yDate);
                if ($y && $y['check_in'] !== null && $y['check_out'] === null) {
                    $since = $at - (int) strtotime($yDate . ' ' . $y['check_in']);
                    if ($since > 0 && $since <= self::maxShift() * 3600) {
                        self::save($y, ['check_out' => $time], $source);
                        return self::finish($emp, 'out', 'night', $source, $at, $yDate, $device, $cardNo);
                    }
                }
            }
            // 3. in and out already today: the manager corrects it on the sheet if needed
            if ($row && $row['check_in'] !== null) {
                return self::finish($emp, 'ignored', 'already_out', $source, $at, $date, $device, $cardNo);
            }
            // 4. the check-in
            $status = self::statusFor(substr($time, 0, 5));
            if ($row) {
                if (!in_array($row['status'], ['present', 'late'], true)) { // on leave / sick / holiday / absent: kept for the manager
                    self::save($row, ['check_in' => $time], $source);
                    return self::finish($emp, 'in', 'status_' . $row['status'], $source, $at, $date, $device, $cardNo, 'warning');
                }
                self::save($row, ['check_in' => $time, 'status' => $row['status'] === 'late' ? 'late' : $status], $source);
            } else {
                HrService::purgeDeletedAttendance($id, $date);
                DB::insert('attendance', ['employee_id' => $id, 'work_date' => $date, 'status' => $status, 'check_in' => $time, 'source' => $source, 'created_by' => Auth::id()]);
            }
            return self::finish($emp, 'in', $status === 'late' ? 'late' : '', $source, $at, $date, $device, $cardNo, $status === 'late' ? 'late' : 'in');
        });
    }

    private static function row(int $employeeId, string $date): ?array
    {
        return DB::row('SELECT * FROM attendance WHERE employee_id = ? AND work_date = ? AND deleted_at IS NULL', [$employeeId, $date]);
    }

    /** Fills times on an existing row. A row waiting to be checked (offline / typed) keeps that mark. */
    private static function save(array $row, array $data, string $source): void
    {
        $data['source'] = in_array($row['source'], self::TO_CHECK, true) ? $row['source'] : $source;
        DB::update('attendance', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
    }

    /** Logs the scan and returns what the gate screen shows. */
    private static function finish(array $emp, string $action, string $reason, string $source, int $at, ?string $date, ?string $device, ?int $cardNo, ?string $result = null): array
    {
        self::log((int) $emp['id'], $action, $reason, $source, $at, $date, $device, $cardNo);
        return [
            'result' => $result ?? $action, 'reason' => $reason, 'time' => date('H:i', $at), 'work_date' => $date,
            'employee' => ['id' => (int) $emp['id'], 'name_en' => (string) $emp['name_en'], 'name_ar' => (string) $emp['name_ar'], 'emp_no' => (string) $emp['emp_no'], 'photo' => !empty($emp['photo_id'])],
        ];
    }

    /** A refused scan: logged (and in the Activity log), nothing else changes. */
    private static function refuse(?array $emp, string $reason, string $source, ?int $at, ?string $device, ?int $cardNo): array
    {
        $at ??= self::$clock ?? time();
        self::log($emp ? (int) $emp['id'] : null, 'rejected', $reason, $source, $at, null, $device, $cardNo);
        Audit::log('attendance_scan', 'hr', 'employee', $emp ? (int) $emp['id'] : null, null, ['reason' => $reason, 'source' => $source, 'card_no' => $cardNo],
            'Gate scan refused: ' . $reason . ($emp ? ' — ' . $emp['name_en'] : ''));
        return [
            'result' => 'rejected', 'reason' => $reason, 'time' => date('H:i', $at), 'work_date' => null,
            'employee' => $emp ? ['id' => (int) $emp['id'], 'name_en' => (string) $emp['name_en'], 'name_ar' => (string) $emp['name_ar'], 'emp_no' => (string) $emp['emp_no'], 'photo' => !empty($emp['photo_id'])] : null,
        ];
    }

    private static function log(?int $empId, string $action, string $reason, string $source, int $at, ?string $date, ?string $device, ?int $cardNo): void
    {
        DB::insert('attendance_scans', ['employee_id' => $empId, 'work_date' => $date, 'scanned_at' => date('Y-m-d H:i:s', $at), 'action' => $action,
            'reason' => $reason !== '' ? $reason : null, 'source' => $source, 'card_no' => $cardNo, 'device' => $device !== null ? mb_substr($device, 0, 80) : null, 'user_id' => Auth::id()]);
    }

    // ------------------------------------------------------------------ the manager's view

    /** A working day: not the weekly day off, not a public holiday. */
    public static function isWorkingDay(string $date): bool
    {
        return !in_array((int) date('w', (int) strtotime($date)), LeaveService::weekendDays(), true) && !isset(LeaveService::holidays($date, $date)[$date]);
    }

    /**
     * One day at a glance: active staff with no record (on a working day), late check-ins, check-ins without a
     * check-out, and rows from offline or typed scans still to check.
     * @return array{working: bool, no_record: array, late: array, no_check_out: array, to_check: array}
     */
    public static function day(string $date): array
    {
        $out = ['working' => self::isWorkingDay($date), 'no_record' => [], 'late' => [], 'no_check_out' => [], 'to_check' => []];
        $src = DB::hasColumn('attendance', 'source') ? 'a.source' : "'manual'";
        foreach (DB::all("SELECT e.id, e.name_en, e.name_ar, e.emp_no, a.status, a.check_in, a.check_out, $src AS source
                FROM employees e LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ? AND a.deleted_at IS NULL
                WHERE e.deleted_at IS NULL AND e.status = 'active' AND (e.hire_date IS NULL OR e.hire_date <= ?) ORDER BY e.name_en", [$date, $date]) as $e) {
            if ($e['status'] === null) {
                if ($out['working']) {
                    $out['no_record'][] = $e;
                }
                continue;
            }
            if ($e['status'] === 'late') {
                $out['late'][] = $e;
            }
            if ($e['check_in'] !== null && $e['check_out'] === null && in_array($e['status'], ['present', 'late'], true)) {
                $out['no_check_out'][] = $e;
            }
            if (in_array($e['source'], self::TO_CHECK, true)) {
                $out['to_check'][] = $e;
            }
        }
        return $out;
    }

    /**
     * Before payroll: for each person, what to look at in the month (until today) — working days with no record, check-ins
     * without a check-out, late days, "absent" on a day of approved leave, rows from offline / typed scans, and the
     * overtime hours next to the month before. Only people with something to look at are listed.
     * @return list<array{employee: array, no_record: list<string>, no_check_out: list<string>, late: list<string>, absent_on_leave: list<string>, to_check: list<string>, overtime: float, overtime_before: float}>
     */
    public static function month(string $month): array
    {
        $from = $month . '-01';
        $to = min(date('Y-m-t', (int) strtotime($from)), date('Y-m-d'));
        $prevFrom = date('Y-m-01', (int) strtotime($from . ' -1 month'));
        $prevTo = date('Y-m-t', (int) strtotime($prevFrom));
        $working = [];
        for ($d = $from; $d <= $to; $d = date('Y-m-d', (int) strtotime($d . ' +1 day'))) {
            if (self::isWorkingDay($d)) {
                $working[] = $d;
            }
        }
        $src = DB::hasColumn('attendance', 'source') ? 'source' : "'manual' AS source";
        $out = [];
        $emps = DB::all("SELECT id, name_en, name_ar, emp_no, hire_date, end_date, status FROM employees
            WHERE deleted_at IS NULL AND (status <> 'left' OR end_date >= ?) AND (hire_date IS NULL OR hire_date <= ?) ORDER BY name_en", [$from, $to]);
        foreach ($emps as $e) {
            $rows = [];
            foreach (DB::all("SELECT work_date, status, check_in, check_out, overtime_hours, $src FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL", [$e['id'], $from, $to]) as $r) {
                $rows[$r['work_date']] = $r;
            }
            $leave = [];
            foreach (DB::all("SELECT start_date, end_date FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?", [$e['id'], $to, $from]) as $l) {
                for ($d = max($l['start_date'], $from); $d <= min($l['end_date'], $to); $d = date('Y-m-d', (int) strtotime($d . ' +1 day'))) {
                    $leave[$d] = true;
                }
            }
            $i = ['employee' => $e, 'no_record' => [], 'no_check_out' => [], 'late' => [], 'absent_on_leave' => [], 'to_check' => []];
            foreach ($working as $d) {
                $inJob = (!$e['hire_date'] || $d >= $e['hire_date']) && (!$e['end_date'] || $d <= $e['end_date']);
                if ($inJob && !isset($rows[$d]) && !isset($leave[$d]) && !in_array($e['status'], ['on_leave', 'suspended'], true)) { // not expected at work
                    $i['no_record'][] = $d;
                }
            }
            foreach ($rows as $d => $r) {
                if ($r['check_in'] !== null && $r['check_out'] === null && in_array($r['status'], ['present', 'late'], true) && $d < date('Y-m-d')) {
                    $i['no_check_out'][] = $d;
                }
                if ($r['status'] === 'late') {
                    $i['late'][] = $d;
                }
                if ($r['status'] === 'absent' && isset($leave[$d])) {
                    $i['absent_on_leave'][] = $d;
                }
                if (in_array($r['source'], self::TO_CHECK, true)) {
                    $i['to_check'][] = $d;
                }
            }
            $i['overtime'] = round(array_sum(array_map(fn ($r) => (float) $r['overtime_hours'], $rows)), 2);
            $i['overtime_before'] = round((float) DB::value('SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL', [$e['id'], $prevFrom, $prevTo]), 2);
            $i['overtime_up'] = $i['overtime'] >= 4 && $i['overtime'] > 1.5 * $i['overtime_before'];
            foreach (['no_record', 'no_check_out', 'late', 'absent_on_leave', 'to_check'] as $k) {
                sort($i[$k]);
            }
            if ($i['no_record'] || $i['no_check_out'] || $i['late'] || $i['absent_on_leave'] || $i['to_check'] || $i['overtime_up']) {
                $out[] = $i;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ the gate

    /** The manager PIN for typing a staff number at the gate (stored as a hash; none = typing is off). */
    public static function pinSet(): bool
    {
        return (string) Settings::get('hr.scan_pin_hash', '') !== '';
    }

    public static function pinOk(string $pin): bool
    {
        $hash = (string) Settings::get('hr.scan_pin_hash', '');
        return $hash !== '' && preg_match('/^\d{4,8}$/', $pin) && password_verify($pin, $hash);
    }

    public static function setPin(?string $pin): void
    {
        Settings::set('hr.scan_pin_hash', $pin !== null && $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : '');
    }

    /** The gate's internet addresses (setting hr.scan_ips: addresses, "a.b.c." prefixes or a.b.c.d/nn ranges); none = anywhere. */
    public static function ipAllowed(string $ip): bool
    {
        $list = array_values(array_filter(array_map('trim', (array) preg_split('/[\s,]+/', (string) Settings::get('hr.scan_ips', '')))));
        if (!$list) {
            return true;
        }
        foreach ($list as $p) {
            if ($p === $ip || (str_ends_with($p, '.') && str_starts_with($ip, $p))) {
                return true;
            }
            if (preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $p, $m) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $mask = (int) $m[2] === 0 ? 0 : (-1 << (32 - (int) $m[2])) & 0xFFFFFFFF;
                if ((ip2long($ip) & $mask) === (ip2long($m[1]) & $mask)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** A user who can only scan (the "Attendance kiosk" role): kept on the scanner page. */
    public static function kioskOnly(): bool
    {
        if (!Auth::check() || Auth::isOwner()) {
            return false;
        }
        $perms = Auth::permissions();
        return isset($perms['attendance_scan']) && count($perms) === 1;
    }

    private static function hhmm(string $v, string $default): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $default;
    }
}
