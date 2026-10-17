<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Config;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Lang;

/**
 * Scheduled tasks. cPanel needs a single cron line running every 15 minutes:
 *   php /home/USER/skarabian/cron/run.php
 * The scheduler decides which tasks are due from the last successful run of each one (table cron_runs); a daily or
 * weekly task that failed is tried again RETRY_AFTER later, not on every run. A task can also be run by name,
 * e.g. `php cron/run.php backup`.
 */
final class Cron
{
    /** task => [how often, earliest time of day (HH:MM) or null] */
    public const TASKS = [
        'recurring'  => ['daily', '00:30'], // recurring bills: the bills due today (and any missed dates) are created
        'overdue'    => ['hourly', null],   // bills past their due date become Overdue
        'cleanup'    => ['hourly', null],   // expired temporary access, old sessions, codes, login attempts
        'rates'      => ['daily', '05:00'], // exchange rates (floating currencies only, unless overridden)
        'categories' => ['daily', '05:10'], // Foal → Colt/Filly → Stallion/Mare by age
        'reminders'  => ['daily', '06:00'], // document expiries, vet/farrier due, pregnancy checks, foalings, tank refills, low stock (items, semen), budgets
        'summary'    => ['daily', 'setting:notify.daily_summary_time'],
        'backup'     => ['daily', '02:00'], // database + files, off-server copy, keep 30 days
        'security'   => ['weekly', '08:00'],// weekly security report to the Owner (Sundays)
        'attendance' => ['daily', 'setting:hr.brief_time'], // end-of-day list for HR: no record, late, no check-out, scans to check
    ];

    private const ACTOR = ['id' => null, 'name' => 'Scheduled task'];

    /** Seconds before a failed (or unfinished) daily or weekly task is tried again: 3 hours. */
    public const RETRY_AFTER = 10800;

    /** Runs every task that is due. Returns log lines. */
    public static function runDue(?int $now = null): array
    {
        $now ??= time();
        $log = [];
        foreach (array_keys(self::TASKS) as $task) {
            if (self::isDue($task, $now)) {
                $log[] = self::run($task);
            }
        }
        return $log ?: ['nothing due'];
    }

    public static function isDue(string $task, int $now): bool
    {
        [$every, $at] = self::TASKS[$task];
        if ($at !== null && str_starts_with($at, 'setting:')) {
            $at = (string) Settings::get(substr($at, 8), '07:00');
        }
        $last = DB::value('SELECT MAX(started_at) FROM cron_runs WHERE task = ? AND ok = 1', [$task]);
        $lastTs = $last ? strtotime((string) $last) : 0;
        if ($every === 'hourly') {
            return $now - $lastTs >= 3300; // 55 minutes, so a 15-minute cron never skips an hour
        }
        // Daily / weekly: due once the time of day has passed and it has not run since that moment
        $slot = strtotime(date('Y-m-d', $now) . ' ' . ($at ?: '00:00'), $now);
        if ($every === 'weekly') {
            $slot = strtotime('last sunday ' . ($at ?: '00:00'), strtotime('tomorrow', $now));
        }
        if ($now < $slot || $lastTs >= $slot) {
            return false;
        }
        // A run since then that failed or never finished: tried again after a pause, not every 15 minutes (a failing
        // backup would otherwise make a new full copy each time)
        $failed = DB::value('SELECT MAX(started_at) FROM cron_runs WHERE task = ? AND (ok = 0 OR ok IS NULL) AND started_at >= ?', [$task, date('Y-m-d H:i:s', $slot)]);
        return !$failed || $now - strtotime((string) $failed) >= self::RETRY_AFTER;
    }

    /** Runs one task, records the outcome and never throws. */
    public static function run(string $task): string
    {
        if (!isset(self::TASKS[$task])) {
            return "unknown task $task";
        }
        @set_time_limit(0);
        Lang::set((string) Config::get('app.default_lang', 'en'));
        $id = DB::insert('cron_runs', ['task' => $task, 'started_at' => date('Y-m-d H:i:s')]);
        // A fatal error (e.g. out of memory during a large backup) skips the catch below: it is still recorded and the Owner told
        register_shutdown_function(function () use ($id, $task) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                try {
                    if (DB::pdo()->inTransaction()) {
                        DB::pdo()->rollBack();
                    }
                    self::recordFatal($id, $task, (string) $err['message']);
                } catch (\Throwable) {
                }
            }
        });
        try {
            $msg = (string) self::$task();
            $ok = true;
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
            $msg = 'failed: ' . $e->getMessage();
            $ok = false;
            Notifier::owners('system', Notifier::msg('cron.failed', ['task' => $task]), mb_substr($e->getMessage(), 0, 300), '/portal/settings', true, 'cron-fail-' . $task . '-' . date('Ymd'));
        }
        DB::update('cron_runs', ['finished_at' => date('Y-m-d H:i:s'), 'ok' => $ok ? 1 : 0, 'message' => mb_substr($msg, 0, 500)], 'id = :id', ['id' => $id]);
        DB::run('DELETE FROM cron_runs WHERE started_at < NOW() - INTERVAL 90 DAY');
        return str_pad($task, 11) . ($ok ? 'ok    ' : 'FAILED ') . $msg;
    }

    /** A run (cron_runs $id) that a fatal error ended before it finished: recorded as failed, and the Owner told as for any failure. */
    public static function recordFatal(int $id, string $task, string $message): void
    {
        if (!(int) DB::value('SELECT COUNT(*) FROM cron_runs WHERE id = ? AND finished_at IS NULL', [$id])) {
            return;
        }
        DB::update('cron_runs', ['finished_at' => date('Y-m-d H:i:s'), 'ok' => 0, 'message' => mb_substr('failed: ' . $message, 0, 500)], 'id = :id', ['id' => $id]);
        Notifier::owners('system', Notifier::msg('cron.failed', ['task' => $task]), mb_substr($message, 0, 300), '/portal/settings', true, 'cron-fail-' . $task . '-' . date('Ymd'));
    }

    // ------------------------------------------------------------------ tasks

    private static function recurring(): string
    {
        if (!RecurringBillService::available()) {
            return 'waiting for the database update (Settings)';
        }
        return RecurringBillService::createDue() . ' bills created';
    }

    /** The day's attendance at a glance, to everyone who edits HR (and the Owner): in the portal and by email. */
    private static function attendance(): string
    {
        if (!AttendanceScan::available()) {
            return 'waiting for the database update (Settings)';
        }
        $date = date('Y-m-d');
        $d = AttendanceScan::day($date);
        $n = count($d['no_record']) + count($d['late']) + count($d['no_check_out']) + count($d['to_check']);
        if ($n === 0) {
            return $d['working'] ? 'all in order' : 'not a working day';
        }
        $names = fn (array $list) => ['en' => implode(', ', array_column($list, 'name_en')), 'ar' => implode('، ', array_map(fn ($e) => $e['name_ar'] ?: $e['name_en'], $list))];
        $line = fn (string $k, array $list) => $list ? Notifier::msg('attendance.brief_' . $k, ['n' => count($list), 'names' => $names($list)]) : '';
        Notifier::permitted('hr', 'edit', 'attendance', Notifier::msg('attendance.brief_title', ['date' => ['date' => $date], 'n' => $n]),
            Notifier::msg('attendance.brief_body', ['none' => $line('none', $d['no_record']), 'late' => $line('late', $d['late']),
                'out' => $line('out', $d['no_check_out']), 'check' => $line('check', $d['to_check'])]),
            '/portal/attendance/sheet?date=' . $date, true, 'attendance-brief-' . $date);
        return "$n to look at";
    }

    private static function overdue(): string
    {
        $n = FinanceService::refreshOverdue();
        if ($n) {
            Cache::bump();
        }
        return "$n bills marked overdue";
    }

    private static function cleanup(): string
    {
        $out = [];
        $grants = DB::all('SELECT g.*, u.name FROM user_temp_grants g JOIN users u ON u.id = g.user_id WHERE g.expires_at <= NOW()');
        foreach ($grants as $g) {
            Audit::log('revoke', 'users', 'user', $g['user_id'], ['module' => $g['module'], 'action' => $g['action']], null, 'Temporary access ended: ' . $g['name'] . ' ' . $g['module'] . '.' . $g['action'], self::ACTOR);
        }
        $out[] = DB::run('DELETE FROM user_temp_grants WHERE expires_at <= NOW()')->rowCount() . ' grants ended';
        $timeout = (int) Config::get('security.session_timeout', 1800);
        $out[] = DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND last_seen_at < NOW() - INTERVAL ' . max(300, $timeout) . ' SECOND')->rowCount() . ' idle sessions closed';
        DB::run('DELETE FROM user_sessions WHERE revoked_at < NOW() - INTERVAL 90 DAY');
        DB::run('DELETE FROM email_codes WHERE expires_at < NOW() - INTERVAL 1 DAY');
        DB::run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 90 DAY');
        DB::run('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < NOW() - INTERVAL 180 DAY');
        DB::run('UPDATE users SET locked_until = NULL WHERE locked_until IS NOT NULL AND locked_until < NOW()');
        $out[] = HorseImport::purgeOld() . ' unfinished import files removed';
        $out[] = SiteVisits::purge() . ' website visits over 400 days old removed';
        Cache::clearOld();
        return implode(', ', $out);
    }

    private static function rates(): string
    {
        if (!Config::get('rates.auto_update', true)) {
            return 'auto update is off';
        }
        $url = (string) Config::get('rates.provider_url', 'https://open.er-api.com/v6/latest/QAR');
        $json = self::httpGet($url);
        $data = $json ? json_decode($json, true) : null;
        $rates = $data['rates'] ?? $data['conversion_rates'] ?? null;
        if (!is_array($rates)) {
            throw new \RuntimeException('Rate provider gave no rates');
        }
        $changed = [];
        foreach (DB::all("SELECT * FROM currencies WHERE code <> 'QAR' AND auto_update = 1 AND manual_override = 0") as $c) {
            $perQar = (float) ($rates[$c['code']] ?? 0);
            if ($perQar <= 0) {
                continue;
            }
            $new = round(1 / $perQar, 6);
            $old = (float) $c['rate_to_qar'];
            // Guard against a broken feed: refuse jumps of more than 20% in a day
            if ($old > 0 && abs($new - $old) / $old > 0.20) {
                Notifier::owners('system', Notifier::msg('cron.rate_jump', ['code' => $c['code']]), $c['code'] . ': ' . $old . ' → ' . $new, '/portal/settings/currencies', false, 'rate-jump-' . $c['code'] . '-' . date('Ymd'));
                continue;
            }
            if (abs($new - $old) >= 0.000001) {
                DB::update('currencies', ['rate_to_qar' => $new, 'updated_at' => date('Y-m-d H:i:s')], 'code = :c', ['c' => $c['code']]);
                DB::insert('currency_rate_history', ['code' => $c['code'], 'rate_to_qar' => $new, 'source' => 'auto']);
                $changed[] = $c['code'] . ' ' . $new;
            }
        }
        return $changed ? 'updated ' . implode(', ', $changed) : 'no changes';
    }

    private static function categories(): string
    {
        return HorseService::recalcCategories() . ' horse categories updated';
    }

    /** In-app (and email for overdue items) reminders to the people who handle each kind of item. Each item is announced once. */
    private static function reminders(): string
    {
        $modules = ['qid' => 'hr', 'vet' => 'horse_health', 'check' => 'horse_breeding', 'foal' => 'horse_breeding', 'tank' => 'semen_stock', 'invoice' => 'finance'];
        $windows = ['qid' => 60, 'vet' => 7, 'check' => 3, 'foal' => 30, 'tank' => 3, 'invoice' => 7];
        $n = 0;
        foreach (Reminders::all(60, true) as $r) {
            if ($r['days'] > ($windows[$r['cat']] ?? 30)) {
                continue;
            }
            $state = $r['days'] < 0 ? 'over' : 'soon';
            // Stored as language keys: each reader sees it in their own language
            $title = Notifier::msg('notify.reminder', ['state' => $state === 'over' ? Notifier::msg('cron.overdue') : Notifier::msg('cron.due_soon', ['date' => ['date' => $r['date']]]), 'what' => $r['msg']]);
            Notifier::permitted($modules[$r['cat']] ?? 'studio', 'edit', 'reminder', $title, $r['detail_msg'], $r['url'], $state === 'over', 'rem-' . md5($r['url'] . $r['date'] . $state));
            $n++;
        }
        $sem = 0;
        foreach (self::semenLow() as $st) {
            Notifier::permitted('semen_stock', 'edit', 'low_stock', Notifier::msg('notify.semen_low', ['name' => $st['name']]), Notifier::msg('notify.semen_low_body', ['doses' => (int) $st['doses_min'], 'straws' => (int) $st['straws']]),
                '/portal/semen-stock' . ($st['stallion_id'] ? '?stallion_id=' . (int) $st['stallion_id'] : '?q=' . rawurlencode((string) $st['name'])), false, 'semen-low-' . md5((string) ($st['stallion_id'] ?: $st['name'])) . '-' . date('Y-W'));
            $sem++;
        }
        $inv = 0;
        foreach (DB::all('SELECT id, name_en, name_ar, quantity, min_quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND min_quantity > 0 AND quantity <= min_quantity') as $i) {
            Notifier::permitted('inventory', 'edit', 'low_stock', Notifier::msg('cron.low_stock', ['item' => ['en' => $i['name_en'], 'ar' => $i['name_ar']]]), rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit'], '/portal/items/' . $i['id'], false, 'low-' . $i['id'] . '-' . date('Y-W'));
            $inv++;
        }
        foreach (DB::all('SELECT id, name_en, name_ar, expiry_date FROM inventory_items WHERE deleted_at IS NULL AND quantity > 0 AND expiry_date IS NOT NULL AND expiry_date <= CURDATE() + INTERVAL 30 DAY') as $i) {
            Notifier::permitted('inventory', 'edit', 'expiry', Notifier::msg('cron.item_expires', ['item' => ['en' => $i['name_en'], 'ar' => $i['name_ar']], 'date' => ['date' => $i['expiry_date']]]), null, '/portal/items/' . $i['id'], false, 'exp-' . $i['id'] . '-' . $i['expiry_date']);
            $inv++;
        }
        $bud = 0;
        foreach (Dashboard::budgetsExceeded() as $b) {
            Notifier::permitted('finance', 'approve', 'budget', Notifier::msg('cron.budget_exceeded', ['category' => $b['name_en']]), money($b['spent'], 'QAR', false) . ' / ' . money($b['amount_qar'], 'QAR', false), '/portal/budgets', false, 'bud-' . md5($b['name_en']) . '-' . date('Y-m'));
            $bud++;
        }
        return "$n reminders, $inv stock alerts, $sem semen stock alerts, $bud budgets";
    }

    /** Stallions at or below the semen low-stock threshold — SemenStockService's own rule (doses across all their stock rows). */
    public static function semenLow(): array
    {
        if (!SemenStockService::lowStockCount()) {
            return [];
        }
        $threshold = (int) Settings::get('semen_stock.low_threshold', 2);
        return array_values(array_filter(SemenStockService::stallionSummary(), fn ($s) => (int) $s['doses_min'] <= $threshold));
    }

    /** Morning summary for the Owner by email and/or WhatsApp. */
    private static function summary(): string
    {
        $email = Settings::get('notify.daily_summary_email', '1') === '1';
        $wa = Settings::get('notify.daily_summary_whatsapp', '0') === '1';
        if (!$email && !$wa) {
            return 'summary is turned off';
        }
        $s = self::summaryData();
        $sent = [];
        if ($email) {
            // An explicit recipient (Settings → Daily summary recipient) replaces the Owner's own login
            // email, so the Owner can redirect the summary without changing how they sign in.
            $override = trim((string) Settings::get('notify.summary_email', ''));
            if ($override !== '') {
                Lang::set('en');
                if (Mailer::send($override, __('cron.summary_subject', ['date' => fmt_date(date('Y-m-d'))]), self::summaryHtml($s), [], ['lang' => 'en'])) {
                    $sent[] = $override;
                }
            } else {
                foreach (DB::all("SELECT u.email, u.lang FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") as $o) {
                    Lang::set($o['lang'] ?: 'en');
                    if (Mailer::send($o['email'], __('cron.summary_subject', ['date' => fmt_date(date('Y-m-d'))]), self::summaryHtml($s), [], ['lang' => Lang::current()])) {
                        $sent[] = $o['email'];
                    }
                }
            }
        }
        $phone = (string) Settings::get('notify.owner_phone', '');
        if ($wa && $phone !== '' && Notifier::whatsapp($phone, self::summaryText($s))) {
            $sent[] = 'WhatsApp';
        }
        return $sent ? 'sent to ' . implode(', ', $sent) : 'nothing sent (check mail / WhatsApp settings)';
    }

    public static function summaryData(): array
    {
        $y = date('Y-m-d', strtotime('-1 day'));
        $docs = Dashboard::documents();
        return [
            'approvals' => DB::all("SELECT type, title, amount_qar FROM approvals WHERE status = 'pending' ORDER BY requested_at"),
            'expired'   => $docs['expired'] ?? [],
            'expiring'  => $docs['soon'] ?? [],
            'foalings'  => DB::all("SELECT m.name_en, b.expected_foaling_date AS d FROM breeding_records b JOIN horses m ON m.id = b.mare_id WHERE b.deleted_at IS NULL AND b.status = 'pregnant' AND b.expected_foaling_date <= CURDATE() + INTERVAL 30 DAY ORDER BY d"),
            'checks'    => HorseService::pregnancyChecksDue(date('Y-m-d', strtotime('+3 days'))),
            'tanks'     => Reminders::tanksDue(date('Y-m-d', strtotime('+3 days'))),
            'semen_low' => self::semenLow(),
            'low'       => DB::all('SELECT name_en, quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND min_quantity > 0 AND quantity <= min_quantity ORDER BY name_en'),
            // drafts are not counted until submitted (as on the dashboard)
            'spent'     => (float) DB::value("SELECT COALESCE(SUM(amount_qar), 0) FROM bills WHERE deleted_at IS NULL AND type = 'expense' AND status NOT IN ('cancelled','draft') AND bill_date = ?", [$y]),
            'spent_n'   => (int) DB::value("SELECT COUNT(*) FROM bills WHERE deleted_at IS NULL AND type = 'expense' AND status NOT IN ('cancelled','draft') AND bill_date = ?", [$y]),
            'overdue'   => (float) DB::value("SELECT COALESCE(SUM(amount_qar - paid_qar), 0) FROM bills WHERE deleted_at IS NULL AND status = 'overdue'"),
            'inquiries' => (int) DB::value("SELECT COUNT(*) FROM inquiries WHERE deleted_at IS NULL AND status = 'new'"),
        ];
    }

    private static function summaryHtml(array $s): string
    {
        $sec = function (string $title, array $items) {
            return $items ? '<h3 style="color:#13213f;font-size:17px;margin:22px 0 6px">' . e($title) . ' (' . count($items) . ')</h3><ul style="margin:0;padding-inline-start:18px">' . implode('', array_map(fn ($i) => '<li>' . $i . '</li>', array_slice($items, 0, 15))) . '</ul>' : '';
        };
        $h = '<p>' . e(__('cron.summary_intro')) . '</p>';
        $h .= '<table style="border-collapse:collapse;margin:8px 0"><tr><td style="padding:4px 14px 4px 0">' . e(__('cron.spent_yesterday')) . '</td><td><strong>' . money($s['spent'], 'QAR', false) . '</strong> (' . $s['spent_n'] . ')</td></tr>'
            . '<tr><td style="padding:4px 14px 4px 0">' . e(__('cron.overdue_total')) . '</td><td><strong>' . money($s['overdue'], 'QAR', false) . '</strong></td></tr>'
            . '<tr><td style="padding:4px 14px 4px 0">' . e(__('cms.new_messages')) . '</td><td><strong>' . $s['inquiries'] . '</strong></td></tr></table>';
        $h .= $sec(__('dashboard.waiting_approval'), array_map(fn ($a) => e($a['title']) . ($a['amount_qar'] !== null ? ' — ' . money($a['amount_qar'], 'QAR', false) : ''), $s['approvals']));
        $h .= $sec(__('cron.docs_expired'), array_map(fn ($d) => e($d['name'] . ' · ' . __($d['doc']) . ' · ' . fmt_date($d['date'])), $s['expired']));
        $h .= $sec(__('cron.docs_expiring'), array_map(fn ($d) => e($d['name'] . ' · ' . __($d['doc']) . ' · ' . fmt_date($d['date'])), $s['expiring']));
        $h .= $sec(__('cron.preg_checks'), array_map(fn ($c) => e(__('dashboard.preg_check_day', ['name' => loc($c), 'day' => $c['day']]) . ' · ' . ($c['days'] < 0 ? __('cron.overdue') . ' · ' : '') . fmt_date($c['due_date'])), $s['checks'] ?? []));
        $h .= $sec(__('cron.foalings'), array_map(fn ($f) => e($f['name_en'] . ' · ' . fmt_date($f['d'])), $s['foalings']));
        $h .= $sec(__('cron.tank_refills'), array_map(fn ($t) => e($t['name'] . ' · ' . ($t['days'] < 0 ? __('cron.overdue') . ' · ' : '') . fmt_date($t['due_date'])), $s['tanks'] ?? []));
        $h .= $sec(__('cron.semen_low'), array_map(fn ($x) => e($x['name'] . ' · ' . __('notify.semen_low_body', ['doses' => (int) $x['doses_min'], 'straws' => (int) $x['straws']])), $s['semen_low'] ?? []));
        $h .= $sec(__('cron.low_stock_list'), array_map(fn ($i) => e($i['name_en'] . ' · ' . rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit']), $s['low']));
        return $h . EmailLook::button(__('cron.open_portal'), absolute_url('/portal'));
    }

    private static function summaryText(array $s): string
    {
        Lang::set('en');
        $lines = ['*SK Arabians — ' . fmt_date(date('Y-m-d')) . '*'];
        $lines[] = 'Approvals waiting: ' . count($s['approvals']);
        $lines[] = 'Documents expired: ' . count($s['expired']) . ', expiring: ' . count($s['expiring']);
        $lines[] = 'Pregnancy checks due: ' . count($s['checks'] ?? []);
        $lines[] = 'Foalings due (30 days): ' . count($s['foalings']);
        $lines[] = 'Nitrogen tank refills due: ' . count($s['tanks'] ?? []);
        $lines[] = 'Semen low stock (stallions): ' . count($s['semen_low'] ?? []);
        $lines[] = 'Low stock items: ' . count($s['low']);
        $lines[] = 'Spent yesterday: ' . money($s['spent'], 'QAR', false);
        $lines[] = 'New website messages: ' . $s['inquiries'];
        $lines[] = absolute_url('/portal');
        return implode("\n", $lines);
    }

    /** Weekly security email: failed logins, locked accounts, new users, permission changes, large exports. */
    private static function security(): string
    {
        if (Settings::get('security.weekly_report', '1') !== '1') {
            return 'weekly report is turned off';
        }
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $big = (int) Settings::get('security.large_export_rows', 500);
        $q = fn (string $where, array $p = []) => DB::all("SELECT created_at, user_name, summary, ip FROM activity_log WHERE created_at >= ? AND $where ORDER BY id DESC LIMIT 30", array_merge([$since], $p));
        $d = [
            'failed' => DB::all('SELECT username, ip, COUNT(*) AS n FROM login_attempts WHERE success = 0 AND created_at >= ? GROUP BY username, ip ORDER BY n DESC LIMIT 20', [$since]),
            'locked' => $q("action = 'account_locked'"),
            'users' => $q("action = 'create' AND module = 'users'"),
            'perms' => $q("action IN ('permissions','grant','revoke','reset_2fa','reset_password')"),
            'exports' => $q("action = 'export' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.rows')) AS UNSIGNED) >= ?", [$big]),
            'denied' => $q("action = 'access_denied'"),
            'chain' => Audit::verify(),
        ];
        $sent = 0;
        foreach (DB::all("SELECT u.email, u.lang FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") as $o) {
            Lang::set($o['lang'] ?: 'en'); // each Owner reads it in their own language, like the daily summary
            $sent += Mailer::send($o['email'], __('cron.security_subject'), self::securityHtml($d), [], ['lang' => Lang::current()]) ? 1 : 0;
        }
        return "sent to $sent owner(s)";
    }

    /** The weekly security email, in the current language. */
    private static function securityHtml(array $d): string
    {
        $sections = [
            [__('security.failed_7d'), array_map(fn ($f) => e($f['username'] . ' · ' . $f['ip'] . ' · ×' . $f['n']), $d['failed'])],
            [__('cron.locked'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary']), $d['locked'])],
            [__('cron.new_users'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $d['users'])],
            [__('cron.permission_changes'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $d['perms'])],
            [__('cron.large_exports'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $d['exports'])],
            [__('cron.denied'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ' · ' . $r['ip'] . ')'), $d['denied'])],
        ];
        [$chainOk, $n, $broken] = $d['chain'];
        $html = '<p>' . e(__('cron.security_intro')) . '</p><p><strong>' . e(__('security.log_chain')) . ':</strong> ' . e($chainOk ? __('security.intact', ['n' => $n]) : __('security.broken', ['id' => $broken])) . '</p>';
        foreach ($sections as [$title, $items]) {
            $html .= '<h3 style="color:#13213f;font-size:17px;margin:20px 0 6px">' . e($title) . ' (' . count($items) . ')</h3>'
                . ($items ? '<ul style="margin:0;padding-inline-start:18px"><li>' . implode('</li><li>', $items) . '</li></ul>' : '<p style="color:#5c6782">—</p>');
        }
        return $html;
    }

    private static function backup(): string
    {
        $files = BackupService::run('cron');
        $out = implode(', ', $files);
        try {
            $off = Offsite::upload($files);
            $out .= ' | off-server: ' . ($off ?? 'not set up (copies stay on this server only)');
        } catch (\Throwable $e) {
            // the backup itself is fine; the copy elsewhere failed (recorded, and the alert below says so)
            ErrorHandler::log($e);
            $out .= ' | off-server: FAILED ' . mb_substr($e->getMessage(), 0, 150);
        }
        if (Offsite::late()) {
            Notifier::owners('system', Notifier::msg('backups.offsite_late'), (string) Settings::get('backup.offsite_last_error', ''), '/portal/settings/backups', true, 'offsite-late-' . date('Ymd'));
        } elseif (!Offsite::configured()) {
            Notifier::owners('system', Notifier::msg('backups.offsite_none_title'), Notifier::msg('backups.no_offsite'), '/portal/settings/backups', false, 'offsite-none-' . date('oW'));
        }
        try {
            [$ok, $msg] = BackupService::restoreTest();
        } catch (\Throwable $e) {
            // the copies are made (no new backup on the next run); a restore test that breaks is reported like a failed one
            ErrorHandler::log($e);
            [$ok, $msg] = [false, mb_substr($e->getMessage(), 0, 200)];
        }
        if (!$ok) {
            Notifier::owners('system', Notifier::msg('backups.test_bad'), $msg, '/portal/settings/backups', true, 'restore-fail-' . date('Ymd'));
        }
        return $out . ' | restore test: ' . ($ok ? 'ok' : 'FAILED') . ' ' . $msg;
    }

    public static function httpGet(string $url, int $timeout = 20): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'SK-Arabians/1.0']);
            $r = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $r !== false && $code < 400 ? (string) $r : null;
        }
        $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => $timeout]]));
        return $r === false ? null : $r;
    }
}
