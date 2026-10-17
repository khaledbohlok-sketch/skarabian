<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\AttendanceScan;
use App\Services\Cache;
use App\Services\FileStore;
use App\Services\Settings;

/**
 * Attendance by QR (database update 024): the gate scanner — a phone or tablet signed in with the "Attendance kiosk"
 * login (or anyone with "Attendance scanner") reads the staff ID cards — its settings, and the monthly check before
 * payroll. Scans only fill check-in / check-out times; the daily sheet and payroll work as before.
 */
class AttendanceScanController extends Controller
{
    private const PIN_TRIES = 5; // wrong manager PINs per user in 15 minutes before typing a staff number is locked
    /** The attendance settings: setting => [form field] */
    private const SETTINGS = [
        'hr.default_in' => ['default_in'], 'hr.default_out' => ['default_out'], 'hr.grace_minutes' => ['grace_minutes'],
        'hr.max_shift_hours' => ['max_shift_hours'], 'hr.brief_time' => ['brief_time'], 'hr.scan_ips' => ['scan_ips'],
    ];

    public function page(): void
    {
        Auth::requirePerm('attendance_scan', 'create');
        $ipOk = !AttendanceScan::kioskOnly() || AttendanceScan::ipAllowed(Request::ip());
        $problem = !AttendanceScan::available() ? __('attendance.needs_update') : (!$ipOk ? __('attendance.scan_ip_refused') : null);
        $cfg = [
            'scanUrl' => url('/portal/attendance/scan'), 'typedUrl' => url('/portal/attendance/scan/typed'), 'pingUrl' => url('/portal/attendance/scan/ping'),
            'photoUrl' => url('/portal/attendance/scan/photo/'), 'loginUrl' => url('/portal/login'), 'jsqr' => asset('vendor/jsqr/jsQR.js'),
            'ready' => $problem === null, 'pin' => AttendanceScan::pinSet(), 'serverTime' => time(), 'tz' => date_default_timezone_get(),
            'i18n' => [
                'start' => __('attendance.scan_start'), 'hint' => __('attendance.scan_hint'), 'online' => __('attendance.scan_online'),
                'offline' => __('attendance.scan_offline'), 'queued' => __('attendance.scan_queued'), 'saved' => __('attendance.scan_saved_offline'),
                'signedOut' => __('attendance.scan_signed_out'), 'camera' => __('attendance.scan_camera_denied'), 'noCamera' => __('attendance.scan_no_camera'),
                'expired' => __('attendance.scan_page_expired'), 'failed' => __('attendance.scan_failed'),
            ],
        ];
        $this->view('portal/hr/scan', ['title' => __('attendance.scanner'), 'cfg' => $cfg, 'problem' => $problem], 'kiosk');
    }

    /** POST qr [, offline=1, at=ISO time]: one scanned card → JSON for the gate screen. */
    public function scan(): void
    {
        $this->gate();
        $offline = Request::post('offline') === '1';
        $at = $offline ? (strtotime((string) Request::post('at')) ?: null) : null;
        $r = AttendanceScan::scan(mb_substr((string) Request::post('qr'), 0, 300), $offline ? 'qr_offline' : 'qr', $at, $this->device());
        if (in_array($r['result'], ['in', 'late', 'out', 'warning'], true)) {
            Cache::bump();
        }
        Response::json(['ok' => true, 'message' => $this->message($r)] + $r);
    }

    /** POST emp_no + pin: a card that won't scan — the staff number typed with the manager PIN (marked for the manager). */
    public function typed(): void
    {
        $this->gate();
        if (!AttendanceScan::pinSet()) {
            Response::json(['ok' => false, 'error' => __('attendance.pin_off')], 422);
        }
        // wrong PINs in the last 15 minutes, since this login last typed the right one
        $fails = (int) DB::value("SELECT COUNT(*) FROM activity_log WHERE action = 'attendance_pin_wrong' AND user_id = ? AND created_at > NOW() - INTERVAL 15 MINUTE
            AND id > COALESCE((SELECT MAX(id) FROM activity_log WHERE action = 'attendance_pin_ok' AND user_id = ?), 0)", [Auth::id(), Auth::id()]);
        if ($fails >= self::PIN_TRIES) {
            Response::json(['ok' => false, 'error' => __('attendance.pin_locked')], 429);
        }
        if (!AttendanceScan::pinOk((string) Request::post('pin'))) {
            Audit::log('attendance_pin_wrong', 'hr', null, null, null, ['tries' => $fails + 1], 'Gate scanner: wrong manager PIN');
            Response::json(['ok' => false, 'error' => __('attendance.pin_wrong')], 403);
        }
        $empNo = mb_substr(trim((string) Request::post('emp_no')), 0, 20);
        Audit::log('attendance_pin_ok', 'hr', null, null, null, ['emp_no' => $empNo], 'Gate scanner: staff number typed with the manager PIN');
        $r = AttendanceScan::typed($empNo, $this->device());
        if ($r['result'] !== 'rejected') {
            Cache::bump();
        }
        Response::json(['ok' => true, 'message' => $this->message($r)] + $r);
    }

    /** Keeps the gate phone signed in while the scanner is open, and gives it the server's clock. */
    public function ping(): void
    {
        Auth::requirePerm('attendance_scan', 'create');
        Response::json(['ok' => true, 'serverTime' => time()]);
    }

    /** The staff photo shown after a scan (the kiosk login cannot open the employee records themselves). */
    public function photo(string $id): void
    {
        Auth::requirePerm('attendance_scan', 'create');
        $f = DB::row("SELECT f.* FROM employees e JOIN files f ON f.id = e.photo_id AND f.deleted_at IS NULL
            WHERE e.id = ? AND e.deleted_at IS NULL AND e.status IN ('active','on_leave')", [(int) $id]) ?? Response::notFound();
        foreach ([[(string) $f['thumb_name'], 'image/webp'], [(string) $f['stored_name'], (string) $f['mime']]] as [$name, $mime]) {
            if ($name !== '' && str_starts_with($mime, 'image/') && is_file(FileStore::path($name))) {
                Response::file(FileStore::path($name), $mime, null, true);
            }
        }
        Response::notFound();
    }

    /** Usual hours, grace, longest shift, the end-of-day list time, the gate's internet address and the manager PIN. */
    public function settings(): void
    {
        Auth::requirePerm('hr', 'edit');
        if (!AttendanceScan::available()) {
            $this->flash('error', __('attendance.needs_update'));
            $this->redirect('/portal/attendance');
        }
        $keys = array_keys(self::SETTINGS);
        if (Request::isPost()) {
            $time = fn ($v) => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
            $in = $time(Request::post('default_in'));
            $out = $time(Request::post('default_out'));
            $brief = $time(Request::post('brief_time'));
            $grace = filter_var(Request::post('grace_minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 240]]);
            $shift = filter_var(Request::post('max_shift_hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 8, 'max_range' => 24]]);
            $ips = trim((string) Request::post('scan_ips'));
            $pin = trim((string) Request::post('pin'));
            $errors = [];
            if (!$in || !$out || $out <= $in) {
                $errors['default_out'] = __('attendance.hours_invalid');
            }
            if (!$brief) {
                $errors['brief_time'] = __('validation.time');
            }
            if ($grace === false) {
                $errors['grace_minutes'] = __('attendance.number_between', ['min' => 0, 'max' => 240]);
            }
            if ($shift === false) {
                $errors['max_shift_hours'] = __('attendance.number_between', ['min' => 8, 'max' => 24]);
            }
            if (mb_strlen($ips) > 500 || ($ips !== '' && !preg_match('#^[0-9./,\s]+$#', $ips))) {
                $errors['scan_ips'] = __('attendance.ips_invalid');
            }
            if ($pin !== '' && !preg_match('/^\d{4,8}$/', $pin)) {
                $errors['pin'] = __('attendance.pin_invalid');
            }
            if ($errors) {
                \App\Core\Session::errors($errors);
                \App\Core\Session::keepOld(array_diff_key($_POST, ['pin' => 1])); // never the PIN
                $this->flash('error', __('validation.fix_errors'));
                $this->redirect('/portal/attendance/settings');
            }
            $old = array_combine($keys, array_map(fn ($k) => Settings::get($k), $keys));
            $form = ['default_in' => $in, 'default_out' => $out, 'grace_minutes' => (string) $grace, 'max_shift_hours' => (string) $shift, 'brief_time' => $brief, 'scan_ips' => $ips];
            $new = [];
            foreach (self::SETTINGS as $k => [$field]) {
                $new[$k] = $form[$field];
                Settings::set($k, $new[$k]);
            }
            $pinChange = $pin !== '' ? 'set' : (Request::post('pin_clear') === '1' ? 'cleared' : null);
            if ($pinChange !== null) {
                AttendanceScan::setPin($pinChange === 'set' ? $pin : null);
            }
            Audit::log('update', 'hr', 'settings', null, $old, $new + ($pinChange ? ['manager_pin' => $pinChange] : []), 'Attendance settings');
            $this->flash('success', __('common.saved'));
            $this->redirect('/portal/attendance/settings');
        }
        $this->view('portal/hr/attendance_settings', [
            'title' => __('attendance.settings'), 'values' => array_combine(array_column(self::SETTINGS, 0), array_map(fn ($k) => (string) Settings::get($k, ''), $keys)),
            'pin' => AttendanceScan::pinSet(),
        ]);
    }

    /** Before payroll: per person, what to look at in the month — each with a link to that day's sheet. */
    public function check(): void
    {
        Auth::requirePerm('hr', 'view');
        $month = (string) Request::query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > date('Y-m')) {
            $month = date('Y-m');
        }
        $this->view('portal/hr/attendance_check', [
            'title' => __('attendance.month_check'), 'month' => $month, 'rows' => AttendanceScan::month($month),
            'canSheet' => Auth::can('hr', 'create'), 'scans' => AttendanceScan::available(),
        ]);
    }

    /** The scanner's permission, the database update and (for the kiosk login) the gate's internet address. */
    private function gate(): void
    {
        Auth::requirePerm('attendance_scan', 'create');
        if (!AttendanceScan::available()) {
            Response::json(['ok' => false, 'error' => __('attendance.needs_update')], 409);
        }
        if (AttendanceScan::kioskOnly() && !AttendanceScan::ipAllowed(Request::ip())) {
            Audit::log('access_denied', 'hr', null, null, null, ['permission' => 'attendance_scan.ip', 'ip' => Request::ip()], 'Gate scanner used away from the gate');
            Response::json(['ok' => false, 'error' => __('attendance.scan_ip_refused')], 403);
        }
    }

    private function device(): string
    {
        return mb_substr((string) (Auth::user()['username'] ?? '') . ' · ' . preg_replace('/\s+/', ' ', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 80);
    }

    /** The big line on the gate screen. */
    private function message(array $r): string
    {
        $t = ['time' => $r['time']];
        return match (true) {
            $r['result'] === 'in' => __('attendance.scan_in', $t),
            $r['result'] === 'late' => __('attendance.scan_late', $t),
            $r['result'] === 'out' => __($r['reason'] === 'night' ? 'attendance.scan_out_night' : 'attendance.scan_out', $t),
            $r['result'] === 'warning' => __('attendance.scan_warning_' . substr($r['reason'], 7), $t),
            $r['result'] === 'ignored' => __('attendance.scan_ignored_' . $r['reason'], $t),
            default => __('attendance.scan_refused_' . $r['reason']),
        };
    }
}
