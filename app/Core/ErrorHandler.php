<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Errors are written to a private log (storage/logs) and never shown to users, unless app.debug is enabled on a local
 * machine. The Owner sees what went wrong on the error page and in Administration → System errors.
 *
 * A notice or warning while a page is being shown (GET, live site) is written to the log and the page carries on —
 * a missing value must not take a whole page down. While something is being saved (POST) it stops the request, so
 * nothing half-done is saved; on a local machine (debug) and in the command-line tests every one stops.
 */
final class ErrorHandler
{
    /** Tests switch the live-site behaviour on or off; null = decided from the request. */
    public static ?bool $softOverride = null;

    /** The places already logged in this request (a warning in a loop is written once). */
    private static array $seen = [];

    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('app.debug') ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', Config::storagePath('logs/php-error.log'));

        set_error_handler(function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            $e = new \ErrorException($str, 0, $no, $file, $line);
            if (self::soft($no)) {
                if (!isset(self::$seen[$file . ':' . $line])) {
                    self::$seen[$file . ':' . $line] = true;
                    self::log($e, self::requestContext() + ['kept_going' => true]);
                }
                return true;
            }
            throw $e;
        });

        set_exception_handler([self::class, 'handle']);

        // The errors PHP cannot hand to the handler above (a file that will not compile, a function declared twice, out
        // of memory) used to leave a blank white page: they get the same error page, reference and log entry instead
        register_shutdown_function(function (): void {
            $err = error_get_last();
            if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            self::handle(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
        });
    }

    /** Whether this kind of problem is logged and the page carries on (see the class note). */
    public static function soft(int $no): bool
    {
        if (!in_array($no, [E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED], true)) {
            return false;
        }
        if (self::$softOverride !== null) {
            return self::$softOverride;
        }
        return !Config::get('app.debug') && PHP_SAPI !== 'cli' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
    }

    /**
     * Writes one entry: "[time] [reference] What happened @ file:line {page, method, user}" and the call trace below it.
     * Returns the reference shown to the user.
     */
    public static function log(\Throwable|string $e, array $context = []): string
    {
        $id = bin2hex(random_bytes(4));
        $head = $e instanceof \Throwable ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : $e;
        $trace = $e instanceof \Throwable ? "\n" . $e->getTraceAsString() : '';
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf("[%s] [%s] %s%s%s\n", date('c'), $id, $head, $ctx, $trace);
        @file_put_contents(Config::storagePath('logs/app-' . date('Y-m') . '.log'), $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    /** The page, how it was asked for and who asked (for the Owner's System errors page). */
    public static function requestContext(): array
    {
        if (PHP_SAPI === 'cli') {
            return [];
        }
        $c = ['method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''), 'page' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 300)];
        try {
            if ($u = Auth::user()) {
                $c['user'] = (int) $u['id'];
            }
        } catch (\Throwable) {
            // before the session is known
        }
        return $c;
    }

    public static function handle(\Throwable $e): void
    {
        $id = self::log($e, self::requestContext());
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Error [$id]: " . $e->getMessage() . "\n" . (Config::get('app.debug') ? $e->getTraceAsString() . "\n" : ''));
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (Config::get('app.debug')) {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
            return;
        }
        try {
            echo View::render('errors/500', ['ref' => $id, 'detail' => self::ownerDetail($e)], 'error');
        } catch (\Throwable) {
            echo 'An unexpected error occurred. Reference: ' . $id;
        }
    }

    /** The Owner sees what went wrong and where; everyone else only the reference. */
    public static function ownerDetail(\Throwable $e): ?string
    {
        try {
            if (Auth::check() && Auth::isOwner()) {
                return get_class($e) . ': ' . $e->getMessage() . ' — ' . self::shortPath($e->getFile()) . ':' . $e->getLine();
            }
        } catch (\Throwable) {
            // no user to ask about
        }
        return null;
    }

    /** "app/Views/portal/…/x.php" instead of the server's full path. */
    public static function shortPath(string $file): string
    {
        $root = defined('APP_ROOT') ? rtrim(str_replace('\\', '/', APP_ROOT), '/') . '/' : '';
        $f = str_replace('\\', '/', $file);
        return $root !== '' && str_starts_with($f, $root) ? substr($f, strlen($root)) : $f;
    }
}
