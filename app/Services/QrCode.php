<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Self-contained QR code generator (ISO/IEC 18004): byte mode, error correction level M, versions 1–20.
 * Used for horse stable-door stickers, Studio document verification codes and 2FA setup.
 * Output is SVG so it prints sharply at any size.
 */
final class QrCode
{
    /** version => [ec codewords per block, group1 blocks, group1 data cw, group2 blocks, group2 data cw] (level M) */
    private const BLOCKS_M = [
        1 => [10, 1, 16, 0, 0], 2 => [16, 1, 28, 0, 0], 3 => [26, 1, 44, 0, 0], 4 => [18, 2, 32, 0, 0],
        5 => [24, 2, 43, 0, 0], 6 => [16, 4, 27, 0, 0], 7 => [18, 4, 31, 0, 0], 8 => [22, 2, 38, 2, 39],
        9 => [22, 3, 36, 2, 37], 10 => [26, 4, 43, 1, 44], 11 => [30, 1, 50, 4, 51], 12 => [22, 6, 36, 2, 37],
        13 => [22, 8, 37, 1, 38], 14 => [24, 4, 40, 5, 41], 15 => [24, 5, 41, 5, 42], 16 => [28, 7, 45, 3, 46],
        17 => [28, 10, 46, 1, 47], 18 => [26, 9, 43, 4, 44], 19 => [26, 3, 44, 11, 45], 20 => [26, 3, 41, 13, 42],
    ];
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
        9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74], 17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86],
        20 => [6, 34, 62, 90],
    ];

    private int $size;
    private array $m = [];     // modules [row][col] bool
    private array $fn = [];    // function-module flags

    /** @return bool[][] matrix (true = dark) */
    public static function matrix(string $text, ?int $forceMask = null): array
    {
        $q = new self();
        return $q->encode($text, $forceMask);
    }

    public static function svg(string $text, int $moduleSize = 4, string $color = '#0f1f45'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $quiet = 4;
        $dim = ($n + 2 * $quiet) * $moduleSize;
        $path = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) {
                    $path .= 'M' . (($c + $quiet) * $moduleSize) . ' ' . (($r + $quiet) * $moduleSize) . "h{$moduleSize}v{$moduleSize}h-{$moduleSize}z";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $dim . '" height="' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="100%" height="100%" fill="#fff"/><path fill="' . htmlspecialchars($color) . '" d="' . $path . '"/></svg>';
    }

    public static function dataUri(string $text, int $moduleSize = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $moduleSize));
    }

    private function encode(string $text, ?int $forceMask): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len = count($bytes);
        $version = 0;
        foreach (self::BLOCKS_M as $v => $b) {
            $capBits = ($b[1] * $b[2] + $b[3] * $b[4]) * 8;
            $ccBits = $v <= 9 ? 8 : 16;
            if (4 + $ccBits + 8 * $len <= $capBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new \InvalidArgumentException('QR data too long');
        }
        [$ecLen, $g1b, $g1d, $g2b, $g2d] = self::BLOCKS_M[$version];
        $dataCw = $g1b * $g1d + $g2b * $g2d;

        // Bit stream
        $bits = [];
        $push = function (int $val, int $n) use (&$bits) {
            for ($i = $n - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push($len, $version <= 9 ? 8 : 16);
        foreach ($bytes as $b) {
            $push($b, 8);
        }
        $cap = $dataCw * 8;
        $push(0, min(4, $cap - count($bits)));
        while (count($bits) % 8) {
            $bits[] = 0;
        }
        $data = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $data[] = bindec(implode('', $chunk));
        }
        for ($pad = 0xEC; count($data) < $dataCw; $pad ^= 0xEC ^ 0x11) {
            $data[] = $pad;
        }

        // Split into blocks, add Reed-Solomon error correction, interleave
        $blocks = [];
        $pos = 0;
        for ($i = 0; $i < $g1b + $g2b; $i++) {
            $n = $i < $g1b ? $g1d : $g2d;
            $blocks[] = array_slice($data, $pos, $n);
            $pos += $n;
        }
        $gen = self::rsGenerator($ecLen);
        $ecBlocks = array_map(fn ($b) => self::rsRemainder($b, $gen), $blocks);
        $final = [];
        $maxD = max($g1d, $g2d);
        for ($i = 0; $i < $maxD; $i++) {
            foreach ($blocks as $b) {
                if (isset($b[$i])) {
                    $final[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $b) {
                $final[] = $b[$i];
            }
        }

        $this->size = $version * 4 + 17;
        $this->m = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->fn = $this->m;
        $this->drawFunctionPatterns($version);
        $this->drawCodewords($final);

        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($forceMask !== null ? [$forceMask] : range(0, 7) as $mask) {
            $this->applyMask($mask);
            $this->drawFormat($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $mask;
            }
            $this->applyMask($mask); // undo (XOR)
        }
        $this->applyMask($best);
        $this->drawFormat($best);
        return $this->m;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->m[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $version): void
    {
        $s = $this->size;
        for ($i = 0; $i < $s; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$s - 4, 3], [3, $s - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $s && $y >= 0 && $y < $s) {
                        $d = max(abs($dx), abs($dy));
                        $this->set($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $al = self::ALIGN[$version];
        $n = count($al);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($al[$i] + $dx, $al[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormat(0); // reserve format areas
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $s - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function drawFormat(int $mask): void
    {
        $data = (0 << 3) | $mask; // level M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;
        $s = $this->size;
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($s - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $s - 15 + $i, $bit($i));
        }
        $this->set(8, $s - 8, true);
    }

    private function drawCodewords(array $data): void
    {
        $s = $this->size;
        $total = count($data) * 8;
        $i = 0;
        for ($right = $s - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $s; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $s - 1 - $vert : $vert;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->m[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $s = $this->size;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                if ($this->fn[$y][$x]) {
                    continue;
                }
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($inv) {
                    $this->m[$y][$x] = !$this->m[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $s = $this->size;
        $m = $this->m;
        $score = 0;
        $dark = 0;
        $lines = [];
        for ($i = 0; $i < $s; $i++) {
            $row = $m[$i];
            $col = array_column($m, $i);
            $lines[] = $row;
            $lines[] = $col;
            $dark += count(array_filter($row));
        }
        foreach ($lines as $line) {
            $run = 1;
            for ($k = 1; $k <= $s; $k++) {
                if ($k < $s && $line[$k] === $line[$k - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + $run - 5;
                    }
                    $run = 1;
                }
            }
            $str = implode('', array_map('intval', $line));
            $score += 40 * (substr_count('0000' . $str . '0000', '00001011101') + substr_count('0000' . $str . '0000', '10111010000'));
        }
        for ($y = 0; $y < $s - 1; $y++) {
            for ($x = 0; $x < $s - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $total = $s * $s;
        $score += 10 * intdiv(abs($dark * 20 - $total * 10), $total);
        return $score;
    }

    // ------------------------------------------------------------ Reed-Solomon over GF(256), poly 0x11D

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private static function rsGenerator(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $gen): array
    {
        $result = array_fill(0, count($gen), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($gen as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }
        return $result;
    }
}
