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

    /**
     * Normalises user input like "4,604.32", "-12.5" or "١٢٣٫٥" into a float. Only plain decimal numbers are
     * accepted (thousands separators, Arabic-Indic digits and a leading minus are fine): no exponents ("1e5"),
     * hex, or other forms PHP's is_numeric() would let through.
     */
    public static function parse(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        $v = str_replace([' ', "\u{00A0}", 'QAR'], '', trim((string) $value));
        // Accept Arabic-Indic and Persian digits, the Arabic decimal point and thousands separator, and the Unicode minus
        $v = strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => ',', '−' => '-']);
        // Optional sign, digits with optional "," group separators (1,000 and 1,00,000 alike), optional decimals
        if (!preg_match('/^[-+]?(?:\d+(?:,\d+)*)?(?:\.\d*)?$/', $v) || !preg_match('/\d/', $v)) {
            return null;
        }
        return (float) str_replace(',', '', $v);
    }
}
