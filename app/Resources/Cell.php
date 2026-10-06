<?php
declare(strict_types=1);

namespace App\Resources;

/** Renders one list cell according to the column format. Every value is escaped. */
final class Cell
{
    public static function render(mixed $v, array $c, array $row): string
    {
        $fmt = $c['fmt'] ?? 'text';
        if (($v === null || $v === '') && $fmt !== 'bool') {
            return '<span class="muted">—</span>';
        }
        return match ($fmt) {
            'money'  => money($v),
            'num'    => e(rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.')),
            'date'   => e(fmt_date((string) $v)),
            'datetime' => e(fmt_date((string) $v, true)),
            'expiry' => '<span class="' . expiry_class((string) $v) . '">' . e(fmt_date((string) $v)) . self::expiryNote((string) $v) . '</span>',
            'bool'   => $v ? '<span class="tick" title="' . e(__('common.yes')) . '">✓</span>' : '',
            'badge'  => status_badge((string) $v, $c['prefix'] ?? 'status'),
            'enum'   => e(__(($c['prefix'] ?? 'status') . '.' . $v)),
            'lang'   => e(__((string) $v)),
            'thumb'  => '<img class="thumb" src="' . e(url('/portal/files/' . (int) $v . '/thumb')) . '" alt="" loading="lazy" width="44" height="44">',
            'age'    => e(horse_age((string) $v)),
            default  => e(mb_strimwidth((string) $v, 0, 80, '…')),
        };
    }

    private static function expiryNote(string $date): string
    {
        $d = days_until($date);
        if ($d === null) {
            return '';
        }
        if ($d < 0) {
            return ' <small>(' . e(__('common.expired')) . ')</small>';
        }
        return $d <= 60 ? ' <small>(' . e(__('common.days_left', ['n' => $d])) . ')</small>' : '';
    }
}
