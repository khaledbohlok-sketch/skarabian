<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\Passwords;
use App\Services\QrCode;
use App\Services\Totp;

/** The logged-in user's own account: password, 2FA, language, sessions. */
class AccountController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $sessions = DB::all('SELECT * FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC', [$user['id']]);
        $this->view('portal/account/index', [
            'title' => __('account.title'), 'user' => $user, 'sessions' => $sessions,
            'currentHash' => hash('sha256', (string) Session::get('sid_token')),
        ]);
    }

    public function password(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (Request::isPost()) {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');
            $error = null;
            if (!Passwords::verify($current, $user['password_hash'])) {
                $error = 'auth.current_wrong';
            } elseif ($new !== $confirm) {
                $error = 'auth.pw_mismatch';
            } elseif (Passwords::verify($new, $user['password_hash'])) {
                $error = 'auth.pw_same';
            } else {
                $error = Passwords::validate($new, [$user['username'], $user['name'], explode('@', $user['email'])[0]]);
            }
            if ($error) {
                $this->flash('error', __($error));
                $this->redirect('/portal/account/password');
            }
            DB::update('users', ['password_hash' => Passwords::hash($new), 'must_change_password' => 0, 'password_changed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
            Audit::log('password_changed', 'users', 'user', $user['id'], null, null, 'Password changed');
            AuthService::logoutAll((int) $user['id'], true); // other devices must log in again
            $this->flash('success', __('auth.pw_changed'));
            $this->redirect('/portal');
        }
        $this->view('portal/account/password', ['title' => __('auth.change_password'), 'forced' => (int) $user['must_change_password'] === 1]);
    }

    public function twofa(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $required = !empty(Auth::role()['require_2fa']);
        if (Request::isPost()) {
            $action = Request::post('action');
            if ($action === 'disable') {
                if ($required) {
                    $this->flash('error', __('auth.twofa_required'));
                    $this->redirect('/portal/account/2fa');
                }
                if (!Passwords::verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
                    $this->flash('error', __('auth.current_wrong'));
                    $this->redirect('/portal/account/2fa');
                }
                DB::update('users', ['twofa_method' => 'none', 'totp_secret' => null], 'id = :id', ['id' => $user['id']]);
                Audit::log('2fa_disabled', 'users', 'user', $user['id']);
                $this->flash('success', __('auth.twofa_disabled'));
                $this->redirect('/portal/account');
            }
            if ($action === 'totp') {
                $secret = (string) Session::get('totp_setup');
                if ($secret && Totp::verify($secret, (string) Request::post('code'))) {
                    DB::update('users', ['twofa_method' => 'totp', 'totp_secret' => Crypto::encrypt($secret)], 'id = :id', ['id' => $user['id']]);
                    Session::forget('totp_setup');
                    Audit::log('2fa_enabled', 'users', 'user', $user['id'], null, ['method' => 'totp']);
                    $this->flash('success', __('auth.twofa_enabled'));
                    $this->redirect('/portal');
                }
                $this->flash('error', __('auth.code_invalid'));
                $this->redirect('/portal/account/2fa');
            }
            if ($action === 'email_send') {
                AuthService::sendEmailCode($user, 'setup');
                Session::set('email_setup_sent', 1);
                $this->flash('success', __('auth.code_sent'));
                $this->redirect('/portal/account/2fa?m=email');
            }
            if ($action === 'email') {
                if (AuthService::checkEmailCode((int) $user['id'], 'setup', (string) Request::post('code'))) {
                    DB::update('users', ['twofa_method' => 'email', 'totp_secret' => null], 'id = :id', ['id' => $user['id']]);
                    Audit::log('2fa_enabled', 'users', 'user', $user['id'], null, ['method' => 'email']);
                    $this->flash('success', __('auth.twofa_enabled'));
                    $this->redirect('/portal');
                }
                $this->flash('error', __('auth.code_invalid'));
                $this->redirect('/portal/account/2fa?m=email');
            }
        }
        if (!Session::get('totp_setup')) {
            Session::set('totp_setup', Totp::newSecret());
        }
        $secret = (string) Session::get('totp_setup');
        $this->view('portal/account/twofa', [
            'title' => __('auth.twofa_setup'), 'secret' => $secret, 'required' => $required, 'user' => $user,
            'qr' => QrCode::svg(Totp::uri($secret, $user['username']), 4),
        ]);
    }

    public function lang(): void
    {
        $lang = Request::post('lang') === 'ar' ? 'ar' : 'en';
        setcookie('lang', $lang, ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        if (Auth::check()) {
            DB::update('users', ['lang' => $lang], 'id = :id', ['id' => Auth::id()]);
        }
        $this->back('/portal');
    }

    public function logoutAll(): void
    {
        Auth::requireLogin();
        AuthService::logoutAll((int) Auth::id(), true);
        $this->flash('success', __('account.logged_out_others'));
        $this->redirect('/portal/account');
    }

    public function endSession(string $id): void
    {
        Auth::requireLogin();
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ? AND user_id = ?', [(int) $id, Auth::id()]);
        Audit::log('session_ended', 'users', 'user', Auth::id(), null, ['session' => (int) $id]);
        $this->redirect('/portal/account');
    }
}
