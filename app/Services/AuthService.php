<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Request;
use App\Core\Session;

/**
 * Login, lockout, 2-factor, session tracking and logout.
 * Sessions are tracked in user_sessions so the Owner can see and end them, and so "log out of all devices" works.
 */
final class AuthService
{
    public const DEVICE_COOKIE = 'SKADEV';

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

    /**
     * Checks credentials. Returns one of: ok, twofa, invalid, locked, inactive, captcha, restricted_country, restricted_hours.
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
            self::recordAttempt($login, (int) $user['id'], false, 'locked');
            return ['locked', $user];
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
                Notifier::owners('security', __('notify.account_locked_title'), __('notify.account_locked_body', ['user' => $user['name'], 'ip' => Request::ip()]), '/portal/users/' . $user['id'], true);
                return ['locked', $user];
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
        if (!$uid || time() - (int) Session::get('pending_2fa_at', 0) > 600) {
            return null;
        }
        $user = DB::row("SELECT * FROM users WHERE id = ? AND status = 'active'", [$uid]);
        if (!$user) {
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
            return null;
        }
        Session::forget('pending_2fa_uid');
        Session::forget('pending_2fa_tries');
        self::completeLogin($user);
        return $user;
    }

    public static function completeLogin(array $user): void
    {
        Session::regenerate();
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
        self::recordAttempt($user['username'], (int) $user['id'], true, null);
        Auth::setUser($user);
        Audit::log('login', null, 'user', $user['id'], null, ['ip' => $ip, 'country' => $country, 'device' => Request::device()], 'Logged in', $user);
        self::checkDevice($user, $country);
    }

    /** Alerts the Owner when a user logs in from a new device or a new country. */
    private static function checkDevice(array $user, ?string $country): void
    {
        $devId = $_COOKIE[self::DEVICE_COOKIE] ?? '';
        if (!preg_match('/^[A-Za-z0-9_-]{20,64}$/', $devId)) {
            $devId = Crypto::token(24);
        }
        setcookie(self::DEVICE_COOKIE, $devId, [
            'expires' => time() + 86400 * 400, 'path' => '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        $hash = hash('sha256', $devId . '|' . $user['id']);
        $known = DB::row('SELECT * FROM known_devices WHERE user_id = ? AND device_hash = ?', [$user['id'], $hash]);
        $firstEver = !DB::value('SELECT 1 FROM known_devices WHERE user_id = ? LIMIT 1', [$user['id']]);
        $countries = DB::column('SELECT DISTINCT country FROM known_devices WHERE user_id = ? AND country IS NOT NULL', [$user['id']]);
        if (!$known) {
            DB::insert('known_devices', ['user_id' => $user['id'], 'device_hash' => $hash, 'country' => $country]);
        } else {
            DB::run('UPDATE known_devices SET last_seen = NOW(), country = COALESCE(?, country) WHERE id = ?', [$country, $known['id']]);
        }
        if ($firstEver || Settings::get('security.alert_new_device', '1') !== '1') {
            return;
        }
        $newCountry = $country && $countries && !in_array($country, $countries, true);
        if (!$known || $newCountry) {
            Notifier::owners(
                'security',
                __('notify.new_device_title'),
                __('notify.new_device_body', ['user' => $user['name'], 'device' => Request::device(), 'ip' => Request::ip(), 'country' => $country ?? '?']),
                '/portal/users/' . $user['id'],
                true
            );
        }
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
        return $now >= $m[1] && $now <= $m[2];
    }

    public static function sendEmailCode(array $user, string $purpose = 'login'): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::run('UPDATE email_codes SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$user['id'], $purpose]);
        DB::insert('email_codes', [
            'user_id' => $user['id'], 'purpose' => $purpose, 'code_hash' => hash('sha256', $code),
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);
        Mailer::send($user['email'], __('auth.email_code_subject'), '<p>' . e(__('auth.email_code_body')) . '</p><p style="font-size:28px;letter-spacing:6px"><strong>' . $code . '</strong></p>');
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

    /** True when the user still has to change the password or set up required 2FA before using the portal. */
    public static function pendingSetup(array $user): ?string
    {
        if ((int) $user['must_change_password'] === 1) {
            return 'password';
        }
        $role = Auth::role();
        if (!empty($role['require_2fa']) && $user['twofa_method'] === 'none') {
            return '2fa';
        }
        return null;
    }

    private static function recordAttempt(string $login, ?int $userId, bool $success, ?string $reason): void
    {
        DB::insert('login_attempts', [
            'username' => mb_substr(mb_strtolower($login), 0, 150), 'user_id' => $userId, 'ip' => Request::ip(),
            'success' => $success ? 1 : 0, 'reason' => $reason,
        ]);
    }
}
