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
 * The scheduler decides which tasks are due from the last successful run of each one (table cron_runs).
 * A task can also be run by name, e.g. `php cron/run.php backup`.
 */
final class Cron
{
    /** task => [how often, earliest time of day (HH:MM) or null] */
    public const TASKS = [
        'overdue'    => ['hourly', null],   // bills past their due date become Overdue
        'cleanup'    => ['hourly', null],   // expired temporary access, old sessions, codes, login attempts
        'rates'      => ['daily', '05:00'], // exchange rates (floating currencies only, unless overridden)
        'categories' => ['daily', '05:10'], // Foal → Colt/Filly → Stallion/Mare by age
        'reminders'  => ['daily', '06:00'], // document expiries, vet/farrier due, foalings, low stock, budgets
        'summary'    => ['daily', 'setting:notify.daily_summary_time'],
        'backup'     => ['daily', '02:00'], // database + files, off-server copy, keep 30 days
        'security'   => ['weekly', '08:00'],// weekly security report to the Owner (Sundays)
    ];

    private const ACTOR = ['id' => null, 'name' => 'Scheduled task'];

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
        return $now >= $slot && $lastTs < $slot;
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
        try {
            $msg = (string) self::$task();
            $ok = true;
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
            $msg = 'failed: ' . $e->getMessage();
            $ok = false;
            Notifier::owners('system', __('cron.failed', ['task' => $task]), mb_substr($e->getMessage(), 0, 300), '/portal/settings', true, 'cron-fail-' . $task . '-' . date('Ymd'));
        }
        DB::update('cron_runs', ['finished_at' => date('Y-m-d H:i:s'), 'ok' => $ok ? 1 : 0, 'message' => mb_substr($msg, 0, 500)], 'id = :id', ['id' => $id]);
        DB::run('DELETE FROM cron_runs WHERE started_at < NOW() - INTERVAL 90 DAY');
        return str_pad($task, 11) . ($ok ? 'ok    ' : 'FAILED ') . $msg;
    }

    // ------------------------------------------------------------------ tasks

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
                Notifier::owners('system', __('cron.rate_jump', ['code' => $c['code']]), $c['code'] . ': ' . $old . ' → ' . $new, '/portal/settings/currencies', false, 'rate-jump-' . $c['code'] . '-' . date('Ymd'));
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
        $modules = ['qid' => 'hr', 'vet' => 'horse_health', 'foal' => 'horse_breeding', 'invoice' => 'finance'];
        $windows = ['qid' => 60, 'vet' => 7, 'foal' => 30, 'invoice' => 7];
        $n = 0;
        foreach (Reminders::all(60, true) as $r) {
            if ($r['days'] > ($windows[$r['cat']] ?? 30)) {
                continue;
            }
            $state = $r['days'] < 0 ? 'over' : 'soon';
            $title = ($state === 'over' ? __('cron.overdue') : __('cron.due_soon', ['date' => fmt_date($r['date'])])) . ': ' . $r['title'];
            Notifier::permitted($modules[$r['cat']] ?? 'studio', 'edit', 'reminder', $title, $r['detail'], $r['url'], $state === 'over', 'rem-' . md5($r['url'] . $r['date'] . $state));
            $n++;
        }
        $inv = 0;
        foreach (DB::all('SELECT id, name_en, quantity, min_quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND min_quantity > 0 AND quantity <= min_quantity') as $i) {
            Notifier::permitted('inventory', 'edit', 'low_stock', __('cron.low_stock', ['item' => $i['name_en']]), rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit'], '/portal/items/' . $i['id'], false, 'low-' . $i['id'] . '-' . date('Y-W'));
            $inv++;
        }
        foreach (DB::all('SELECT id, name_en, expiry_date FROM inventory_items WHERE deleted_at IS NULL AND quantity > 0 AND expiry_date IS NOT NULL AND expiry_date <= CURDATE() + INTERVAL 30 DAY') as $i) {
            Notifier::permitted('inventory', 'edit', 'expiry', __('cron.item_expires', ['item' => $i['name_en'], 'date' => fmt_date($i['expiry_date'])]), null, '/portal/items/' . $i['id'], false, 'exp-' . $i['id'] . '-' . $i['expiry_date']);
            $inv++;
        }
        $bud = 0;
        foreach (Dashboard::budgetsExceeded() as $b) {
            Notifier::permitted('finance', 'approve', 'budget', __('cron.budget_exceeded', ['category' => $b['name_en']]), money($b['spent'], 'QAR', false) . ' / ' . money($b['amount_qar'], 'QAR', false), '/portal/budgets', false, 'bud-' . md5($b['name_en']) . '-' . date('Y-m'));
            $bud++;
        }
        return "$n reminders, $inv stock alerts, $bud budgets";
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
            foreach (DB::all("SELECT u.email, u.lang FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") as $o) {
                Lang::set($o['lang'] ?: 'en');
                if (Mailer::send($o['email'], __('cron.summary_subject', ['date' => fmt_date(date('Y-m-d'))]), self::summaryHtml($s))) {
                    $sent[] = $o['email'];
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
            'low'       => DB::all('SELECT name_en, quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND min_quantity > 0 AND quantity <= min_quantity ORDER BY name_en'),
            'spent'     => (float) DB::value("SELECT COALESCE(SUM(amount_qar), 0) FROM bills WHERE deleted_at IS NULL AND type = 'expense' AND status <> 'cancelled' AND bill_date = ?", [$y]),
            'spent_n'   => (int) DB::value("SELECT COUNT(*) FROM bills WHERE deleted_at IS NULL AND type = 'expense' AND status <> 'cancelled' AND bill_date = ?", [$y]),
            'overdue'   => (float) DB::value("SELECT COALESCE(SUM(amount_qar - paid_qar), 0) FROM bills WHERE deleted_at IS NULL AND status = 'overdue'"),
            'inquiries' => (int) DB::value("SELECT COUNT(*) FROM inquiries WHERE deleted_at IS NULL AND status = 'new'"),
        ];
    }

    private static function summaryHtml(array $s): string
    {
        $sec = function (string $title, array $items) {
            return $items ? '<h3 style="color:#0f1f45;margin:18px 0 6px">' . e($title) . ' (' . count($items) . ')</h3><ul style="margin:0;padding-inline-start:18px">' . implode('', array_map(fn ($i) => '<li>' . $i . '</li>', array_slice($items, 0, 15))) . '</ul>' : '';
        };
        $h = '<p>' . e(__('cron.summary_intro')) . '</p>';
        $h .= '<table style="border-collapse:collapse;margin:8px 0"><tr><td style="padding:4px 14px 4px 0">' . e(__('cron.spent_yesterday')) . '</td><td><strong>' . money($s['spent'], 'QAR', false) . '</strong> (' . $s['spent_n'] . ')</td></tr>'
            . '<tr><td style="padding:4px 14px 4px 0">' . e(__('cron.overdue_total')) . '</td><td><strong>' . money($s['overdue'], 'QAR', false) . '</strong></td></tr>'
            . '<tr><td style="padding:4px 14px 4px 0">' . e(__('cms.new_messages')) . '</td><td><strong>' . $s['inquiries'] . '</strong></td></tr></table>';
        $h .= $sec(__('dashboard.waiting_approval'), array_map(fn ($a) => e($a['title']) . ($a['amount_qar'] !== null ? ' — ' . money($a['amount_qar'], 'QAR', false) : ''), $s['approvals']));
        $h .= $sec(__('cron.docs_expired'), array_map(fn ($d) => e($d['name'] . ' · ' . __($d['doc']) . ' · ' . fmt_date($d['date'])), $s['expired']));
        $h .= $sec(__('cron.docs_expiring'), array_map(fn ($d) => e($d['name'] . ' · ' . __($d['doc']) . ' · ' . fmt_date($d['date'])), $s['expiring']));
        $h .= $sec(__('cron.foalings'), array_map(fn ($f) => e($f['name_en'] . ' · ' . fmt_date($f['d'])), $s['foalings']));
        $h .= $sec(__('cron.low_stock_list'), array_map(fn ($i) => e($i['name_en'] . ' · ' . rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit']), $s['low']));
        return $h . '<p><a href="' . e(absolute_url('/portal')) . '" style="background:#0f1f45;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px">' . e(__('cron.open_portal')) . '</a></p>';
    }

    private static function summaryText(array $s): string
    {
        Lang::set('en');
        $lines = ['*SK Arabians — ' . fmt_date(date('Y-m-d')) . '*'];
        $lines[] = 'Approvals waiting: ' . count($s['approvals']);
        $lines[] = 'Documents expired: ' . count($s['expired']) . ', expiring: ' . count($s['expiring']);
        $lines[] = 'Foalings due (30 days): ' . count($s['foalings']);
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
        $failed = DB::all('SELECT username, ip, COUNT(*) AS n FROM login_attempts WHERE success = 0 AND created_at >= ? GROUP BY username, ip ORDER BY n DESC LIMIT 20', [$since]);
        $sections = [
            [__('security.failed_7d'), array_map(fn ($f) => e($f['username'] . ' · ' . $f['ip'] . ' · ×' . $f['n']), $failed)],
            [__('cron.locked'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary']), $q("action = 'account_locked'"))],
            [__('cron.new_users'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $q("action = 'create' AND module = 'users'"))],
            [__('cron.permission_changes'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $q("action IN ('permissions','grant','revoke','reset_2fa','reset_password')"))],
            [__('cron.large_exports'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ')'), $q("action = 'export' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.rows')) AS UNSIGNED) >= ?", [$big]))],
            [__('cron.denied'), array_map(fn ($r) => e(fmt_date($r['created_at'], true) . ' · ' . $r['summary'] . ' (' . $r['user_name'] . ' · ' . $r['ip'] . ')'), $q("action = 'access_denied'"))],
        ];
        [$chainOk, $n, $broken] = Audit::verify();
        $html = '<p>' . e(__('cron.security_intro')) . '</p><p><strong>' . e(__('security.log_chain')) . ':</strong> ' . e($chainOk ? __('security.intact', ['n' => $n]) : __('security.broken', ['id' => $broken])) . '</p>';
        foreach ($sections as [$title, $items]) {
            $html .= '<h3 style="color:#0f1f45;margin:16px 0 6px">' . e($title) . ' (' . count($items) . ')</h3>'
                . ($items ? '<ul style="margin:0;padding-inline-start:18px"><li>' . implode('</li><li>', $items) . '</li></ul>' : '<p style="color:#777">—</p>');
        }
        $sent = 0;
        foreach (DB::all("SELECT u.email, u.lang FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") as $o) {
            $sent += Mailer::send($o['email'], __('cron.security_subject'), $html) ? 1 : 0;
        }
        return "sent to $sent owner(s)";
    }

    private static function backup(): string
    {
        $files = BackupService::run('cron');
        $out = implode(', ', $files);
        $off = Offsite::upload($files);
        if ($off !== null) {
            $out .= ' | off-server: ' . $off;
        }
        [$ok, $msg] = BackupService::restoreTest();
        if (!$ok) {
            Notifier::owners('system', __('backups.test_bad'), $msg, '/portal/settings/backups', true, 'restore-fail-' . date('Ymd'));
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
