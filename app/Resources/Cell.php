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
        $html = self::format($v, $fmt, $c, $row);
        // 'to' => ['horses', '_sire_id']: the value links to that record (e.g. an embryo's sire), when the user may open that list
        if (!empty($c['to']) && !empty($row[$c['to'][1]]) && self::canOpen((string) $c['to'][0])) {
            return '<a class="cell-link" href="' . e(url('/portal/' . $c['to'][0] . '/' . (int) $row[$c['to'][1]])) . '">' . $html . '</a>';
        }
        return $html;
    }

    private static function canOpen(string $resource): bool
    {
        static $can = [];
        return $can[$resource] ??= (bool) (($r = Registry::get($resource)) && $r->canList());
    }

    private static function format(mixed $v, string $fmt, array $c, array $row): string
    {
        return match ($fmt) {
            'money'  => money($v),
            'num'    => e(rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.')),
            'date'   => e(fmt_date((string) $v)),
            'datetime' => e(fmt_date((string) $v, true)),
            'expiry' => '<span class="' . expiry_class((string) $v) . '">' . e(fmt_date((string) $v)) . self::expiryNote((string) $v) . '</span>',
            'bool'   => $v ? '<span class="tick" title="' . e(__('common.yes')) . '">✓</span>' : '',
            'badge'  => status_badge((string) $v, $c['prefix'] ?? 'status'),
            'enum'   => e(enum_label($c['prefix'] ?? 'status', (string) $v)),
            'lang'   => e(__((string) $v)),
            'lookup' => e(lookup_label($c['lookup'] ?? '', (string) $v)),
            'thumb'  => '<img class="thumb" src="' . e(url('/portal/files/' . (int) $v . '/thumb')) . '" alt="" loading="lazy" width="44" height="44">',
            'age'    => e(horse_age((string) $v)),
            'swatch' => '<span class="swatch" style="background:' . (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? e((string) $v) : '#8a8f98') . '"></span>',
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
