<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Off-server copy of each backup. Two options (set in config.php → backup):
 *  - Google Drive with a service account: put the backups folder in a Shared Drive (Google Workspace) and add the
 *    service account email as a member with "Content manager". Service accounts have no storage of their own, so a
 *    folder in a personal "My Drive" does not work.
 *  - FTP / FTPS to any other server.
 * Copies older than backup.keep_days are deleted from Drive as well.
 */
final class Offsite
{
    /** Uploads the given backup files. Returns a short status, or null when no off-server copy is configured. */
    public static function upload(array $files): ?string
    {
        $out = [];
        if (Config::get('backup.gdrive_folder_id') && Config::get('backup.gdrive_service_account_json')) {
            $out[] = 'Drive ' . self::drive($files);
        }
        $ftp = Config::get('backup.ftp', []);
        if (!empty($ftp['host'])) {
            $out[] = 'FTP ' . self::ftp($files, $ftp);
        }
        return $out ? implode('; ', $out) : null;
    }

    public static function configured(): bool
    {
        return (Config::get('backup.gdrive_folder_id') && Config::get('backup.gdrive_service_account_json')) || !empty(Config::get('backup.ftp', [])['host']);
    }

    // ------------------------------------------------------------------ Google Drive

    private static function drive(array $files): string
    {
        $token = self::driveToken();
        $folder = (string) Config::get('backup.gdrive_folder_id');
        $done = 0;
        foreach ($files as $name) {
            $path = BackupService::path($name);
            if (!$path) {
                continue;
            }
            $boundary = 'b' . bin2hex(random_bytes(8));
            $meta = json_encode(['name' => $name, 'parents' => [$folder]]);
            $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: application/octet-stream\r\n\r\n"
                . file_get_contents($path) . "\r\n--$boundary--";
            [$code, $resp] = self::request('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true', $body, [
                'Authorization: Bearer ' . $token, 'Content-Type: multipart/related; boundary=' . $boundary,
            ], 600);
            if ($code >= 300) {
                throw new \RuntimeException('Drive upload failed (' . $code . '): ' . mb_substr($resp, 0, 200));
            }
            $done++;
        }
        // Remove copies older than the keep period
        $keep = (int) Config::get('backup.keep_days', 30);
        $before = gmdate('Y-m-d\TH:i:s', time() - $keep * 86400);
        $q = rawurlencode("'$folder' in parents and trashed = false and createdTime < '$before' and (name contains 'db-' or name contains 'files-')");
        [, $list] = self::request('GET', "https://www.googleapis.com/drive/v3/files?q=$q&fields=files(id,name)&supportsAllDrives=true&includeItemsFromAllDrives=true&pageSize=200", null, ['Authorization: Bearer ' . $token]);
        $removed = 0;
        foreach (json_decode($list, true)['files'] ?? [] as $f) {
            if (preg_match('/^(db|files)-\d{8}-\d{6}\./', $f['name'])) {
                self::request('DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($f['id']) . '?supportsAllDrives=true', null, ['Authorization: Bearer ' . $token]);
                $removed++;
            }
        }
        return "$done uploaded, $removed old removed";
    }

    /** OAuth access token for the service account (JWT bearer flow, signed with its private key). */
    private static function driveToken(): string
    {
        $file = (string) Config::get('backup.gdrive_service_account_json');
        $sa = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new \RuntimeException('Service account file missing or invalid: ' . basename($file));
        }
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
            'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (!openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign the Google token request');
        }
        [$code, $resp] = self::request('POST', 'https://oauth2.googleapis.com/token',
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $b64($sig)]),
            ['Content-Type: application/x-www-form-urlencoded']);
        $tok = json_decode($resp, true)['access_token'] ?? null;
        if ($code >= 300 || !$tok) {
            throw new \RuntimeException('Google sign-in failed (' . $code . ')');
        }
        return $tok;
    }

    private static function request(string $method, string $url, ?string $body, array $headers, int $timeout = 60): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new \RuntimeException('Connection failed: ' . $err);
        }
        return [$code, (string) $resp];
    }

    // ------------------------------------------------------------------ FTP

    private static function ftp(array $files, array $c): string
    {
        $conn = !empty($c['ssl']) && function_exists('ftp_ssl_connect') ? @ftp_ssl_connect($c['host'], (int) ($c['port'] ?? 21), 30) : @ftp_connect($c['host'], (int) ($c['port'] ?? 21), 30);
        if (!$conn || !@ftp_login($conn, (string) $c['user'], (string) $c['pass'])) {
            throw new \RuntimeException('FTP login failed');
        }
        ftp_pasv($conn, true);
        $dir = rtrim((string) ($c['dir'] ?? '/'), '/');
        $done = 0;
        foreach ($files as $name) {
            $path = BackupService::path($name);
            if ($path && @ftp_put($conn, $dir . '/' . $name, $path, FTP_BINARY)) {
                $done++;
            }
        }
        $keep = (int) Config::get('backup.keep_days', 30);
        $removed = 0;
        foreach (@ftp_nlist($conn, $dir) ?: [] as $f) {
            $base = basename($f);
            if (preg_match('/^(db|files)-(\d{8})-\d{6}\./', $base, $m) && strtotime($m[2]) < time() - $keep * 86400 && @ftp_delete($conn, $dir . '/' . $base)) {
                $removed++;
            }
        }
        ftp_close($conn);
        if ($done < count($files)) {
            throw new \RuntimeException("FTP uploaded $done of " . count($files));
        }
        return "$done uploaded, $removed old removed";
    }
}
