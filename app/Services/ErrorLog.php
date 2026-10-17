<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;

/**
 * Reads the private error log (storage/logs/app-YYYY-MM.log, written by ErrorHandler) for the Owner's
 * Administration → System errors page: this month's and last month's entries, newest first.
 * An entry: "[time] [reference] Class: message @ file:line {page, method, user}" with the call trace below it.
 */
final class ErrorLog
{
    /** Read at most this much of the end of each month's log. */
    private const TAIL_BYTES = 2_000_000;

    /** @return array<int, array{time:string, ref:string, head:string, ctx:array, trace:string[], kept_going:bool}> newest first */
    public static function entries(): array
    {
        $out = [];
        foreach ([date('Y-m'), date('Y-m', strtotime(date('Y-m-01') . ' -1 month'))] as $month) {
            $text = self::tail(Config::storagePath('logs/app-' . $month . '.log'), self::TAIL_BYTES);
            $cur = null;
            foreach (preg_split('/\r\n|\n/', $text) as $line) {
                if (preg_match('/^\[([^\]]+)\] \[([0-9a-f]{8})\] (.*)$/', $line, $m)) {
                    if ($cur) {
                        $out[] = self::finish($cur);
                    }
                    $cur = ['time' => $m[1], 'ref' => $m[2], 'head' => $m[3], 'trace' => []];
                } elseif ($cur && $line !== '') {
                    $cur['trace'][] = $line;
                }
            }
            if ($cur) {
                $out[] = self::finish($cur);
            }
        }
        usort($out, fn ($a, $b) => strcmp($b['time'], $a['time']));
        return $out;
    }

    public static function find(string $ref): ?array
    {
        foreach (self::entries() as $e) {
            if ($e['ref'] === strtolower($ref)) {
                return $e;
            }
        }
        return null;
    }

    /** The last lines of the server's own PHP error log (problems PHP itself stopped on, e.g. out of memory). */
    public static function phpTail(int $lines = 40): array
    {
        $text = self::tail(Config::storagePath('logs/php-error.log'), 60_000);
        $all = array_values(array_filter(preg_split('/\r\n|\n/', $text), fn ($l) => trim($l) !== ''));
        return array_map(fn ($l) => self::clean($l), array_slice($all, -$lines));
    }

    /** Where in the program: the first line of the trace in the application's own code (else the place of the error). */
    public static function where(array $e): string
    {
        foreach ($e['trace'] as $t) {
            if (preg_match('#(app/(?:Controllers|Services|Resources|Views)/[^(]+)\((\d+)\)#', $t, $m)) {
                return $m[1] . ':' . $m[2];
            }
        }
        return preg_match('/ @ (\S+:\d+)/', $e['head'], $m) ? $m[1] : '';
    }

    /** What the server runs, for whoever maintains the system: the PHP and database versions and the database's SQL mode. */
    public static function server(): array
    {
        $out = ['php' => PHP_VERSION, 'db' => '—', 'mode' => '—'];
        try {
            $out['db'] = (string) DB::value('SELECT VERSION()');
            $out['mode'] = (string) DB::value('SELECT @@GLOBAL.sql_mode') ?: '—';
        } catch (\Throwable) {
            // not essential
        }
        return $out;
    }

    /** The user's name for an entry (the context's user id). */
    public static function userName(array $e): string
    {
        $id = (int) ($e['ctx']['user'] ?? 0);
        return $id ? (string) (DB::value('SELECT name FROM users WHERE id = ?', [$id]) ?? '#' . $id) : '';
    }

    private static function finish(array $e): array
    {
        // the page, method and user: at the end of the first line (entries before 8 Oct 2026: at the end of the trace)
        $ctx = [];
        $take = function (string $text) use (&$ctx): string {
            if (($p = strrpos($text, ' {"')) !== false && is_array($j = json_decode(substr($text, $p + 1), true))) {
                $ctx += $j;
                return substr($text, 0, $p);
            }
            return $text;
        };
        $e['head'] = self::clean($take($e['head']));
        if ($e['trace']) {
            $last = count($e['trace']) - 1;
            $e['trace'][$last] = $take($e['trace'][$last]);
        }
        $e['trace'] = array_map(fn ($t) => self::clean($t), $e['trace']);
        $e['ctx'] = $ctx;
        $e['kept_going'] = !empty($ctx['kept_going']);
        return $e;
    }

    /** The server's full paths shortened to the program's own ("app/…"). */
    private static function clean(string $text): string
    {
        $root = rtrim(APP_ROOT, '/\\');
        return str_replace([$root . '/', $root . '\\', str_replace('\\', '/', $root) . '/'], '', $text);
    }

    private static function tail(string $file, int $bytes): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }
        $size = (int) filesize($file);
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return '';
        }
        if ($size > $bytes) {
            fseek($fh, $size - $bytes);
            fgets($fh); // start at a whole line
        }
        $text = (string) stream_get_contents($fh);
        fclose($fh);
        return $text;
    }
}
