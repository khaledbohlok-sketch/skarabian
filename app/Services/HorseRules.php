<?php
declare(strict_types=1);

namespace App\Services;

/** Horse business rules: category from age and sex, gestation. */
final class HorseRules
{
    public const GESTATION_DAYS = 340;

    /**
     * Foal: under 1 year. Colt / Filly: 1 to under 4 years. Stallion / Mare: 4 years and older. Geldings stay Gelding.
     * Fixes the old system that labelled foals as Mare/Stallion.
     */
    public static function category(string $sex, ?string $dob, ?string $today = null): string
    {
        if ($sex === 'gelding') {
            return 'gelding';
        }
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
