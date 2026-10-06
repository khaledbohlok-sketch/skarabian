<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $pathCache = null;
    public static array $routeParams = [];

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Request path relative to the front controller, always starting with "/". */
    public static function path(): string
    {
        if (self::$pathCache !== null) {
            return self::$pathCache;
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        return self::$pathCache = $uri;
    }

    public static function basePath(): string
    {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        return rtrim($script === '/' || $script === '.' ? '' : $script, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function file(string $key): ?array
    {
        return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null;
    }

    public static function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = Config::get('app.trusted_proxies', []);
        if ($trusted && in_array($remote, $trusted, true)) {
            $h = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($h !== '') {
                $ip = trim(explode(',', $h)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $remote;
    }

    public static function userAgent(): string
    {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    /** Short readable device name from the user agent ("iPhone · Safari"). */
    public static function device(): string
    {
        $ua = self::userAgent();
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
        $br = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };
        return "$os · $br";
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_starts_with(self::path(), '/portal/api/');
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    public static function param(string $name): ?string
    {
        return self::$routeParams[$name] ?? null;
    }
}
