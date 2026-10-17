<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/** Image CAPTCHA (GD) shown after 3 failed login attempts. No third-party service, no tracking. */
final class Captcha
{
    private const CHARS = 'ABCDEFGHJKLMNPRSTUVWXYZ23456789';

    public static function newCode(): string
    {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        Session::set('captcha', ['code' => $code, 'at' => time()]);
        return $code;
    }

    public static function check(?string $answer): bool
    {
        $c = Session::get('captcha');
        Session::forget('captcha'); // single use
        if (!$c || !$answer || time() - $c['at'] > 600) {
            return false;
        }
        return hash_equals(strtoupper($c['code']), strtoupper(trim($answer)));
    }

    public static function png(): string
    {
        $code = self::newCode();
        $w = 170;
        $h = 56;
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, 244, 246, 250);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);
        for ($i = 0; $i < 7; $i++) {
            $c = imagecolorallocate($im, random_int(150, 210), random_int(150, 210), random_int(170, 220));
            imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        for ($i = 0; $i < 220; $i++) {
            $c = imagecolorallocate($im, random_int(120, 200), random_int(120, 200), random_int(120, 200));
            imagesetpixel($im, random_int(0, $w), random_int(0, $h), $c);
        }
        $x = 14;
        foreach (str_split($code) as $ch) {
            $col = imagecolorallocate($im, random_int(10, 40), random_int(30, 60), random_int(80, 120));
            // Built-in font 5, drawn twice with a 1px offset for weight
            $y = random_int(10, 26);
            $tmp = imagecreatetruecolor(14, 18);
            imagefill($tmp, 0, 0, $bg);
            imagestring($tmp, 5, 2, 1, $ch, $col);
            imagecopyresampled($im, $tmp, $x, $y, 0, 0, 28, 32, 14, 18);
            imagedestroy($tmp);
            $x += 29;
        }
        ob_start();
        imagepng($im);
        imagedestroy($im);
        return (string) ob_get_clean();
    }
}
