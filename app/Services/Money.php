<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Currency rules: base currency QAR, rates are "1 unit = X QAR", QAR value calculated once at entry, 2 decimals. */
final class Money
{
    public const BASE = 'QAR';

    public static function currencies(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM currencies' . ($activeOnly ? ' WHERE active = 1' : '') . " ORDER BY code = 'QAR' DESC, code");
    }

    public static function rate(string $code): float
    {
        if ($code === self::BASE) {
            return 1.0;
        }
        $r = DB::value('SELECT rate_to_qar FROM currencies WHERE code = ?', [$code]);
        if ($r === null) {
            throw new \InvalidArgumentException('Unknown currency ' . $code);
        }
        return (float) $r;
    }

    public static function toQar(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }

    /** Normalises user input like "4,604.32" into a float. */
    public static function parse(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = str_replace([',', ' ', 'QAR'], '', (string) $value);
        // Accept Arabic-Indic digits
        $v = strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);
        return is_numeric($v) ? (float) $v : null;
    }
}
