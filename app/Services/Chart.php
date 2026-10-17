<?php
declare(strict_types=1);

namespace App\Services;

/** Server-rendered SVG charts (no JavaScript library, CSP friendly, prints well). */
final class Chart
{
    /**
     * Grouped bars: $labels = [..], $series = [[name, colour, [values..]], ..]. $every: write every n-th label under
     * the bars (many days side by side would overlap); each bar's hover text always names its own label. $whole: the
     * values are counts (hover text without decimals).
     */
    public static function bars(array $labels, array $series, int $w = 720, int $h = 260, int $every = 1, bool $whole = false): string
    {
        $max = 0.0;
        foreach ($series as $s) {
            $max = max($max, ...array_map('floatval', $s[2] ?: [0]));
        }
        $max = $max > 0 ? self::nice($max) : 1;
        if ($whole) {
            $max = 4 * max(1, (int) ceil($max / 4)); // counts: whole numbers on the four scale lines (0, 1, 2, 3, 4 — not 0.5)
        }
        $padL = 54;
        $padB = 28;
        $padT = 10;
        $cw = $w - $padL - 10;
        $ch = $h - $padB - $padT;
        $n = max(1, count($labels));
        $group = $cw / $n;
        $bw = min(22, ($group * 0.7) / max(1, count($series)));
        if (!array_filter(array_merge([], ...array_map(fn ($s) => array_map('floatval', array_values($s[2] ?: [0])), $series)))) {
            return self::empty();
        }
        $names = implode(', ', array_map(fn ($s) => (string) $s[0], $series));
        $svg = '<div class="chart-box"><svg viewBox="0 0 ' . $w . ' ' . $h . '" class="chart-svg" preserveAspectRatio="xMidYMid meet" direction="ltr" role="img" aria-label="' . e($names) . '" xmlns="http://www.w3.org/2000/svg">';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $ch - $ch * $i / 4;
            $svg .= '<line class="c-grid" x1="' . $padL . '" x2="' . ($w - 10) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $svg .= '<text class="cy" x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . self::short($max * $i / 4) . '</text>';
        }
        foreach ($labels as $i => $l) {
            $gx = $padL + $group * $i + ($group - $bw * count($series)) / 2;
            foreach ($series as $k => [$name, $color, $vals]) {
                $v = (float) ($vals[$i] ?? 0);
                $bh = $ch * $v / $max;
                $tip = $name . ' · ' . $l . ': ' . number_format($v, $whole ? 0 : 2);
                $svg .= '<rect class="bar ' . self::tone($color, $k) . '" x="' . round($gx + $k * $bw, 1) . '" y="' . round($padT + $ch - $bh, 1) . '" width="' . round($bw - 2, 1) . '" height="' . round($bh, 1) . '" rx="2"'
                    . (self::tone($color, $k) === 's-x' ? ' fill="' . e($color) . '"' : '') . ' data-tip="' . e($tip) . '" aria-label="' . e($tip) . '"/>';
            }
            if ($i % max(1, $every) === 0) {
                // with many labels a phone shows every other one (portal.css: larger text there)
                $alt = $every === 1 && $n > 8 && $i % 2 === 1 ? ' alt' : '';
                $svg .= '<text class="cx' . $alt . '" x="' . round($padL + $group * $i + $group / 2, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e($l) . '</text>';
            }
        }
        return $svg . '</svg></div>';
    }

    /** The CSS class of a series colour (portal.css .s-a gold / .s-b navy follow the theme); other colours stay as given. */
    private static function tone(string $color, int $k = 0): string
    {
        return match (strtolower($color)) { '#b08d57', '#a9854f', 'gold', 'a' => 's-a', '#213562', '#1c2c52', 'navy', 'b' => 's-b', default => 's-x' };
    }

    /** "No figures yet" in place of an empty drawing. */
    public static function empty(?string $message = null): string
    {
        return '<div class="chart-empty">' . icon('chart') . '<span>' . e($message ?? __('ui.chart_empty')) . '</span></div>';
    }

    /**
     * Horizontal bars: [[label, value], ..] as page rows (label, bar, value), so the words stay readable at any width —
     * on a phone the bar goes under its label (portal.css .hbars).
     */
    public static function hbars(array $rows, string $color = '#213562'): string
    {
        if (!$rows) {
            return '';
        }
        $max = max(array_map(fn ($r) => (float) $r[1], $rows)) ?: 1;
        $tone = match (self::tone($color)) { 's-b' => ' hb-navy', 's-a' => ' hb-gold', default => '' };
        $out = '<div class="hbars">';
        foreach ($rows as [$label, $v]) {
            $pct = max(0.5, round(100 * (float) $v / $max, 1));
            $out .= '<div class="hb-row"><span class="hb-label" title="' . e((string) $label) . '">' . e((string) $label) . '</span>'
                . '<span class="hb-track"><span class="hb-bar' . $tone . '" style="width:' . $pct . '%' . ($tone ? '' : ';background:' . e($color)) . '"></span></span>'
                . '<span class="hb-val">' . number_format((float) $v, 0) . '</span></div>';
        }
        return $out . '</div>';
    }

    private static function nice(float $v): float
    {
        $exp = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $exp >= $v) {
                return $m * $exp;
            }
        }
        return 10 * $exp;
    }

    private static function short(float $v): string
    {
        return $v >= 1_000_000 ? round($v / 1_000_000, 1) . 'M' : ($v >= 1000 ? round($v / 1000, 1) . 'k' : (string) round($v));
    }
}
