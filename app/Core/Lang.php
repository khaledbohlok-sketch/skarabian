<?php
declare(strict_types=1);

namespace App\Core;

/** English / Arabic translations. All labels live in lang/en.php and lang/ar.php. */
final class Lang
{
    public const SUPPORTED = ['en', 'ar'];
    private static string $current = 'en';
    private static array $strings = [];

    public static function set(string $lang): void
    {
        self::$current = in_array($lang, self::SUPPORTED, true) ? $lang : 'en';
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function isRtl(): bool
    {
        return self::$current === 'ar';
    }

    public static function dir(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    public static function strings(string $lang): array
    {
        if (!isset(self::$strings[$lang])) {
            $file = APP_ROOT . '/lang/' . $lang . '.php';
            self::$strings[$lang] = is_file($file) ? require $file : [];
        }
        return self::$strings[$lang];
    }

    public static function get(string $key, array $params = [], ?string $lang = null): string
    {
        $lang ??= self::$current;
        $s = self::strings($lang)[$key] ?? self::strings('en')[$key] ?? null;
        if ($s === null) {
            // Readable fallback so a missing key never breaks a page (tests/lang_check.php reports missing keys)
            $s = ucfirst(str_replace('_', ' ', substr($key, (int) strrpos($key, '.') + (str_contains($key, '.') ? 1 : 0))));
        }
        foreach ($params as $k => $v) {
            $s = str_replace(':' . $k, (string) $v, $s);
        }
        return $s;
    }

    public static function has(string $key): bool
    {
        return isset(self::strings(self::$current)[$key]);
    }
}
