<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/** Small file cache for dashboard statistics. Bumping the version invalidates everything after a write. */
final class Cache
{
    private static function dir(): string
    {
        return Config::storagePath('cache');
    }

    private static function version(): string
    {
        $f = self::dir() . '/_version';
        return is_file($f) ? trim((string) file_get_contents($f)) : '0';
    }

    public static function bump(): void
    {
        @file_put_contents(self::dir() . '/_version', (string) microtime(true), LOCK_EX);
    }

    public static function remember(string $key, int $ttl, callable $fn): mixed
    {
        $file = self::dir() . '/' . sha1($key . '|' . self::version()) . '.cache';
        if (is_file($file) && filemtime($file) > time() - $ttl) {
            $data = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
            if ($data !== false) {
                return $data;
            }
        }
        $value = $fn();
        @file_put_contents($file, serialize($value), LOCK_EX);
        return $value;
    }

    public static function clearOld(): void
    {
        foreach (glob(self::dir() . '/*.cache') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
