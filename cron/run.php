<?php
/**
 * SK Arabians scheduled tasks. Add ONE cron job in cPanel, every 15 minutes:
  *   0,15,30,45 * * * *  /usr/local/bin/php /home/USER/skarabian/cron/run.php >/dev/null 2>&1
 *
 *   php cron/run.php              run whatever is due (reminders, overdue bills, rates, backups, summaries...)
 *   php cron/run.php backup       run one task now (overdue, cleanup, rates, categories, reminders, summary, backup, security)
 *   php cron/run.php --list       show each task and when it last ran
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Cron;

$arg = $argv[1] ?? null;
if ($arg === '--list') {
    foreach (Cron::TASKS as $task => [$every, $at]) {
        $last = DB::row('SELECT started_at, ok, message FROM cron_runs WHERE task = ? ORDER BY id DESC LIMIT 1', [$task]);
        printf("%-11s %-7s %-34s %s\n", $task, $every, $at ?? '', $last ? $last['started_at'] . ($last['ok'] ? ' ok ' : ' FAILED ') . $last['message'] : 'never');
    }
    exit(0);
}

// Only one copy at a time (a slow backup must not overlap the next run)
$lock = fopen(App\Core\Config::storagePath('cron.lock'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "already running\n";
    exit(0);
}
$lines = $arg ? [Cron::run($arg)] : Cron::runDue();
echo implode(PHP_EOL, $lines), PHP_EOL;
flock($lock, LOCK_UN);
