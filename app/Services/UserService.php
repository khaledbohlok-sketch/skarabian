<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;

final class UserService
{
    /** Approval handler for a new account. */
    public static function approveUser(int $userId, array $payload, array $approval): void
    {
        DB::update('users', ['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')], "id = :id AND status = 'pending'", ['id' => $userId]);
    }

    public static function rejectUser(int $userId, array $payload, array $approval): void
    {
        DB::update('users', ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')], "id = :id AND status = 'pending'", ['id' => $userId]);
    }

    /** How long an emailed password-reset link works. */
    public const RESET_HOURS = 24;

    /**
     * Password reset by an administrator. The old password stops working at once and the person is signed out
     * everywhere. They get a one-time link by email (valid RESET_HOURS, single use) to choose a new password; the
     * administrator never sees a password. Only when no email can be sent does the Owner (and only the Owner) get a
     * one-time password to pass on, which must be changed at next login. 2FA is left as it is. The Owner is told of every reset.
     * Returns ['mode' => 'link', 'email' => masked address] | ['mode' => 'password', 'password' => ...] | ['mode' => 'failed'].
     */
    public static function resetPassword(int $userId): array
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u) {
            return ['mode' => 'failed'];
        }
        $by = (string) (Auth::user()['name'] ?? '');
        $result = ['mode' => 'failed'];
        // Before database update 021 the token table doesn't exist yet: fall back to the Owner-only one-time password
        $tokensReady = (bool) DB::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'password_reset_tokens'");
        if ($tokensReady && filter_var((string) $u['email'], FILTER_VALIDATE_EMAIL)) {
            $token = Crypto::token();
            DB::run('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
            $tid = DB::insert('password_reset_tokens', [
                'user_id' => $userId, 'token_hash' => hash('sha256', $token), 'created_by' => Auth::id(),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600 * self::RESET_HOURS),
            ]);
            $lang = in_array($u['lang'] ?? '', ['en', 'ar'], true) ? $u['lang'] : 'en';
            $link = absolute_url('/portal/password-reset/' . $token);
            $sent = Mailer::send($u['email'], Lang::get('auth.reset_mail_subject', [], $lang),
                '<p>' . e(Lang::get('auth.reset_mail_body', ['name' => $u['name'], 'hours' => self::RESET_HOURS], $lang)) . '</p>'
                . EmailLook::button(Lang::get('auth.reset_mail_button', [], $lang), $link)
                . '<p style="font-size:13px;color:#5c6782">' . e(Lang::get('auth.reset_mail_ignore', [], $lang)) . '</p>', [], ['lang' => $lang]);
            if ($sent) {
                $result = ['mode' => 'link', 'email' => self::maskEmail((string) $u['email'])];
            } else {
                DB::run('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?', [$tid]);
            }
        }
        if ($result['mode'] === 'failed' && Auth::isOwner()) {
            $result = ['mode' => 'password', 'password' => Passwords::generate()];
        }
        if ($result['mode'] === 'failed') {
            Audit::log('reset_password_failed', 'users', 'user', $userId, null, null, 'Password reset not sent (no working email)');
            return $result;
        }
        // The old password is unusable from now on (a random one nobody knows, or the Owner's one-time password)
        $pw = $result['password'] ?? Crypto::token();
        DB::update('users', ['password_hash' => Passwords::hash($pw), 'must_change_password' => 1, 'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        AuthService::logoutAll($userId);
        Audit::log('reset_password', 'users', 'user', $userId, null, ['method' => $result['mode']], 'Password reset by ' . $by . ($result['mode'] === 'link' ? ' (link emailed)' : ' (one-time password)'));
        Notifier::owners('security', Notifier::msg('notify.pw_reset_title'), Notifier::msg('notify.pw_reset_body', ['user' => $u['name'], 'by' => $by, 'ip' => Request::ip()]), '/portal/users/' . $userId, true);
        return $result;
    }

    /**
     * The Owner sets a password for someone on the spot (no email needed), to give them in person. It follows the
     * password rules (checked by the caller), works at once and must be changed by the person at their next login, so
     * the Owner never knows the password they end up with. The old password, any emailed reset link and every session
     * stop working; 2FA is left as it is. The Owners are told; the Activity log never holds the password.
     */
    public static function setPassword(int $userId, string $plain): void
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u) {
            return;
        }
        $by = (string) (Auth::user()['name'] ?? '');
        DB::transaction(function () use ($userId, $plain) {
            DB::update('users', ['password_hash' => Passwords::hash($plain), 'must_change_password' => 1, 'failed_attempts' => 0, 'locked_until' => null,
                'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
            if (DB::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'password_reset_tokens'")) {
                DB::run('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]); // a link sent earlier stops working
            }
        });
        AuthService::logoutAll($userId);
        Audit::log('reset_password', 'users', 'user', $userId, null, ['method' => 'set'], 'Password set by ' . $by . ' (to be changed at next login)');
        Notifier::owners('security', Notifier::msg('notify.pw_reset_title'), Notifier::msg('notify.pw_reset_body', ['user' => $u['name'], 'by' => $by, 'ip' => Request::ip()]), '/portal/users/' . $userId, true);
    }

    /** "k•••d@g•••.com" */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $dot = strrpos($domain, '.');
        $host = $dot === false ? $domain : substr($domain, 0, $dot);
        $tld = $dot === false ? '' : substr($domain, $dot);
        $m = fn (string $s) => $s === '' ? '' : mb_substr($s, 0, 1) . '•••' . (mb_strlen($s) > 3 ? mb_substr($s, -1) : '');
        return $m($local) . '@' . $m($host) . $tld;
    }

    /** The account behind an unused, unexpired reset link (with token_id), or null. */
    public static function findResetToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/', $token)) {
            return null;
        }
        try {
            return DB::row("SELECT u.*, t.id AS token_id FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
                WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.deleted_at IS NULL AND u.status = 'active'", [hash('sha256', $token)]);
        } catch (\PDOException) {
            return null; // database update 021 not applied yet
        }
    }

    /** Uses the link (once) and sets the chosen password. False when the link was used meanwhile. */
    public static function completeReset(array $row, string $password): bool
    {
        $ok = DB::transaction(function () use ($row, $password) {
            if (DB::run('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$row['token_id']])->rowCount() !== 1) {
                return false;
            }
            DB::run('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['id']]);
            DB::update('users', ['password_hash' => Passwords::hash($password), 'must_change_password' => 0, 'password_changed_at' => date('Y-m-d H:i:s'),
                'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
            return true;
        });
        if ($ok) {
            AuthService::logoutAll((int) $row['id']);
            Audit::log('password_changed', 'users', 'user', $row['id'], null, null, 'Password set with an emailed reset link', $row);
        }
        return (bool) $ok;
    }

    /** Removes the authenticator; if the role requires 2FA the person sets it up again at next login. */
    public static function reset2fa(int $userId): void
    {
        DB::update('users', ['twofa_method' => 'none', 'totp_secret' => null, 'totp_last_step' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        AuthService::logoutAll($userId);
        Audit::log('reset_2fa', 'users', 'user', $userId, null, null, 'Two-factor authentication reset');
    }
}
