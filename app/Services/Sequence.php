<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Gap-safe auto numbering: BILL-202610-0001, PO-202610-0001, EMB-2026-001, SKA-SAL-2026-0001 ... */
final class Sequence
{
    /** Marks a number not taken yet (see placeholder()). */
    public const PLACEHOLDER_PREFIX = 'TMP-';

    /**
     * A unique temporary value (20 characters) for a NOT NULL UNIQUE number column, stored by the form's insert and
     * replaced with the real number in the resource's afterSave() — inside the save transaction, so a save that
     * fails rolls the sequence back too and no number is burnt.
     */
    public static function placeholder(): string
    {
        return self::PLACEHOLDER_PREFIX . bin2hex(random_bytes(8));
    }

    public static function next(string $name, string $period): int
    {
        return DB::transaction(function () use ($name, $period) {
            // One statement creates the row or counts on, taking its exclusive lock at once: two numbers issued at the
            // same moment wait for each other (INSERT IGNORE's shared lock, then FOR UPDATE, could deadlock them)
            DB::run('INSERT INTO sequences (name, period, `last_value`) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE `last_value` = `last_value` + 1', [$name, $period]);
            return (int) DB::value('SELECT `last_value` FROM sequences WHERE name = ? AND period = ? FOR UPDATE', [$name, $period]);
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
