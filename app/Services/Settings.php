<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/** Key/value settings editable from the portal (contact details, approval limits, CMS texts...). */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = DB::pairs('SELECT `key`, `value` FROM settings');
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = self::all()[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::run(
            'INSERT INTO settings (`key`, `value`, updated_at, updated_by) VALUES (?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW(), updated_by = VALUES(updated_by)',
            [$key, $value, Auth::id()]
        );
        self::$cache = null;
    }

    public static function float(string $key, float $default = 0): float
    {
        return (float) self::get($key, $default);
    }
}
