<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $to, int $code = 302): never
    {
        if (!preg_match('#^https?://#', $to)) {
            $to = url($to);
        }
        header('Location: ' . $to, true, $code);
        exit;
    }

    public static function back(string $fallback = '/portal'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        // Compare hostnames only (parse_url() strips the port from the referrer but $_SERVER['HTTP_HOST']
        // keeps it) — otherwise this "same site" check fails on any non-default-port setup and every
        // action that returns here (uploads, make-main, archive, delete, ...) bounces to the dashboard.
        $refHost = parse_url($ref, PHP_URL_HOST);
        $ownHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($ref !== '' && $refHost !== null && $refHost === $ownHost) {
            header('Location: ' . $ref, true, 302);
            exit;
        }
        self::redirect($fallback);
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $html, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /** $etag: the file's ETag when a part of it may be asked for ("Range": a video player); else the whole file is sent. */
    public static function file(string $path, string $mime, ?string $downloadName = null, bool $private = true, int $maxAge = 0, ?string $etag = null): never
    {
        if (!is_file($path)) {
            self::notFound();
        }
        $size = (int) filesize($path);
        [$first, $last] = [0, $size - 1];
        if ($etag !== null) {
            header('Accept-Ranges: bytes');
            $ifRange = $_SERVER['HTTP_IF_RANGE'] ?? null; // a part of the copy the browser has: only while it is still this file
            if (isset($_SERVER['HTTP_RANGE']) && ($ifRange === null || $ifRange === $etag)) {
                $range = self::byteRange((string) $_SERVER['HTTP_RANGE'], $size);
                if ($range === false) {
                    http_response_code(416);
                    header('Content-Range: bytes */' . $size);
                    exit;
                }
                if ($range !== null) {
                    [$first, $last] = $range;
                    http_response_code(206);
                    header('Content-Range: bytes ' . $first . '-' . $last . '/' . $size);
                }
            }
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . ($last - $first + 1));
        header('X-Content-Type-Options: nosniff');
        if ($downloadName !== null) {
            header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        } else {
            header('Content-Disposition: inline');
        }
        header('Cache-Control: ' . ($private ? 'private, no-store' : 'public, max-age=' . $maxAge));
        if ($first === 0 && $last === $size - 1) {
            readfile($path);
            exit;
        }
        $fh = fopen($path, 'rb');
        fseek($fh, $first);
        $left = $last - $first + 1;
        while ($left > 0 && ($chunk = fread($fh, min(65536, $left))) !== false && $chunk !== '') {
            echo $chunk;
            $left -= strlen($chunk);
        }
        fclose($fh);
        exit;
    }

    /**
     * One part of a file of $size bytes asked for in a Range header ("bytes=0-1023", "bytes=500-", "bytes=-500"):
     * [first, last]; null to send the whole file (no single byte range understood); false when it is outside the file.
     */
    public static function byteRange(string $header, int $size): array|false|null
    {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
            return null;
        }
        if ($m[1] === '') { // the last n bytes
            return (int) $m[2] > 0 && $size > 0 ? [max(0, $size - (int) $m[2]), $size - 1] : false;
        }
        $first = (int) $m[1];
        if ($m[2] !== '' && (int) $m[2] < $first) {
            return null; // not a valid range: ignored
        }
        return $first < $size ? [$first, $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1)] : false;
    }

    /** A picture made by the app (e.g. a file-type icon), for an <img>. */
    public static function image(string $content, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        echo $content;
        exit;
    }

    public static function download(string $content, string $mime, string $name): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        echo $content;
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        if (Request::wantsJson()) {
            self::json(['error' => 'not_found'], 404);
        }
        $layout = str_starts_with(Request::path(), '/portal') ? 'error' : 'site';
        echo View::render('errors/404', [], $layout);
        exit;
    }

    /**
     * 410 Gone: a public page that existed and was removed on purpose (a horse taken off the website, a news item
     * withdrawn). Search engines drop it sooner than a 404. Visitors see the same "page not found" page.
     */
    public static function gone(): never
    {
        http_response_code(410);
        if (Request::wantsJson()) {
            self::json(['error' => 'gone'], 410);
        }
        echo View::render('errors/404', [], 'site');
        exit;
    }

    public static function forbidden(): never
    {
        http_response_code(403);
        if (Request::wantsJson()) {
            self::json(['error' => 'access_denied', 'message' => __('common.access_denied')], 403);
        }
        echo View::render('errors/403', [], Auth::check() ? 'portal' : 'error');
        exit;
    }

    /** Security headers sent with every response. */
    public static function securityHeaders(): void
    {
        header_remove('X-Powered-By'); // don't advertise the PHP version
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
        header('Cross-Origin-Opener-Policy: same-origin');
        // media-src https: the homepage's hero video may be a link to an MP4 file elsewhere (Website → Homepage & texts)
        $csp ="default-src 'self'; img-src 'self' data: blob: https://*.cdninstagram.com https://i.ytimg.com https://*.tile.openstreetmap.org; "
             . "script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
             . "media-src 'self' blob: https:; frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://www.google.com https://www.instagram.com; "
             . "connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'";
        header('Content-Security-Policy: ' . $csp);
        if (Config::get('security.hsts', true) && Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
