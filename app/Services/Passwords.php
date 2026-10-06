<?php
declare(strict_types=1);

namespace App\Services;

/** Password policy: minimum 10 characters, mixed (upper, lower, digit, symbol); bcrypt hashing. */
final class Passwords
{
    public const MIN = 10;

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Returns an error translation key, or null when the password is acceptable. */
    public static function validate(string $plain, array $avoid = []): ?string
    {
        if (mb_strlen($plain) < self::MIN) {
            return 'auth.pw_too_short';
        }
        if (!preg_match('/[a-z]/', $plain) || !preg_match('/[A-Z]/', $plain) || !preg_match('/\d/', $plain) || !preg_match('/[^A-Za-z0-9]/', $plain)) {
            return 'auth.pw_not_mixed';
        }
        foreach ($avoid as $word) {
            if ($word !== '' && mb_strlen((string) $word) >= 3 && stripos($plain, (string) $word) !== false) {
                return 'auth.pw_contains_name';
            }
        }
        return null;
    }

    public static function generate(int $len = 14): string
    {
        $sets = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKMNPQRSTUVWXYZ', '23456789', '!@#$%*?-+'];
        $pw = '';
        foreach ($sets as $s) {
            $pw .= $s[random_int(0, strlen($s) - 1)];
        }
        $all = implode('', $sets);
        while (strlen($pw) < $len) {
            $pw .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pw);
    }
}
