<?php
declare(strict_types=1);

namespace App\Core;

/** Hardened PHP session: private save path, HttpOnly + Secure + SameSite cookies, strict mode. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $path = Config::storagePath('sessions');
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) max(1800, (int) Config::get('security.session_timeout', 1800)));
        session_name('SKASESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    /** Keep submitted form values (never passwords) for re-display after a validation error. */
    public static function keepOld(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_csrf']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_current'][$key] ?? $default;
    }

    /** Called once per request: moves "old" input to the current request so it lives exactly one page view. */
    public static function ageOld(): void
    {
        $_SESSION['_old_current'] = $_SESSION['_old'] ?? [];
        $_SESSION['_errors_current'] = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);
    }

    public static function errors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function error(string $field): ?string
    {
        return $_SESSION['_errors_current'][$field] ?? null;
    }
}
