<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Field-level encryption for sensitive data (QID/passport numbers, bank details, 2FA secrets)
 * using libsodium secretbox (XSalsa20-Poly1305) with the app key from config.
 */
final class Crypto
{
    private const PREFIX = 'enc1:';

    private static function key(): string
    {
        $raw = base64_decode((string) Config::get('app.key'), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('app.key must be a base64 32-byte key (php tools/keygen.php)');
        }
        return $raw;
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '' || !str_starts_with($cipher, self::PREFIX)) {
            return $cipher;
        }
        $raw = base64_decode(substr($cipher, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
        return $plain === false ? null : $plain;
    }

    public static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, self::key());
    }

    /** Masks a sensitive value for display to users without "view sensitive" (e.g. "••••5821"). */
    public static function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return '••••' . mb_substr($value, -min(4, max(0, mb_strlen($value) - 2)));
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
