<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Session;

/**
 * Login, lockout, 2-factor, session tracking and logout.
 * Sessions are tracked in user_sessions so the Owner can see and end them, and so "log out of all devices" works.
 */
final class AuthService
{
    public const DEVICE_COOKIE = 'SKADEV';
    /** Consecutive wrong 2FA codes (login or account settings) that lock the account. */
    public const TWOFA_MAX_FAILURES = 10;
    /** Seconds the code step of a sign-in stays open after the password (a resent email code opens it again). */
    public const TWOFA_WINDOW = 600;
    /** Email codes that can be sent again during one sign-in. */
    public const TWOFA_MAX_RESENDS = 5;
    /** Wrong current passwords in account settings (password or 2FA change) that end the session. */
    public const CURRENT_PW_MAX_FAILURES = 5;

    /** Restores the logged-in user for this request, enforcing inactivity timeout and revocation. */
    public static function boot(): void
    {
        $uid = Session::get('uid');
        $token = Session::get('sid_token');
        if (!$uid || !$token) {
            return;
        }
        $timeout = (int) Config::get('security.session_timeout', 1800);
        $last = (int) Session::get('last_activity', 0);
        $sess = DB::row('SELECT * FROM user_sessions WHERE token_hash = ? AND user_id = ?', [hash('sha256', $token), $uid]);
        $user = DB::row("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$uid]);

        if (!$sess || $sess['revoked_at'] !== null || !$user || ($last && time() - $last > $timeout)) {
            if ($sess && $sess['revoked_at'] === null) {
                DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ?', [$sess['id']]);
                Audit::log('logout', null, 'user', $uid, null, null, 'Session timed out', $user ?: null);
            }
            Session::destroy();
            Session::start();
            Session::flash('info', __('auth.session_expired'));
            return;
        }
        Session::set('last_activity', time());
        if (strtotime($sess['last_seen_at']) < time() - 60) {
            DB::run('UPDATE user_sessions SET last_seen_at = NOW(), ip = ? WHERE id = ?', [Request::ip(), $sess['id']]);
        }
        Auth::setUser($user);
        self::deviceId(); // a browser that lost its device cookie gets it back before the next login
    }

    public static function captchaRequired(string $username): bool
    {
        $after = (int) Config::get('security.captcha_after', 3);
        $fails = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND (username = ? OR ip = ?) AND created_at > NOW() - INTERVAL 15 MINUTE',
            [mb_strtolower($username), Request::ip()]
        );
        return $fails >= $after;
    }

    /** Per-IP limit on failed logins / 2FA codes (password spraying across many accounts), checked before any per-account lock. */
    public static function ipThrottled(): bool
    {
        $recent = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE', [Request::ip()]);
        return $recent >= (int) Config::get('security.ip_max_failures', 30);
    }

    /**
     * Checks credentials. Returns one of: ok, twofa, invalid, inactive, captcha, restricted_country, restricted_hours.
     * A locked account answers "invalid" exactly like a wrong password, so nobody learns that the account exists or is locked.
     */
    public static function attempt(string $login, string $password, ?string $captcha): array
    {
        $login = mb_strtolower(trim($login));
        if (self::captchaRequired($login) && !Captcha::check($captcha)) {
            self::recordAttempt($login, null, false, 'captcha');
            return ['captcha', null];
        }
        $user = DB::row('SELECT * FROM users WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND deleted_at IS NULL', [$login, $login]);

        if (!$user) {
            password_verify($password, '$2y$12$' . str_repeat('a', 53)); // same timing as a real check
            self::recordAttempt($login, null, false, 'unknown_user');
            Audit::log('login_failed', null, 'user', null, null, ['login' => $login], 'Unknown user');
            return ['invalid', null];
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            Passwords::verify($password, $user['password_hash']); // same timing as an unlocked account; the result is not used
            self::recordAttempt($login, (int) $user['id'], false, 'locked');
            return ['invalid', null];
        }
        if (!Passwords::verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_attempts'] + 1;
            $max = (int) Config::get('security.lockout_attempts', 5);
            $lock = $fails >= $max ? date('Y-m-d H:i:s', time() + 60 * (int) Config::get('security.lockout_minutes', 15)) : null;
            DB::run('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$lock ? 0 : $fails, $lock, $user['id']]);
            self::recordAttempt($login, (int) $user['id'], false, 'bad_password');
            Audit::log('login_failed', null, 'user', $user['id'], null, ['attempt' => $fails], 'Wrong password', $user);
            if ($lock) {
                Audit::log('account_locked', 'users', 'user', $user['id'], null, ['until' => $lock], 'Account locked after failed attempts', $user);
                Notifier::owners('security', Notifier::msg('notify.account_locked_title'), Notifier::msg('notify.account_locked_body', ['user' => $user['name'], 'ip' => Request::ip()]), '/portal/users/' . $user['id'], true);
            }
            return ['invalid', null];
        }
        if ($user['status'] !== 'active') {
            self::recordAttempt($login, (int) $user['id'], false, 'inactive');
            Audit::log('login_failed', null, 'user', $user['id'], null, null, 'Inactive account', $user);
            return ['inactive', $user];
        }
        $role = DB::row('SELECT * FROM roles WHERE id = ?', [$user['role_id']]);
        if ($role && $role['restrict_country']) {
            $country = Geo::country(Request::ip());
            if ($country !== null && strtoupper($country) !== strtoupper($role['restrict_country'])) {
                self::recordAttempt($login, (int) $user['id'], false, 'country');
                Audit::log('login_blocked', null, 'user', $user['id'], null, ['country' => $country], 'Login outside allowed country', $user);
                return ['restricted_country', $user];
            }
        }
        if ($role && $role['business_hours_only'] && !self::withinBusinessHours()) {
            self::recordAttempt($login, (int) $user['id'], false, 'hours');
            Audit::log('login_blocked', null, 'user', $user['id'], null, null, 'Login outside business hours', $user);
            return ['restricted_hours', $user];
        }
        if ($user['twofa_method'] !== 'none') {
            Session::regenerate();
            Session::set('pending_2fa_uid', (int) $user['id']);
            Session::set('pending_2fa_at', time());
            Session::forget('pending_2fa_tries'); // a new sign-in: the wrong codes and resends of an earlier one don't count
            Session::forget('pending_2fa_resends');
            if ($user['twofa_method'] === 'email') {
                self::sendEmailCode($user);
            }
            return ['twofa', $user];
        }
        self::completeLogin($user);
        return ['ok', $user];
    }

    public static function verifySecondFactor(string $code): ?array
    {
        $uid = (int) Session::get('pending_2fa_uid');
        if (!$uid || self::pendingTwofaExpired()) {
            return null;
        }
        $user = DB::row("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$uid]);
        if (!$user) {
            return null;
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            Session::forget('pending_2fa_uid'); // locked meanwhile (e.g. too many wrong codes): start again after the lock
            return null;
        }
        $ok = false;
        if ($user['twofa_method'] === 'totp') {
            // A code is accepted once: a code seen by someone else (shoulder, screenshot) cannot be replayed
            $step = Totp::matchStep((string) Crypto::decrypt($user['totp_secret']), $code);
            $ok = $step !== null && $step > (int) $user['totp_last_step'];
            if ($ok) {
                DB::run('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $uid]);
            }
        } elseif ($user['twofa_method'] === 'email') {
            $ok = self::checkEmailCode($uid, 'login', $code);
        }
        if (!$ok) {
            $tries = (int) Session::get('pending_2fa_tries', 0) + 1;
            Session::set('pending_2fa_tries', $tries);
            self::recordAttempt($user['username'], $uid, false, '2fa');
            Audit::log('login_failed', null, 'user', $uid, null, null, 'Wrong 2FA code', $user);
            if ($tries >= 5) {
                Session::forget('pending_2fa_uid');
            }
            self::twofaFailed($user);
            return null;
        }
        Session::forget('pending_2fa_uid');
        Session::forget('pending_2fa_tries');
        self::completeLogin($user);
        return $user;
    }

    /** The code step of the sign-in in progress has run out of time. */
    public static function pendingTwofaExpired(): bool
    {
        return time() - (int) Session::get('pending_2fa_at', 0) > self::TWOFA_WINDOW;
    }

    /**
     * "Send a new code" during sign-in: the new code gets the full time again (the wrong codes already entered still
     * count). Returns false after TWOFA_MAX_RESENDS: the sign-in is dropped and starts again with the password.
     */
    public static function resendLoginCode(array $user): bool
    {
        $n = (int) Session::get('pending_2fa_resends', 0) + 1;
        if ($n > self::TWOFA_MAX_RESENDS) {
            Session::forget('pending_2fa_uid');
            return false;
        }
        Session::set('pending_2fa_resends', $n);
        Session::set('pending_2fa_at', time());
        self::sendEmailCode($user);
        return true;
    }

    /**
     * A wrong current password in account settings (changing the password or 2FA). Counted like a failed login, and
     * after CURRENT_PW_MAX_FAILURES in this session the session ends, so a session left open (or stolen) can't be used
     * to guess the password. The account is not locked. Returns true when the user was signed out.
     */
    public static function currentPasswordFailed(array $user): bool
    {
        $n = (int) Session::get('current_pw_fails', 0) + 1;
        Session::set('current_pw_fails', $n);
        self::recordAttempt($user['username'], (int) $user['id'], false, 'current_password');
        Audit::log('login_failed', 'users', 'user', $user['id'], null, ['attempt' => $n], 'Wrong current password in account settings', $user);
        if ($n < self::CURRENT_PW_MAX_FAILURES) {
            return false;
        }
        self::logout();
        Session::start();
        return true;
    }

    public static function completeLogin(array $user): void
    {
        Session::regenerate();
        Session::forget('_csrf'); // a form token seen before login is not valid after it
        $token = Crypto::token();
        $ip = Request::ip();
        $country = Geo::country($ip);
        DB::insert('user_sessions', [
            'user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip' => $ip, 'country' => $country,
            'user_agent' => Request::userAgent(), 'device' => Request::device(),
        ]);
        Session::set('uid', (int) $user['id']);
        Session::set('sid_token', $token);
        Session::set('last_activity', time());
        DB::run('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $user['id']]);
        self::resetTwofaFailures((int) $user['id']);
        self::recordAttempt($user['username'], (int) $user['id'], true, null);
        Auth::setUser($user);
        Audit::log('login', null, 'user', $user['id'], null, ['ip' => $ip, 'country' => $country, 'device' => Request::device()], 'Logged in', $user);
        self::checkDevice($user, $country);
    }

    /**
     * Alerts the Owners when a user logs in from a new device or a new country — at most once a day for the same browser.
     * A device is known by its cookie; when a browser lost it (private mode, cleared data), the same browser on the same
     * network within 30 days still counts as known, so it does not raise the alert again.
     */
    private static function checkDevice(array $user, ?string $country): void
    {
        $hash = hash('sha256', self::deviceId() . '|' . $user['id']);
        $browser = mb_substr(Request::device(), 0, 60);
        $net = self::network(Request::ip());
        $rich = DB::hasColumn('known_devices', 'ip_net'); // after database update 020
        $known = DB::row('SELECT * FROM known_devices WHERE user_id = ? AND device_hash = ?', [$user['id'], $hash]);
        if (!$known && $rich) {
            $same = DB::row('SELECT * FROM known_devices WHERE user_id = ? AND browser = ? AND ip_net = ? AND last_seen > NOW() - INTERVAL 30 DAY ORDER BY last_seen DESC LIMIT 1',
                [$user['id'], $browser, $net]);
            if ($same) {
                DB::update('known_devices', ['device_hash' => $hash], 'id = :id', ['id' => $same['id']]); // remembered under its new cookie
                $known = $same;
            }
        }
        $firstEver = !DB::value('SELECT 1 FROM known_devices WHERE user_id = ? LIMIT 1', [$user['id']]);
        $countries = DB::column('SELECT DISTINCT country FROM known_devices WHERE user_id = ? AND country IS NOT NULL', [$user['id']]);
        if (!$known) {
            DB::insert('known_devices', ['user_id' => $user['id'], 'device_hash' => $hash, 'country' => $country] + ($rich ? ['browser' => $browser, 'ip_net' => $net] : []));
        } elseif ($rich) {
            DB::run('UPDATE known_devices SET last_seen = NOW(), country = COALESCE(?, country), logins = logins + 1, browser = ?, ip_net = ? WHERE id = ?', [$country, $browser, $net, $known['id']]);
        } else {
            DB::run('UPDATE known_devices SET last_seen = NOW(), country = COALESCE(?, country) WHERE id = ?', [$country, $known['id']]);
        }
        if ($firstEver || Settings::get('security.alert_new_device', '1') !== '1') {
            return;
        }
        $newCountry = $country && $countries && !in_array($country, $countries, true);
        if (!$known || $newCountry) {
            // grouped: one alert (and one email) per user and browser a day, even when a phone hops between networks
            $key = 'newdev-' . $user['id'] . '-' . substr(hash('sha256', $browser . '|' . ($newCountry ? $country : '')), 0, 16) . '-' . date('Ymd');
            Notifier::owners(
                'security',
                Notifier::msg('notify.new_device_title'), // language keys: each owner reads it in their own language
                Notifier::msg('notify.new_device_body', ['user' => $user['name'], 'device' => $browser, 'ip' => Request::ip(), 'country' => Geo::names($country)]),
                '/portal/users/' . $user['id'],
                true,
                $key
            );
        }
    }

    /**
     * Counts a wrong 2FA code on the account (login, or confirming a 2FA change in account settings). After
     * TWOFA_MAX_FAILURES in a row the account is locked like after wrong passwords, its sessions end and the Owner is told.
     * The count only goes back to 0 with a correct code (or the Owner's unlock), so after the lock runs out every
     * further wrong code locks again. Returns true when the account is now locked.
     */
    public static function twofaFailed(array $user): bool
    {
        try {
            DB::run('UPDATE users SET twofa_failed = twofa_failed + 1 WHERE id = ?', [$user['id']]);
            $n = (int) DB::value('SELECT twofa_failed FROM users WHERE id = ?', [$user['id']]);
        } catch (\PDOException $e) {
            \App\Core\ErrorHandler::log($e); // database update 021 not applied yet
            return false;
        }
        if ($n < self::TWOFA_MAX_FAILURES) {
            return false;
        }
        $lock = date('Y-m-d H:i:s', time() + 60 * (int) Config::get('security.lockout_minutes', 15));
        DB::run('UPDATE users SET locked_until = ? WHERE id = ?', [$lock, $user['id']]);
        Session::forget('pending_2fa_uid');
        self::logoutAll((int) $user['id']);
        Audit::log('account_locked', 'users', 'user', $user['id'], null, ['until' => $lock, 'twofa_failed' => $n], 'Account locked after wrong 2FA codes', $user);
        if ($n % self::TWOFA_MAX_FAILURES === 0) {
            Notifier::owners('security', Notifier::msg('notify.account_locked_title'), Notifier::msg('notify.twofa_locked_body', ['user' => $user['name'], 'n' => $n, 'ip' => Request::ip()]), '/portal/users/' . $user['id'], true);
        }
        return true;
    }

    public static function resetTwofaFailures(int $userId): void
    {
        try {
            DB::run('UPDATE users SET twofa_failed = 0 WHERE id = ? AND twofa_failed <> 0', [$userId]);
        } catch (\PDOException) {
            // database update 021 not applied yet: nothing to reset
        }
    }

    /**
     * Checks a code from the user's current second factor (TOTP or an emailed 'reauth' code), e.g. before 2FA is
     * changed or turned off. A wrong code counts toward the 2FA lock.
     */
    public static function checkCurrentFactor(array $user, string $code): bool
    {
        $ok = false;
        if ($user['twofa_method'] === 'totp') {
            $step = Totp::matchStep((string) Crypto::decrypt($user['totp_secret']), $code);
            $ok = $step !== null && $step > (int) $user['totp_last_step'];
            if ($ok) {
                DB::run('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $user['id']]);
            }
        } elseif ($user['twofa_method'] === 'email') {
            $ok = self::checkEmailCode((int) $user['id'], 'reauth', $code);
        }
        if ($ok) {
            self::resetTwofaFailures((int) $user['id']);
        } else {
            self::recordAttempt($user['username'], (int) $user['id'], false, '2fa_reauth');
            Audit::log('2fa_failed', 'users', 'user', $user['id'], null, null, 'Wrong 2FA code in account settings', $user);
            self::twofaFailed($user);
        }
        return $ok;
    }

    /** This browser's device cookie; set when it is missing (kept 400 days). */
    public static function deviceId(): string
    {
        $devId = (string) ($_COOKIE[self::DEVICE_COOKIE] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{20,64}$/', $devId)) {
            $devId = Crypto::token(24);
            $_COOKIE[self::DEVICE_COOKIE] = $devId;
            if (!headers_sent()) {
                setcookie(self::DEVICE_COOKIE, $devId, ['expires' => time() + 86400 * 400, 'path' => '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            }
        }
        return $devId;
    }

    /** The network of an address (IPv4 /24, IPv6 /48): a laptop or phone moving around one office stays on one network. */
    public static function network(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0/24';
        }
        $bin = @inet_pton($ip);
        return $bin !== false && strlen($bin) === 16 ? bin2hex(substr($bin, 0, 6)) . '::/48' : mb_substr($ip, 0, 45);
    }

    public static function logout(): void
    {
        $user = Auth::user();
        $token = Session::get('sid_token');
        if ($token) {
            DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = ?', [hash('sha256', $token)]);
        }
        if ($user) {
            Audit::log('logout', null, 'user', $user['id'], null, null, 'Logged out', $user);
        }
        Session::destroy();
    }

    public static function logoutAll(int $userId, bool $exceptCurrent = false): int
    {
        $params = [$userId];
        $sql = 'UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL';
        if ($exceptCurrent && Session::get('sid_token')) {
            $sql .= ' AND token_hash <> ?';
            $params[] = hash('sha256', Session::get('sid_token'));
        }
        $n = DB::run($sql, $params)->rowCount();
        Audit::log('sessions_revoked', 'users', 'user', $userId, null, ['count' => $n], 'Logged out of all devices');
        return $n;
    }

    public static function withinBusinessHours(): bool
    {
        $range = (string) Settings::get('security.business_hours', '06:00-22:00');
        if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $range, $m)) {
            return true;
        }
        $now = date('H:i');
        // a range past midnight (e.g. 22:00-06:00) is the evening plus the early morning
        return $m[1] <= $m[2] ? ($now >= $m[1] && $now <= $m[2]) : ($now >= $m[1] || $now <= $m[2]);
    }

    public static function sendEmailCode(array $user, string $purpose = 'login'): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::run('UPDATE email_codes SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$user['id'], $purpose]);
        DB::insert('email_codes', [
            'user_id' => $user['id'], 'purpose' => $purpose, 'code_hash' => hash('sha256', $code),
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);
        Mailer::send($user['email'], __('auth.email_code_subject'), '<p>' . e(__('auth.email_code_body')) . '</p>' . EmailLook::code($code), [], ['lang' => Lang::current()]);
    }

    public static function checkEmailCode(int $userId, string $purpose, string $code): bool
    {
        $row = DB::row('SELECT * FROM email_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [$userId, $purpose]);
        if (!$row || $row['attempts'] >= 5) {
            return false;
        }
        if (!hash_equals($row['code_hash'], hash('sha256', preg_replace('/\D/', '', $code)))) {
            DB::run('UPDATE email_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return false;
        }
        DB::run('UPDATE email_codes SET used_at = NOW() WHERE id = ?', [$row['id']]);
        return true;
    }

    /**
     * What the user still has to do before using the portal: change the password, or set up the two-step login their
     * role requires once the grace period is over (null: nothing).
     */
    public static function pendingSetup(array $user): ?string
    {
        if ((int) $user['must_change_password'] === 1) {
            return 'password';
        }
        $role = Auth::role();
        if (!empty($role['require_2fa']) && $user['twofa_method'] === 'none' && self::twofaGraceUntil($user, $role) === null) {
            return '2fa';
        }
        return null;
    }

    /**
     * Until when a user whose role requires 2FA may still work without it (Security → grace period, default 7 days,
     * counted from when the account was made or the role started requiring it). Null: no grace (left, or not needed).
     */
    public static function twofaGraceUntil(array $user, ?array $role = null): ?string
    {
        $role ??= Auth::role();
        if (empty($role['require_2fa']) || ($user['twofa_method'] ?? 'none') !== 'none') {
            return null;
        }
        $days = max(0, (int) Settings::get('security.twofa_grace_days', '7'));
        $from = max((string) ($user['created_at'] ?? ''), (string) ($role['twofa_since'] ?? ''));
        if (!$days || $from === '') {
            return null;
        }
        $until = date('Y-m-d H:i:s', strtotime($from) + $days * 86400);
        return $until > date('Y-m-d H:i:s') ? $until : null;
    }

    /** Email codes are offered for 2FA only once outgoing email is known to work (a successful test email in Settings). */
    public static function emailCodesAvailable(): bool
    {
        return (string) Settings::get('mail.verified_at', '') !== '';
    }

    private static function recordAttempt(string $login, ?int $userId, bool $success, ?string $reason): void
    {
        DB::insert('login_attempts', [
            'username' => mb_substr(mb_strtolower($login), 0, 150), 'user_id' => $userId, 'ip' => Request::ip(),
            'success' => $success ? 1 : 0, 'reason' => $reason,
        ]);
    }
}
