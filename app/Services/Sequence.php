<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Gap-safe auto numbering: BILL-202610-0001, PO-202610-0001, EMB-2026-001, SKA-SAL-2026-0001 ... */
final class Sequence
{
    public static function next(string $name, string $period): int
    {
        return DB::transaction(function () use ($name, $period) {
            DB::run('INSERT IGNORE INTO sequences (name, period, last_value) VALUES (?, ?, 0)', [$name, $period]);
            $v = (int) DB::value('SELECT last_value FROM sequences WHERE name = ? AND period = ? FOR UPDATE', [$name, $period]) + 1;
            DB::run('UPDATE sequences SET last_value = ? WHERE name = ? AND period = ?', [$v, $name, $period]);
            return $v;
        });
    }

    public static function bill(?string $date = null): string
    {
        $p = date('Ym', strtotime($date ?? 'now'));
        return sprintf('BILL-%s-%04d', $p, self::next('BILL', $p));
    }

    public static function monthly(string $prefix, ?string $date = null): string
    {
        $p = date('Ym', strtotime($date ?? 'now'));
        return sprintf('%s-%s-%04d', $prefix, $p, self::next($prefix, $p));
    }

    public static function embryo(?string $date = null): string
    {
        $y = date('Y', strtotime($date ?? 'now'));
        return sprintf('EMB-%s-%03d', $y, self::next('EMB', $y));
    }

    public static function document(string $code, ?string $date = null): string
    {
        $y = date('Y', strtotime($date ?? 'now'));
        return sprintf('SKA-%s-%s-%04d', $code, $y, self::next('DOC-' . $code, $y));
    }
}
