<?php
declare(strict_types=1);

namespace App\Services;

/** Horse business rules: category from age and sex, gestation, pregnancy-check days. */
final class HorseRules
{
    public const GESTATION_DAYS = 340;

    /** Pregnancy checks after a cover / insemination / embryo transfer (days after the start date). */
    public const PREGNANCY_CHECK_DAYS = [14, 30, 45, 60];

    /** Horses counted as at the stud (dashboard counts, website, active lists); sold, exported, leased out and deceased are not. */
    public const AT_SK_STATUSES = ['active', 'in_shelter'];

    /**
     * Foal: under 1 year. Colt / Filly: 1 to under 4 years. Stallion / Mare: 4 years and older.
     * Fixes the old system that labelled foals as Mare/Stallion.
     */
    public static function category(string $sex, ?string $dob, ?string $today = null): string
    {
        if (!$dob) {
            return $sex === 'male' ? 'stallion' : 'mare';
        }
        $years = (new \DateTime($dob))->diff(new \DateTime($today ?? 'today'))->y;
        if ($years < 1) {
            return 'foal';
        }
        if ($years < 4) {
            return $sex === 'male' ? 'colt' : 'filly';
        }
        return $sex === 'male' ? 'stallion' : 'mare';
    }

    public static function expectedFoaling(?string $start, int $days = self::GESTATION_DAYS): ?string
    {
        return $start ? date('Y-m-d', strtotime($start . ' +' . $days . ' days')) : null;
    }
}
