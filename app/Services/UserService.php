<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

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

    /** New one-time password; the person must change it at next login and is signed out everywhere. */
    public static function resetPassword(int $userId): string
    {
        $pw = Passwords::generate();
        DB::update('users', ['password_hash' => Passwords::hash($pw), 'must_change_password' => 1, 'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        AuthService::logoutAll($userId);
        Audit::log('reset_password', 'users', 'user', $userId, null, null, 'Password reset by ' . (Auth::user()['name'] ?? ''));
        return $pw;
    }

    /** Removes the authenticator; if the role requires 2FA the person sets it up again at next login. */
    public static function reset2fa(int $userId): void
    {
        DB::update('users', ['twofa_method' => 'none', 'totp_secret' => null, 'totp_last_step' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        AuthService::logoutAll($userId);
        Audit::log('reset_2fa', 'users', 'user', $userId, null, null, 'Two-factor authentication reset');
    }
}
