<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

/** Handles one web request: staging guard, headers, session, language, login state, CSRF, routing. */
final class Kernel
{
    public static function handle(): void
    {
        self::stagingGuard();
        // One address for the site: www.skarabian.com → skarabian.com, so the login and device cookies always belong to
        // one host (a browser switching between the two looked like a new device every time)
        $canon = strtolower((string) parse_url((string) Config::get('app.url', ''), PHP_URL_HOST));
        if ($canon !== '' && !str_starts_with($canon, 'www.') && strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) === 'www.' . $canon) {
            $scheme = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_SCHEME) ?: 'https');
            Response::redirect($scheme . '://' . $canon . (string) ($_SERVER['REQUEST_URI'] ?? '/'), 301);
        }
        Response::securityHeaders();

        $path = Request::path();

        // One address per public page (search engines count /en/horses/ and /en/horses as two pages): a trailing slash or
        // a capitalised language (/EN, /Ar/horses) goes on to the plain address with a permanent redirect
        if (in_array(Request::method(), ['GET', 'HEAD'], true) && !str_starts_with($path, '/portal')) {
            $raw = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            $base = Request::basePath();
            if ($base !== '' && str_starts_with($raw, $base)) {
                $raw = substr($raw, strlen($base));
            }
            $clean = preg_replace_callback('#^/(en|ar)(?=/|$)#i', fn ($m) => '/' . strtolower($m[1]), $path);
            if (($raw !== '/' && str_ends_with($raw, '/')) || $clean !== $path) {
                $q = (string) ($_SERVER['QUERY_STRING'] ?? '');
                $enc = implode('/', array_map('rawurlencode', explode('/', $clean))); // the path was decoded: send it encoded again
                Response::redirect($enc . ($q !== '' ? '?' . $q : ''), 301);
            }
        }

        // Static-like endpoints that don't need a session
        if (in_array($path, ['/robots.txt', '/sitemap.xml', '/manifest.webmanifest'], true) || str_starts_with($path, '/media/')) {
            Lang::set('en');
            (require APP_ROOT . '/app/routes.php')->dispatch(Request::method(), $path);
            return;
        }

        Session::start();
        Session::ageOld();

        // Language: public site from the URL (/en, /ar); portal from the user's preference
        if (preg_match('#^/(en|ar)(/|$)#', $path, $m)) {
            Lang::set($m[1]);
            // Remember the visitor's choice so a later visit to the bare domain lands in the same language
            if (($_COOKIE['lang'] ?? null) !== $m[1]) {
                setcookie('lang', $m[1], ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            }
        } else {
            Lang::set($_COOKIE['lang'] ?? Config::get('app.default_lang', 'en'));
        }

        if (str_starts_with($path, '/portal')) {
            header('Cache-Control: no-store, private, no-transform'); // no-transform: the hosting must not add scripts to staff pages
            AuthService::boot();
            if (Auth::check()) {
                Lang::set(Auth::user()['lang'] ?? 'en');
                // Forced password change / 2FA setup before anything else
                $pending = AuthService::pendingSetup(Auth::user());
                $allowed = ['/portal/account/password', '/portal/account/2fa', '/portal/logout', '/portal/account/lang'];
                if ($pending && !in_array($path, $allowed, true)) {
                    Response::redirect($pending === 'password' ? '/portal/account/password' : '/portal/account/2fa');
                }
                // A view-only role changes nothing: every form sent is refused, except the user's own account
                // (password, 2FA, language, sessions), reading notifications and the print / download log.
                if (Request::isPost() && Auth::readOnly() && !self::readOnlyMayPost($path)) {
                    Auth::deny('read_only');
                }
                // The gate phone's login ("Attendance kiosk"): only the scanner page and its own account
                if (\App\Services\AttendanceScan::kioskOnly() && !self::kioskMay($path)) {
                    if (Request::isPost() || Request::wantsJson()) {
                        Auth::deny('attendance_scan.kiosk');
                    }
                    Response::redirect('/portal/attendance/scan');
                }
            }
        }

        if (Request::isPost() && !Csrf::verify()) {
            Audit::log('csrf_rejected', null, null, null, null, ['path' => $path]);
            if (Request::wantsJson()) {
                Response::json(['error' => 'csrf'], 419);
            }
            Session::flash('error', __('common.csrf_expired'));
            // a website form (the inquiry form) sent after the visitor's session ended: back at the form, with what
            // they typed and the message
            if (preg_match('#^/(en|ar)/#', $path)) {
                Session::keepOld(array_filter($_POST, 'is_string'));
                Response::redirect(local_path($_POST['back'] ?? null, '/' . Lang::current()) . '#inquiry');
            }
            Response::back(str_starts_with($path, '/portal') ? '/portal' : '/');
        }

        (require APP_ROOT . '/app/routes.php')->dispatch(Request::method(), $path);
    }

    /** The few forms a view-only user may still send: their own account, notifications, and the print / download log. */
    private static function readOnlyMayPost(string $path): bool
    {
        return in_array($path, ['/portal/logout', '/portal/account/password', '/portal/account/2fa', '/portal/account/lang', '/portal/account/logout-all',
                '/portal/notifications/read', '/portal/studio/log'], true)
            || (bool) preg_match('#^/portal/(account/sessions/\d+/end|studio/\d+/log)$#', $path);
    }

    /** The pages a kiosk login may open: the scanner (its scans, keep-alive, staff photos) and its own account. */
    private static function kioskMay(string $path): bool
    {
        return $path === '/portal/attendance/scan' || str_starts_with($path, '/portal/attendance/scan/')
            || in_array($path, ['/portal/logout', '/portal/account/password', '/portal/account/2fa', '/portal/account/lang'], true);
    }

    /** Password-protects a staging site (e.g. new.skarabian.com) with HTTP basic auth. */
    private static function stagingGuard(): void
    {
        $user = Config::get('app.staging_user');
        $pass = Config::get('app.staging_password');
        if (!$user || !$pass) {
            return;
        }
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals((string) $user, $u) || !hash_equals((string) $pass, $p)) {
            header('WWW-Authenticate: Basic realm="SK Arabians staging"');
            header('X-Robots-Tag: noindex');
            http_response_code(401);
            exit('Staging site — authorised access only.');
        }
        header('X-Robots-Tag: noindex, nofollow');
    }
}
