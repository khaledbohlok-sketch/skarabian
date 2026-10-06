<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Errors are written to a private log (storage/logs) and never shown to users,
 * unless app.debug is enabled on a local machine.
 */
final class ErrorHandler
{
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
            throw new \ErrorException($str, 0, $no, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
    }

    public static function log(\Throwable|string $e, array $context = []): string
    {
        $id = bin2hex(random_bytes(4));
        $msg = $e instanceof \Throwable
            ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
            : $e;
        $line = sprintf("[%s] [%s] %s %s\n", date('c'), $id, $msg, $context ? json_encode($context) : '');
        @file_put_contents(Config::storagePath('logs/app-' . date('Y-m') . '.log'), $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public static function handle(\Throwable $e): void
    {
        $id = self::log($e);
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
            echo View::render('errors/500', ['ref' => $id], 'error');
        } catch (\Throwable) {
            echo 'An unexpected error occurred. Reference: ' . $id;
        }
    }
}
