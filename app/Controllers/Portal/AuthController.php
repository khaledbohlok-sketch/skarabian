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
        // Basic per-IP throttle against password spraying
        $recent = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE', [Request::ip()]);
        if ($recent >= 30) {
            $this->flash('error', __('auth.too_many'));
            $this->redirect('/portal/login');
        }
        [$status, $user] = AuthService::attempt($login, $password, Request::post('captcha'));
        switch ($status) {
            case 'ok':
                Session::forget('last_login');
                $to = Session::get('intended', '/portal');
                Session::forget('intended');
                $this->redirect(is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/portal');
            case 'twofa':
                $this->redirect('/portal/login/2fa');
            case 'locked':
                $this->flash('error', __('auth.locked', ['minutes' => config('security.lockout_minutes', 15)]));
                break;
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
        $user = AuthService::verifySecondFactor((string) Request::post('code', ''));
        if (!$user) {
            $this->flash('error', __('auth.code_invalid'));
            $this->redirect(Session::get('pending_2fa_uid') ? '/portal/login/2fa' : '/portal/login');
        }
        $to = Session::get('intended', '/portal');
        Session::forget('intended');
        $this->redirect(is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/portal');
    }

    public function resend(): void
    {
        $uid = Session::get('pending_2fa_uid');
        $user = $uid ? DB::row("SELECT * FROM users WHERE id = ? AND twofa_method = 'email'", [$uid]) : null;
        if ($user) {
            AuthService::sendEmailCode($user);
            $this->flash('success', __('auth.code_sent'));
        }
        $this->redirect('/portal/login/2fa');
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
