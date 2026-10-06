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
        $host = parse_url($ref, PHP_URL_HOST);
        if ($ref !== '' && $host === ($_SERVER['HTTP_HOST'] ?? null)) {
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

    public static function file(string $path, string $mime, ?string $downloadName = null, bool $private = true, int $maxAge = 0): never
    {
        if (!is_file($path)) {
            self::notFound();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        if ($downloadName !== null) {
            header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        } else {
            header('Content-Disposition: inline');
        }
        header('Cache-Control: ' . ($private ? 'private, no-store' : 'public, max-age=' . $maxAge));
        readfile($path);
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
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
        header('Cross-Origin-Opener-Policy: same-origin');
        $csp = "default-src 'self'; img-src 'self' data: blob: https://*.cdninstagram.com https://i.ytimg.com https://*.tile.openstreetmap.org; "
             . "script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
             . "media-src 'self' blob:; frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://www.google.com https://www.instagram.com; "
             . "connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'";
        header('Content-Security-Policy: ' . $csp);
        if (Config::get('security.hsts', true) && Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
