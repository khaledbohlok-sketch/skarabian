<?php
declare(strict_types=1);

namespace App\Core;

/** Single configuration source (config/config.php), read with dotted keys. */
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Missing config file: $file (copy config/config.sample.php)\n");
                exit(1);
            }
            http_response_code(503);
            exit('Configuration missing. See docs/INSTALL_CPANEL.md');
        }
        self::$data = require $file;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$data;
        foreach (explode('.', $key) as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = self::get('storage_path') ?: APP_ROOT . '/storage';
        return rtrim($base, '/') . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }
}
