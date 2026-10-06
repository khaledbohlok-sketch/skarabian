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
        Response::securityHeaders();

        $path = Request::path();

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
        } else {
            Lang::set($_COOKIE['lang'] ?? Config::get('app.default_lang', 'en'));
        }

        if (str_starts_with($path, '/portal')) {
            header('Cache-Control: no-store, private');
            AuthService::boot();
            if (Auth::check()) {
                Lang::set(Auth::user()['lang'] ?? 'en');
                // Forced password change / 2FA setup before anything else
                $pending = AuthService::pendingSetup(Auth::user());
                $allowed = ['/portal/account/password', '/portal/account/2fa', '/portal/logout', '/portal/account/lang'];
                if ($pending && !in_array($path, $allowed, true)) {
                    Response::redirect($pending === 'password' ? '/portal/account/password' : '/portal/account/2fa');
                }
            }
        }

        if (Request::isPost() && !Csrf::verify()) {
            Audit::log('csrf_rejected', null, null, null, null, ['path' => $path]);
            if (Request::wantsJson()) {
                Response::json(['error' => 'csrf'], 419);
            }
            Session::flash('error', __('common.csrf_expired'));
            Response::back(str_starts_with($path, '/portal') ? '/portal' : '/');
        }

        (require APP_ROOT . '/app/routes.php')->dispatch(Request::method(), $path);
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
