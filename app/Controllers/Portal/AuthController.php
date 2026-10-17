<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\Captcha;
use App\Services\Passwords;
use App\Services\UserService;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('/portal');
        }
        $login = (string) Session::get('last_login', '');
        $this->view('portal/auth/login', [
            'title' => __('auth.login'),
            'captcha' => AuthService::captchaRequired($login),
            'login' => $login,
        ], 'auth');
    }

    public function login(): void
    {
        $login = (string) Request::post('login', '');
        $password = (string) ($_POST['password'] ?? '');
        Session::set('last_login', mb_substr($login, 0, 150));
        // Per-IP throttle against password spraying, before any per-account lock
        if (AuthService::ipThrottled()) {
            $this->flash('error', __('auth.too_many'));
            $this->redirect('/portal/login');
        }
        [$status, $user] = AuthService::attempt($login, $password, Request::post('captcha'));
        switch ($status) {
            case 'ok':
                Session::forget('last_login');
                $to = Session::get('intended', '/portal');
                Session::forget('intended');
                $this->redirect(local_path($to, '/portal'));
            case 'twofa':
                $this->redirect('/portal/login/2fa');
            case 'inactive':
                $this->flash('error', __('auth.inactive'));
                break;
            case 'captcha':
                $this->flash('error', __('auth.captcha_wrong'));
                break;
            case 'restricted_country':
                $this->flash('error', __('auth.restricted_country'));
                break;
            case 'restricted_hours':
                $this->flash('error', __('auth.restricted_hours'));
                break;
            default:
                $this->flash('error', __('auth.invalid'));
        }
        $this->redirect('/portal/login');
    }

    public function twofaForm(): void
    {
        $uid = Session::get('pending_2fa_uid');
        if (!$uid) {
            $this->redirect('/portal/login');
        }
        $method = DB::value('SELECT twofa_method FROM users WHERE id = ?', [$uid]);
        $this->view('portal/auth/twofa', ['title' => __('auth.twofa'), 'method' => $method], 'auth');
    }

    public function twofa(): void
    {
        if (AuthService::ipThrottled()) {
            $this->flash('error', __('auth.too_many'));
            $this->redirect(Session::get('pending_2fa_uid') ? '/portal/login/2fa' : '/portal/login');
        }
        if (Session::get('pending_2fa_uid') && AuthService::pendingTwofaExpired()) {
            // too late for this sign-in: start again with the password (not "wrong code" over and over)
            Session::forget('pending_2fa_uid');
            $this->flash('error', __('auth.twofa_expired'));
            $this->redirect('/portal/login');
        }
        $user = AuthService::verifySecondFactor((string) Request::post('code', ''));
        if (!$user) {
            $this->flash('error', __('auth.code_invalid'));
            $this->redirect(Session::get('pending_2fa_uid') ? '/portal/login/2fa' : '/portal/login');
        }
        $to = Session::get('intended', '/portal');
        Session::forget('intended');
        $this->redirect(local_path($to, '/portal'));
    }

    public function resend(): void
    {
        $uid = Session::get('pending_2fa_uid');
        $user = $uid ? DB::row("SELECT * FROM users WHERE id = ? AND twofa_method = 'email'", [$uid]) : null;
        if ($user) {
            if (!AuthService::resendLoginCode($user)) {
                $this->flash('error', __('auth.resend_limit'));
                $this->redirect('/portal/login');
            }
            $this->flash('success', __('auth.code_sent'));
        }
        $this->redirect('/portal/login/2fa');
    }

    /** One-time password-reset link emailed by an administrator (UserService::resetPassword). The person chooses a
     * new password here; signing in afterwards still needs their two-factor code. */
    public function passwordReset(string $token): void
    {
        $row = UserService::findResetToken($token);
        if (!$row) {
            $this->flash('error', __('auth.reset_link_invalid'));
            $this->redirect('/portal/login');
        }
        if (Request::isPost()) {
            $new = (string) ($_POST['password'] ?? '');
            $error = $new !== (string) ($_POST['password_confirmation'] ?? '') ? 'auth.pw_mismatch'
                : Passwords::validate($new, [$row['username'], $row['name'], explode('@', (string) $row['email'])[0]]);
            if ($error) {
                $this->flash('error', __($error));
                $this->redirect('/portal/password-reset/' . $token);
            }
            if (!UserService::completeReset($row, $new)) {
                $this->flash('error', __('auth.reset_link_invalid'));
                $this->redirect('/portal/login');
            }
            if (Auth::check()) {
                AuthService::logout(); // someone else was signed in on this browser
                Session::start();
            }
            Session::set('last_login', $row['username']);
            $this->flash('success', __('auth.reset_done'));
            $this->redirect('/portal/login');
        }
        $this->view('portal/auth/reset', ['title' => __('auth.reset_title'), 'token' => $token, 'name' => $row['name']], 'auth');
    }

    public function captcha(): void
    {
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        echo Captcha::png();
        exit;
    }

    public function logout(): void
    {
        AuthService::logout();
        Session::start();
        Session::flash('success', __('auth.logged_out'));
        Response::redirect('/portal/login');
    }
}
