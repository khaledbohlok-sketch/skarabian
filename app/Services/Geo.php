<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;

/**
 * Login country (for the "Qatar only" role rule and the new device / new country alerts). Looked up on the server in
 * database/data/ip-country-v4.bin (IP to Country Lite by DB-IP, CC BY 4.0): no address is sent anywhere. Behind
 * Cloudflare its country header is used, but only for requests that really come through a trusted proxy (a visitor
 * can send that header themselves). config security.geo_lookup: 'off', or 'ipapi' to ask ip-api.com when the file
 * has no answer.
 */
final class Geo
{
    public const FILE = 'database/data/ip-country-v4.bin';

    private static array $cache = [];

    /** Two-letter country of an address, or null (unknown, or a private network). */
    public static function country(string $ip): ?string
    {
        if (array_key_exists($ip, self::$cache)) {
            return self::$cache[$ip];
        }
        $mode = (string) Config::get('security.geo_lookup', 'header');
        $country = null;
        $viaProxy = in_array($_SERVER['REMOTE_ADDR'] ?? '', (array) Config::get('app.trusted_proxies', []), true);
        $cf = (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
        if ($viaProxy && preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX') {
            $country = $cf;
        } elseif ($mode !== 'off') {
            $country = self::fromFile($ip);
            if ($country === null && $mode === 'ipapi' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                $json = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=countryCode', false, $ctx);
                $data = $json ? json_decode($json, true) : null;
                if (!empty($data['countryCode']) && preg_match('/^[A-Z]{2}$/', (string) $data['countryCode'])) {
                    $country = $data['countryCode'];
                }
            }
        }
        return self::$cache[$ip] = $country;
    }

    /** Binary search in the lookup file (sorted 10-byte records: start, end, country). IPv4 only: the site has no IPv6 address. */
    public static function fromFile(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $fh = @fopen(APP_ROOT . '/' . self::FILE, 'rb');
        if (!$fh) {
            return null;
        }
        try {
            $head = (string) fread($fh, 8);
            if (strlen($head) !== 8 || substr($head, 0, 4) !== 'IPC4') {
                return null;
            }
            $count = (int) unpack('N', substr($head, 4))[1];
            $n = (int) sprintf('%u', ip2long($ip));
            $lo = 0;
            $hi = $count - 1;
            while ($lo <= $hi) {
                $mid = intdiv($lo + $hi, 2);
                fseek($fh, 8 + $mid * 10);
                $rec = (string) fread($fh, 10);
                if (strlen($rec) !== 10) {
                    return null;
                }
                ['s' => $s, 'e' => $e] = unpack('Ns/Ne', $rec);
                if ($n < $s) {
                    $hi = $mid - 1;
                } elseif ($n > $e) {
                    $lo = $mid + 1;
                } else {
                    $cc = substr($rec, 8, 2);
                    return preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'ZZ' ? $cc : null;
                }
            }
            return null;
        } finally {
            fclose($fh);
        }
    }

    /** The code of a country typed as text ("Qatar", "قطر", "qa", "Qatari"), or null when it is not one of the list. */
    public static function code(?string $text): ?string
    {
        $t = trim((string) $text);
        if ($t === '' || mb_strlen($t) > 80) {
            return null;
        }
        $id = DB::value('SELECT id FROM countries WHERE id = ? OR name_en = ? OR name_ar = ? OR nationality_en = ? OR nationality_ar = ? ORDER BY id = ? DESC LIMIT 1', [$t, $t, $t, $t, $t, $t]);
        return $id ? strtoupper((string) $id) : null;
    }

    /** The country's name in the user's language ("Qatar" / "قطر"), "Unknown" when there is none. */
    public static function name(?string $code): string
    {
        if (!$code) {
            return __('security.country_unknown');
        }
        $row = DB::row('SELECT name_en, name_ar FROM countries WHERE id = ?', [$code]);
        return ($row ? loc($row) : $code) . ' (' . $code . ')';
    }

    /** name() in both languages, ['en' => ..., 'ar' => ...]: for a message each reader sees in their own (Notifier::msg). */
    public static function names(?string $code): array
    {
        $prev = \App\Core\Lang::current();
        $out = [];
        foreach (['en', 'ar'] as $l) {
            \App\Core\Lang::set($l);
            $out[$l] = self::name($code);
        }
        \App\Core\Lang::set($prev);
        return $out;
    }
}
