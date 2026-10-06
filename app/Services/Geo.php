<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/** Login country detection (for "Qatar only" role restriction and new-country alerts). */
final class Geo
{
    private static array $cache = [];

    public static function country(string $ip): ?string
    {
        if (isset(self::$cache[$ip])) {
            return self::$cache[$ip];
        }
        $mode = Config::get('security.geo_lookup', 'header');
        $country = null;
        if ($mode === 'header' && !empty($_SERVER['HTTP_CF_IPCOUNTRY']) && preg_match('/^[A-Z]{2}$/', $_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
        } elseif ($mode === 'ipapi' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $json = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=countryCode', false, $ctx);
            $data = $json ? json_decode($json, true) : null;
            if (!empty($data['countryCode'])) {
                $country = $data['countryCode'];
            }
        }
        return self::$cache[$ip] = $country;
    }
}
