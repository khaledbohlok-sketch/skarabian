<?php
declare(strict_types=1);

namespace App\Services;

/** Server-rendered SVG charts (no JavaScript library, CSP friendly, prints well). */
final class Chart
{
    /** Grouped bars: $labels = [..], $series = [[name, colour, [values..]], ..] */
    public static function bars(array $labels, array $series, int $w = 720, int $h = 260): string
    {
        $max = 0.0;
        foreach ($series as $s) {
            $max = max($max, ...array_map('floatval', $s[2] ?: [0]));
        }
        $max = $max > 0 ? self::nice($max) : 1;
        $padL = 54;
        $padB = 28;
        $padT = 10;
        $cw = $w - $padL - 10;
        $ch = $h - $padB - $padT;
        $n = max(1, count($labels));
        $group = $cw / $n;
        $bw = min(22, ($group * 0.7) / max(1, count($series)));
        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" xmlns="http://www.w3.org/2000/svg" font-family="Inter, Tajawal, sans-serif" font-size="11">';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $ch - $ch * $i / 4;
            $svg .= '<line x1="' . $padL . '" x2="' . ($w - 10) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="#e6e9ef"/>';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end" fill="#6b7488">' . self::short($max * $i / 4) . '</text>';
        }
        foreach ($labels as $i => $l) {
            $gx = $padL + $group * $i + ($group - $bw * count($series)) / 2;
            foreach ($series as $k => [$name, $color, $vals]) {
                $v = (float) ($vals[$i] ?? 0);
                $bh = $ch * $v / $max;
                $svg .= '<rect x="' . round($gx + $k * $bw, 1) . '" y="' . round($padT + $ch - $bh, 1) . '" width="' . round($bw - 2, 1) . '" height="' . round($bh, 1) . '" rx="2" fill="' . $color . '"><title>' . e($name . ' ' . $l . ': ' . number_format($v, 2)) . '</title></rect>';
            }
            $svg .= '<text x="' . round($padL + $group * $i + $group / 2, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" fill="#6b7488">' . e($l) . '</text>';
        }
        return $svg . '</svg>';
    }

    /** Horizontal bars: [[label, value], ..] */
    public static function hbars(array $rows, string $color = '#213562', int $w = 520): string
    {
        if (!$rows) {
            return '';
        }
        $max = max(array_map(fn ($r) => (float) $r[1], $rows)) ?: 1;
        $rowH = 30;
        $h = count($rows) * $rowH + 6;
        $labelW = 150;
        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" xmlns="http://www.w3.org/2000/svg" font-family="Inter, Tajawal, sans-serif" font-size="12">';
        foreach ($rows as $i => [$label, $v]) {
            $y = $i * $rowH + 4;
            $bw = ($w - $labelW - 90) * ((float) $v / $max);
            $svg .= '<text x="0" y="' . ($y + 15) . '" fill="#1b2333">' . e(mb_strimwidth((string) $label, 0, 22, '…')) . '</text>';
            $svg .= '<rect x="' . $labelW . '" y="' . ($y + 4) . '" width="' . round(max(2, $bw), 1) . '" height="14" rx="3" fill="' . $color . '"/>';
            $svg .= '<text x="' . round($labelW + $bw + 6, 1) . '" y="' . ($y + 15) . '" fill="#6b7488">' . number_format((float) $v, 0) . '</text>';
        }
        return $svg . '</svg>';
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
