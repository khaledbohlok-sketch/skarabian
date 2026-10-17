<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Resources\Registry;
use App\Resources\Users;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\Cache;
use App\Services\DefaultRoles;
use App\Services\Mailer;
use App\Services\Migrator;
use App\Services\Money;
use App\Services\Settings;
use App\Services\TrashService;
use App\Services\UserService;

/** Administration: roles & permissions, user security actions, sessions, settings, currencies, backups, trash, activity log. */
class AdminController extends Controller
{
    private function owner(): void
    {
        Auth::requireLogin();
        if (!Auth::isOwner()) {
            Auth::deny('owner_only');
        }
    }

    // ------------------------------------------------------------------ style guide

    /** The staff system's design tokens and components (Owner only, not in the menu); ?sample=letterhead is the A4 paper shown in it. */
    public function styleGuide(): void
    {
        $this->owner();
        if (Request::query('sample') === 'letterhead') {
            $this->view('portal/admin/styleguide', ['title' => __('styleguide.letterhead'), 'sample' => true, 'docLang' => Request::query('lang') === 'ar' ? 'ar' : 'en'], 'bare');
        }
        $this->view('portal/admin/styleguide', ['title' => __('styleguide.title'), 'sample' => false]);
    }

    // ------------------------------------------------------------------ roles

    public function roles(): void
    {
        $this->owner();
        if (Request::isPost()) {
            $name = trim((string) Request::post('name_en'));
            $copy = (int) Request::post('copy_from');
            if ($name === '' || mb_strlen($name) > 80) {
                $this->flash('error', __('validation.required'));
                $this->redirect('/portal/roles');
            }
            $slug = substr(slugify($name), 0, 30) ?: 'role';
            for ($i = 2; DB::value('SELECT id FROM roles WHERE slug = ?', [$slug]); $i++) {
                $slug = substr(slugify($name), 0, 26) . '-' . $i;
            }
            $id = DB::insert('roles', ['slug' => $slug, 'name_en' => $name, 'name_ar' => trim((string) Request::post('name_ar')) ?: null, 'is_system' => 0]);
            if ($copy) {
                DB::run('INSERT INTO role_permissions (role_id, module, action) SELECT ?, module, action FROM role_permissions WHERE role_id = ?', [$id, $copy]);
            }
            Audit::log('create', 'users', 'role', $id, null, ['name' => $name, 'copy_from' => $copy], 'Role created: ' . $name);
            $this->redirect('/portal/roles/' . $id);
        }
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL AND u.status = \'active\') AS users FROM roles r ORDER BY r.id');
        $this->view('portal/admin/roles', ['title' => __('nav.roles'), 'roles' => $roles]);
    }

    /** Checkbox matrix (module × action) plus the role's security rules. The Owner role always has everything. */
    public function role(string $id): void
    {
        $this->owner();
        $role = DB::row('SELECT * FROM roles WHERE id = ?', [(int) $id]) ?? Response::notFound();
        if (Request::isPost() && $role['slug'] !== 'owner') {
            $old = DB::all('SELECT module, action FROM role_permissions WHERE role_id = ?', [$role['id']]);
            $in = (array) ($_POST['perm'] ?? []);
            $readOnly = DB::hasColumn('roles', 'read_only') && Request::post('read_only');
            DB::transaction(function () use ($role, $in, $readOnly) {
                DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$role['id']]);
                foreach (Auth::MODULES as $m) {
                    foreach (Auth::ACTIONS as $a) {
                        // a view-only role keeps only view / export / print / sensitive
                        if (!empty($in[$m][$a]) && (!$readOnly || in_array($a, Auth::READ_ACTIONS, true))) {
                            DB::insert('role_permissions', ['role_id' => $role['id'], 'module' => $m, 'action' => $a]);
                        }
                    }
                }
                $country = strtoupper(trim((string) Request::post('restrict_country')));
                $twofa = Request::post('require_2fa') ? 1 : 0;
                $set = [
                    'name_en' => trim((string) Request::post('name_en')) ?: $role['name_en'], 'name_ar' => trim((string) Request::post('name_ar')) ?: null,
                    'require_2fa' => $twofa, 'horse_scope' => Request::post('horse_scope') === 'assigned' ? 'assigned' : 'all',
                    'edit_window' => Request::post('edit_window') === 'own_24h' ? 'own_24h' : 'any', 'restrict_country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
                    'business_hours_only' => Request::post('business_hours_only') ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
                ];
                if (DB::hasColumn('roles', 'read_only')) {
                    $set['read_only'] = $readOnly ? 1 : 0;
                }
                if (DB::hasColumn('roles', 'twofa_since') && $twofa && !$role['require_2fa']) {
                    $set['twofa_since'] = date('Y-m-d H:i:s'); // the grace period for its users starts now
                }
                DB::update('roles', $set, 'id = :id', ['id' => $role['id']]);
            });
            $new = DB::all('SELECT module, action FROM role_permissions WHERE role_id = ?', [$role['id']]);
            $fmt = fn ($rows) => array_map(fn ($r) => $r['module'] . '.' . $r['action'], $rows);
            Audit::log('permissions', 'users', 'role', $role['id'], ['removed' => array_values(array_diff($fmt($old), $fmt($new)))], ['added' => array_values(array_diff($fmt($new), $fmt($old)))], 'Permissions changed: ' . $role['name_en']);
            Cache::bump();
            $this->flash('success', __('common.saved'));
            $this->redirect('/portal/roles/' . $role['id']);
        }
        $have = [];
        foreach (DB::all('SELECT module, action FROM role_permissions WHERE role_id = ?', [$role['id']]) as $p) {
            $have[$p['module']][$p['action']] = true;
        }
        $this->view('portal/admin/role', ['title' => loc($role, 'name'), 'role' => $role, 'have' => $have, 'isDefault' => isset(DefaultRoles::definitions()[$role['slug']])]);
    }

    public function resetRole(string $id): void
    {
        $this->owner();
        $role = DB::row('SELECT * FROM roles WHERE id = ?', [(int) $id]) ?? Response::notFound();
        $def = DefaultRoles::definitions()[$role['slug']] ?? null;
        if ($def) {
            DB::transaction(function () use ($role, $def) {
                DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$role['id']]);
                foreach ($def['perms'] as $m => $actions) {
                    foreach ($actions as $a) {
                        DB::insert('role_permissions', ['role_id' => $role['id'], 'module' => $m, 'action' => $a]);
                    }
                }
                $set = ['require_2fa' => $def['require_2fa'], 'horse_scope' => $def['horse_scope'], 'edit_window' => $def['edit_window']];
                if (DB::hasColumn('roles', 'twofa_since') && $def['require_2fa'] && !$role['require_2fa']) {
                    $set['twofa_since'] = date('Y-m-d H:i:s'); // as in role(): the grace period for its users starts now
                }
                DB::update('roles', $set, 'id = :id', ['id' => $role['id']]);
            });
            Audit::log('permissions', 'users', 'role', $role['id'], null, ['reset' => 'default'], 'Role reset to default: ' . $role['name_en']);
            Cache::bump();
            $this->flash('success', __('roles.reset_done'));
        }
        $this->redirect('/portal/roles/' . $role['id']);
    }

    // ------------------------------------------------------------------ user security actions

    private function user(string $id): array
    {
        Auth::requirePerm('users', 'edit');
        $res = Registry::get('users');
        $u = $res->find((int) $id) ?? Response::notFound();
        if (!$res->canEdit($u) || (int) $u['id'] === Auth::id()) {
            Auth::deny('users.edit');
        }
        return $u;
    }

    /** Users → a user → Reset password: set one now (the Owner, nothing emailed) or email a one-time link. */
    public function resetPasswordForm(string $id): void
    {
        $u = $this->user($id);
        $this->view('portal/admin/user_reset', [
            'title' => __('users.reset_title'), 'u' => $u, 'canSet' => Auth::isOwner(), 'suggested' => \App\Services\Passwords::generate(),
            'email' => filter_var((string) $u['email'], FILTER_VALIDATE_EMAIL) ? UserService::maskEmail((string) $u['email']) : null,
        ]);
    }

    /** The Owner sets the password on the spot; it is shown once more so it can be passed on in person. */
    public function setPassword(string $id): void
    {
        $u = $this->user($id);
        if (!Auth::isOwner()) {
            Auth::deny('users.set_password');
        }
        $pw = trim((string) Request::post('password'));
        $error = \App\Services\Passwords::validate($pw, [(string) $u['username'], (string) $u['name'], explode('@', (string) $u['email'])[0]]);
        if ($error) {
            $this->flash('error', __($error === 'auth.pw_contains_name' ? 'users.pw_contains_their_name' : $error));
            $this->redirect('/portal/users/' . $u['id'] . '/reset-password');
        }
        UserService::setPassword((int) $u['id'], $pw);
        $this->flash('info', __('users.password_set', ['name' => $u['name'], 'password' => $pw]));
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function resetPassword(string $id): void
    {
        $u = $this->user($id);
        $r = UserService::resetPassword((int) $u['id']);
        match ($r['mode']) {
            'link'     => $this->flash('success', __('users.reset_link_sent', ['email' => $r['email']])),
            'password' => $this->flash('info', __('users.temp_password', ['password' => $r['password']])), // Owner only, when no email could be sent
            default    => $this->flash('error', __('users.reset_no_email')),
        };
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function reset2fa(string $id): void
    {
        $u = $this->user($id);
        UserService::reset2fa((int) $u['id']);
        $this->flash('success', __('users.twofa_reset'));
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function unlock(string $id): void
    {
        $u = $this->user($id);
        DB::update('users', ['failed_attempts' => 0, 'locked_until' => null], 'id = :id', ['id' => $u['id']]);
        AuthService::resetTwofaFailures((int) $u['id']);
        Audit::log('unlock', 'users', 'user', $u['id'], null, null, 'Account unlocked');
        $this->flash('success', __('users.unlocked'));
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function endUserSessions(string $id): void
    {
        $u = $this->user($id);
        $n = AuthService::logoutAll((int) $u['id']);
        $this->flash('success', __('users.sessions_ended', ['n' => $n]));
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function toggleStatus(string $id): void
    {
        $u = $this->user($id);
        if ($u['status'] === 'pending') {
            $this->redirect('/portal/users/' . $u['id']);
        }
        // an account turned down when it was requested (never approved) is activated by the Owner only
        if ($u['status'] === 'inactive' && !Auth::isOwner() && Users::wasRejected((int) $u['id'])) {
            $this->flash('error', __('users.rejected_owner_only'));
            $this->redirect('/portal/users/' . $u['id']);
        }
        $new = $u['status'] === 'active' ? 'inactive' : 'active';
        if ($new === 'inactive' && DB::value('SELECT slug FROM roles WHERE id = ?', [$u['role_id']]) === 'owner'
            && (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") <= 1) {
            $this->flash('error', __('users.last_owner'));
            $this->redirect('/portal/users/' . $u['id']);
        }
        DB::update('users', ['status' => $new, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $u['id']]);
        if ($new === 'inactive') {
            AuthService::logoutAll((int) $u['id']);
        }
        Audit::log('update', 'users', 'user', $u['id'], ['status' => $u['status']], ['status' => $new], $u['name']);
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/users/' . $u['id']);
    }

    /** Temporary permission with an expiry date (e.g. Technical Support needs Finance for one day). Owner only. */
    public function grant(string $id): void
    {
        $this->owner();
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? Response::notFound();
        $m = (string) Request::post('module');
        $a = (string) Request::post('action');
        $exp = strtotime((string) Request::post('expires_at'));
        if (!in_array($m, Auth::MODULES, true) || !in_array($a, Auth::ACTIONS, true) || !$exp || $exp <= time() || $exp > strtotime('+90 days')) {
            $this->flash('error', __('users.grant_invalid'));
            $this->redirect('/portal/users/' . $u['id']);
        }
        $gid = DB::insert('user_temp_grants', ['user_id' => $u['id'], 'module' => $m, 'action' => $a, 'expires_at' => date('Y-m-d H:i:s', $exp), 'granted_by' => Auth::id()]);
        Audit::log('grant', 'users', 'user', $u['id'], null, ['grant_id' => $gid, 'module' => $m, 'action' => $a, 'expires_at' => date('Y-m-d H:i', $exp)], 'Temporary access: ' . $m . '.' . $a);
        $this->flash('success', __('users.granted'));
        $this->redirect('/portal/users/' . $u['id']);
    }

    public function revokeGrant(string $id, string $gid): void
    {
        $this->owner();
        $g = DB::row('SELECT * FROM user_temp_grants WHERE id = ? AND user_id = ?', [(int) $gid, (int) $id]) ?? Response::notFound();
        DB::run('DELETE FROM user_temp_grants WHERE id = ?', [$g['id']]);
        Audit::log('revoke', 'users', 'user', (int) $id, $g, null, 'Temporary access removed: ' . $g['module'] . '.' . $g['action']);
        $this->redirect('/portal/users/' . (int) $id);
    }

    /** A deleted user's own restore/permanent-delete, reached directly from the "Deleted users" list. */
    private function userTrashRow(string $id): array
    {
        return DB::row("SELECT * FROM trash WHERE record_table = 'users' AND record_id = ?", [(int) $id]) ?? Response::notFound();
    }

    public function restoreUser(string $id): void
    {
        $this->owner(); // only the Owner restores from the Trash (and handles Owner / General Manager accounts)
        $t = $this->userTrashRow($id);
        try {
            TrashService::restore((int) $t['id']);
            $this->flash('success', __('users.restored'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/portal/users?trashed=1');
    }

    public function purgeUser(string $id): void
    {
        $this->owner();
        $t = $this->userTrashRow($id);
        $ok = TrashService::purge((int) $t['id']);
        $this->flash($ok ? 'success' : 'error', __($ok ? 'users.purged' : 'users.purge_blocked'));
        $this->redirect('/portal/users?trashed=1');
    }

    // ------------------------------------------------------------------ sessions

    public function sessions(): void
    {
        $this->owner();
        $rows = DB::all('SELECT s.*, u.name, u.username FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.revoked_at IS NULL AND s.last_seen_at > NOW() - INTERVAL 7 DAY ORDER BY s.last_seen_at DESC LIMIT 300');
        // the devices each user logged in from (no alert for these; "Forget" makes the next login from it alert again)
        $devices = DB::hasColumn('known_devices', 'ip_net')
            ? DB::all('SELECT k.*, u.name FROM known_devices k JOIN users u ON u.id = k.user_id WHERE u.deleted_at IS NULL ORDER BY k.last_seen DESC LIMIT 200') : [];
        $this->view('portal/admin/sessions', ['title' => __('nav.sessions'), 'rows' => $rows, 'devices' => $devices, 'current' => hash('sha256', (string) \App\Core\Session::get('sid_token'))]);
    }

    /** Forgets a known device: the next login from it counts as a new device again. */
    public function forgetDevice(string $id): void
    {
        $this->owner();
        $d = DB::row('SELECT k.*, u.name FROM known_devices k JOIN users u ON u.id = k.user_id WHERE k.id = ?', [(int) $id]) ?? Response::notFound();
        DB::run('DELETE FROM known_devices WHERE id = ?', [$d['id']]);
        Audit::log('device_forgotten', 'users', 'user', (int) $d['user_id'], ['browser' => $d['browser'] ?? null, 'network' => $d['ip_net'] ?? null], null, 'Known device forgotten: ' . $d['name']);
        $this->flash('success', __('sessions.device_forgotten'));
        $this->redirect('/portal/sessions#devices');
    }

    public function endSession(string $id): void
    {
        $this->owner();
        $s = DB::row('SELECT s.*, u.name FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) $id]) ?? Response::notFound();
        DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ?', [$s['id']]);
        Audit::log('session_ended', 'users', 'user', $s['user_id'], null, ['session_id' => $s['id'], 'device' => $s['device']], 'Session ended: ' . $s['name']);
        $this->flash('success', __('users.session_ended'));
        $this->redirect('/portal/sessions');
    }

    // ------------------------------------------------------------------ settings

    private const SETTINGS = [
        // 'owner' => true: only the Owner may change it (the approval limit and where the Owner's alerts go)
        'approval.bill_limit_qar'       => ['money', 'settings.bill_limit', 'owner' => true],
        'notify.owner_phone'            => ['phone', 'settings.owner_phone', 'owner' => true],
        'notify.daily_summary_email'    => ['bool', 'settings.daily_email'],
        'notify.daily_summary_whatsapp' => ['bool', 'settings.daily_whatsapp'],
        'notify.daily_summary_time'     => ['time', 'settings.daily_time'],
        'notify.summary_email'          => ['email', 'settings.summary_email', 'owner' => true],
        'company.name_en'               => ['text', 'settings.company_en'],
        'company.name_ar'               => ['text', 'settings.company_ar'],
        'company.cr'                    => ['text', 'settings.cr'],
        'semen_stock.low_threshold'     => ['int', 'settings.semen_low_threshold'],
        'finance.default_terms_days'    => ['int', 'settings.default_terms', '30'],
        'hr.weekend_days'               => ['checks', 'settings.weekend_days', '5', 'options' => [0 => 'common.dow_0', 1 => 'common.dow_1', 2 => 'common.dow_2', 3 => 'common.dow_3',
                                                                                         4 => 'common.dow_4', 5 => 'common.dow_5', 6 => 'common.dow_6']],
        'hr.paid_leave_types'           => ['checks', 'settings.paid_leave_types', 'annual,sick,emergency', 'options' => \App\Resources\LeaveRequests::TYPES],
    ];

    /** Settings only the Owner may change; others see them read-only. */
    private static function ownerSettings(): array
    {
        return array_keys(array_filter(self::SETTINGS, fn ($d) => !empty($d['owner'])));
    }

    public static function saveSettings(array $defs): array
    {
        $errors = [];
        foreach ($defs as $key => $def) {
            $type = $def[0];
            $field = str_replace('.', '__', $key);
            if ($type === 'checks') {
                // ticked options, stored as "5" or "annual,sick" (in the options' own order)
                $sel = array_map('strval', (array) ($_POST[$field] ?? []));
                $v = implode(',', array_values(array_filter(array_map('strval', array_keys($def['options'])), fn ($k) => in_array($k, $sel, true))));
                $old = Settings::get($key);
                if ((string) $old !== $v) {
                    Settings::set($key, $v);
                    Audit::log('setting', 'settings', 'setting', null, [$key => $old], [$key => $v], 'Setting changed: ' . $key);
                }
                continue;
            }
            $v = trim((string) ($_POST[$field] ?? ''));
            if ($type === 'encrypted') {
                // Secrets are never redisplayed, so a blank submission means "leave it as it is".
                if ($v !== '') {
                    \App\Services\Settings::setEncrypted($key, mb_substr($v, 0, 4000));
                    Audit::log('setting', 'settings', 'setting', null, null, [$key => '[changed]'], 'Setting changed: ' . $key);
                }
                continue;
            }
            if ($type === 'bool') {
                $v = !empty($_POST[$field]) ? '1' : '0';
            } elseif ($type === 'money' || $type === 'int') {
                $n = Money::parse($v);
                if ($n === null || $n < 0) {
                    $errors[$field] = __('validation.number');
                    continue;
                }
                $v = (string) ($type === 'int' ? (int) $n : round($n, 2));
            } elseif ($type === 'phone' && $v !== '' && !preg_match('/^\+?\d{6,15}$/', preg_replace('/[\s\-()]/', '', $v))) {
                $errors[$field] = __('validation.phone');
                continue;
            } elseif ($type === 'time' && $v !== '' && !preg_match('/^\d{2}:\d{2}$/', $v)) {
                $errors[$field] = __('validation.time');
                continue;
            } elseif ($type === 'hours' && $v !== '' && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $v)) {
                $errors[$field] = __('validation.invalid');
                continue;
            } elseif ($type === 'url' && $v !== '' && !preg_match('#^https://[^\s<>"]+$#i', $v)) {
                $errors[$field] = __('validation.url');
                continue;
            } elseif ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = __('validation.email');
                continue;
            }
            $v = mb_substr($v, 0, $type === 'textarea' ? 3000 : 500);
            $old = Settings::get($key);
            if ((string) $old !== $v) {
                Settings::set($key, $v);
                Audit::log('setting', 'settings', 'setting', null, [$key => $old], [$key => $v], 'Setting changed: ' . $key);
            }
        }
        return $errors;
    }

    public function settings(): void
    {
        Auth::requirePerm('settings', 'view');
        if (Request::isPost()) {
            Auth::requirePerm('settings', 'edit');
            $defs = self::SETTINGS;
            if (!Auth::isOwner()) {
                // Server-side, not just a disabled field: a submitted change to an Owner-only setting is refused
                foreach (self::ownerSettings() as $key) {
                    $field = str_replace('.', '__', $key);
                    if (isset($_POST[$field]) && trim((string) $_POST[$field]) !== (string) Settings::get($key)) {
                        Auth::deny('settings.owner_only.' . $key);
                    }
                    unset($defs[$key]);
                }
            }
            $before = array_map(fn ($k) => (string) Settings::get($k), array_combine(self::ownerSettings(), self::ownerSettings()));
            $errors = self::saveSettings($defs);
            $changed = array_keys(array_filter($before, fn ($old, $k) => (string) Settings::get($k) !== $old, ARRAY_FILTER_USE_BOTH));
            if ($changed) {
                \App\Services\Notifier::owners('security', \App\Services\Notifier::msg('notify.owner_setting_title'),__('notify.owner_setting_body', [
                    'by' => (string) (Auth::user()['name'] ?? ''),
                    'settings' => implode(', ', array_map(fn ($k) => __(self::SETTINGS[$k][1]) . ': ' . ($before[$k] !== '' ? $before[$k] : '—') . ' → ' . ((string) Settings::get($k) ?: '—'), $changed)),
                ]), '/portal/settings', true);
            }
            \App\Core\Session::errors($errors);
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'common.saved'));
            Cache::bump();
            $this->redirect('/portal/settings');
        }
        $mail = \App\Core\Config::get('mail.driver', 'log');
        $this->view('portal/admin/settings', ['title' => __('nav.settings'), 'defs' => self::SETTINGS, 'mail' => $mail, 'canEdit' => Auth::can('settings', 'edit'),
            'locked' => Auth::isOwner() ? [] : self::ownerSettings(),
            'whatsapp' => \App\Services\Settings::has('whatsapp.token') || (bool) \App\Core\Config::get('whatsapp.token'), 'drive' => \App\Services\Offsite::configured(),
            'offsite' => ['dest' => \App\Services\Offsite::destination(), 'last' => \App\Services\Offsite::lastOk(), 'late' => \App\Services\Offsite::late()],
            'mailVerified' => (string) Settings::get('mail.verified_at', ''),
            'cron' => array_map(fn ($t) => ['task' => $t] + (DB::row('SELECT started_at, ok, message FROM cron_runs WHERE task = ? ORDER BY id DESC LIMIT 1', [$t]) ?? []), array_keys(\App\Services\Cron::TASKS)),
            'cronLast' => DB::value('SELECT MAX(started_at) FROM cron_runs'),
            'isOwner' => Auth::isOwner(), 'pendingDb' => Auth::isOwner() ? Migrator::pendingCount() : 0, 'ownerEmail' => (string) (Auth::user()['email'] ?? '')]);
    }

    private const WHATSAPP = [
        'whatsapp.token'            => ['encrypted', 'settings.wa_token'],
        'whatsapp.phone_number_id'  => ['text', 'settings.wa_phone_id'],
        'whatsapp.api_version'      => ['text', 'settings.wa_api_version'],
    ];

    public function whatsapp(): void
    {
        $this->owner();
        if (Request::isPost()) {
            $errors = self::saveSettings(self::WHATSAPP);
            \App\Core\Session::errors($errors);
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'common.saved'));
            Cache::bump();
            $this->redirect('/portal/settings/whatsapp');
        }
        $connected = \App\Services\Settings::has('whatsapp.token') || (bool) \App\Core\Config::get('whatsapp.token');
        $this->view('portal/admin/whatsapp', ['title' => __('settings.whatsapp'), 'defs' => self::WHATSAPP, 'connected' => $connected]);
    }

    private const SECURITY = [
        'security.business_hours'      => ['hours', 'security.business_hours'],
        'security.alert_new_device'    => ['bool', 'security.alert_new_device'],
        'security.weekly_report'       => ['bool', 'security.weekly_report'],
        'security.large_export_rows'   => ['int', 'security.large_export'],
        'security.twofa_grace_days'    => ['int', 'security.twofa_grace', '7'],
        'approval.owner_second'        => ['bool', 'security.owner_second'],
    ];

    public function security(): void
    {
        $this->owner();
        if (Request::isPost()) {
            if (Request::post('action') === 'logout_everyone') {
                $n = DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND token_hash <> ?', [hash('sha256', (string) \App\Core\Session::get('sid_token'))])->rowCount();
                Audit::log('sessions_revoked', 'users', null, null, null, ['count' => $n], 'Everyone logged out by the Owner');
                $this->flash('success', __('users.sessions_ended', ['n' => $n]));
            } elseif (Request::post('action') === 'twofa_remind') {
                // a reminder (in the portal and by email) to set up the two-step login
                $u = DB::row("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL AND twofa_method = 'none'", [(int) Request::post('user_id')]);
                if ($u) {
                    \App\Services\Notifier::user((int) $u['id'], 'security', \App\Services\Notifier::msg('security.twofa_remind_title'), \App\Services\Notifier::msg('security.twofa_remind_body'), '/portal/account/2fa', true);
                    Audit::log('twofa_reminder', 'users', 'user', (int) $u['id'], null, null, '2FA reminder sent: ' . $u['name']);
                    $this->flash('success', __('security.twofa_reminded', ['name' => $u['name']]));
                }
            } else {
                $errors = self::saveSettings(self::SECURITY);
                \App\Core\Session::errors($errors);
                $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'common.saved'));
            }
            $this->redirect('/portal/settings/security');
        }
        $stats = [
            'failed' => (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > NOW() - INTERVAL 7 DAY'),
            'locked' => (int) DB::value('SELECT COUNT(*) FROM users WHERE locked_until > NOW()'),
            'denied' => (int) DB::value("SELECT COUNT(*) FROM activity_log WHERE action = 'access_denied' AND created_at > NOW() - INTERVAL 7 DAY"),
            // with their last login and last action, and whether they are blocked or still in the grace period
            'no2fa'  => array_map(fn ($u) => $u + ['grace_until' => AuthService::twofaGraceUntil($u, ['require_2fa' => 1, 'twofa_since' => $u['twofa_since']])],
                DB::all("SELECT u.id, u.name, u.created_at, u.last_login_at, u.twofa_method, r.name_en AS role, r.name_ar AS role_ar, "
                    . (DB::hasColumn('roles', 'twofa_since') ? 'r.twofa_since' : 'NULL AS twofa_since') . ",
                    (SELECT MAX(a.created_at) FROM activity_log a WHERE a.user_id = u.id AND a.action NOT IN ('login','logout','login_failed','access_denied','csrf_rejected','view_sensitive')) AS last_action
                    FROM users u JOIN roles r ON r.id = u.role_id WHERE r.require_2fa = 1 AND u.twofa_method = 'none' AND u.status = 'active' AND u.deleted_at IS NULL ORDER BY u.name")),
            'roles'  => DB::all('SELECT id, name_en, name_ar, require_2fa, restrict_country, business_hours_only FROM roles ORDER BY id'),
            'chain'  => Audit::verify(),
        ];
        $this->view('portal/admin/security', ['title' => __('nav.security'), 'defs' => self::SECURITY, 'stats' => $stats]);
    }

    /** Administration → System errors (Owner): what went wrong lately, newest first; ?ref= opens one with its trace. */
    public function errors(): void
    {
        $this->owner();
        $ref = strtolower(trim((string) Request::query('ref', '')));
        $entries = \App\Services\ErrorLog::entries();
        $this->view('portal/admin/errors', [
            'title' => __('nav.errors'), 'entries' => array_slice($entries, 0, 200), 'total' => count($entries),
            'one' => preg_match('/^[0-9a-f]{8}$/', $ref) ? \App\Services\ErrorLog::find($ref) : null, 'ref' => $ref, 'php' => \App\Services\ErrorLog::phpTail(40),
            'server' => \App\Services\ErrorLog::server(),
        ]);
    }

    public function currencies(): void
    {
        Auth::requireLogin();
        if (!Auth::can('settings') && !Auth::can('finance', 'edit')) {
            Auth::deny('settings.view');
        }
        $canEdit = Auth::can('settings', 'edit') || Auth::can('finance', 'edit');
        if (Request::isPost()) {
            if (!$canEdit) {
                Auth::deny('settings.edit');
            }
            foreach (DB::all('SELECT * FROM currencies') as $c) {
                $in = $_POST['cur'][$c['code']] ?? null;
                if (!is_array($in) || $c['code'] === 'QAR') {
                    continue;
                }
                $rate = Money::parse($in['rate'] ?? null);
                $upd = ['active' => !empty($in['active']) ? 1 : 0, 'auto_update' => !empty($in['auto']) ? 1 : 0];
                if ($rate !== null && $rate > 0 && abs($rate - (float) $c['rate_to_qar']) > 0.0000005) {
                    $upd += ['rate_to_qar' => round($rate, 6), 'manual_override' => 1, 'updated_at' => date('Y-m-d H:i:s')];
                    DB::insert('currency_rate_history', ['code' => $c['code'], 'rate_to_qar' => round($rate, 6), 'source' => 'manual']);
                    Audit::log('update', 'settings', 'currency', null, ['rate' => $c['rate_to_qar']], ['rate' => round($rate, 6)], 'Exchange rate ' . $c['code']);
                }
                DB::update('currencies', $upd, 'code = :c', ['c' => $c['code']]);
            }
            $this->flash('success', __('common.saved'));
            $this->redirect('/portal/settings/currencies');
        }
        $rows = DB::all("SELECT * FROM currencies ORDER BY code = 'QAR' DESC, code");
        $history = DB::all('SELECT * FROM currency_rate_history ORDER BY id DESC LIMIT 30');
        $this->view('portal/admin/currencies', ['title' => __('nav.currencies'), 'rows' => $rows, 'history' => $history, 'canEdit' => $canEdit]);
    }

    // ------------------------------------------------------------------ backups

    public function backups(): void
    {
        $this->owner();
        $this->view('portal/admin/backups', ['title' => __('nav.backups'), 'files' => BackupService::list(),
            'drive' => \App\Services\Offsite::configured(), 'folderId' => (string) Settings::get('backup.gdrive_folder_id', \App\Core\Config::get('backup.gdrive_folder_id', '')),
            'offsite' => ['dest' => \App\Services\Offsite::destination(), 'last' => \App\Services\Offsite::lastOk(), 'late' => \App\Services\Offsite::late(),
                          'error' => (string) Settings::get('backup.offsite_last_error', '')],
            'hasKey' => Settings::has('backup.gdrive_json') || is_file((string) \App\Core\Config::get('backup.gdrive_service_account_json', '')),
            'last' => DB::row("SELECT created_at, new_values FROM activity_log WHERE action = 'backup' ORDER BY id DESC LIMIT 1")]);
    }

    /** Loads the newest backup into the restore-test database. A POST with the form token, so no link can start it. */
    public function testRestore(): void
    {
        $this->owner();
        try {
            [$ok, $msg] = BackupService::restoreTest();
        } catch (\Throwable $e) { // shown as a failed test (as the nightly one does), not an error page
            [$ok, $msg] = [false, $e->getMessage()];
        }
        $this->flash($ok ? 'success' : 'error', __($ok ? 'backups.test_ok' : 'backups.test_bad') . ' — ' . $msg);
        $this->redirect('/portal/settings/backups');
    }

    /** Google Drive off-server copy: folder ID and the service-account key, editable from the portal. */
    public function saveDrive(): void
    {
        $this->owner();
        $folderId = trim((string) Request::post('gdrive_folder_id', ''));
        $oldFolderId = (string) Settings::get('backup.gdrive_folder_id', '');
        if ($oldFolderId !== $folderId) {
            Settings::set('backup.gdrive_folder_id', $folderId !== '' ? $folderId : null);
            Audit::log('setting', 'settings', 'setting', null, ['backup.gdrive_folder_id' => $oldFolderId], ['backup.gdrive_folder_id' => $folderId], 'Setting changed: backup.gdrive_folder_id');
        }
        $file = $_FILES['gdrive_json'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $content = (string) file_get_contents($file['tmp_name']);
            $json = json_decode($content, true);
            if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
                $this->flash('error', __('settings.drive_json_invalid'));
                $this->redirect('/portal/settings/backups');
            }
            Settings::setEncrypted('backup.gdrive_json', $content);
            Audit::log('setting', 'settings', 'setting', null, null, ['backup.gdrive_json' => '[changed]'], 'Google Drive service account key updated');
        } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->flash('error', __('files.upload_failed'));
            $this->redirect('/portal/settings/backups');
        }
        $this->flash('success', __('common.saved'));
        Cache::bump();
        $this->redirect('/portal/settings/backups');
    }

    /** Applies the database changes an uploaded update brought (Settings and the dashboard point the Owner here).
     * A backup is made first; without one nothing is changed. */
    public function databaseUpdate(): void
    {
        $this->owner();
        if (!Migrator::pending()) {
            $this->redirect('/portal/settings');
        }
        try {
            BackupService::run('before database update');
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
            $this->flash('error', __('settings.db_update_no_backup'));
            $this->redirect('/portal/settings');
        }
        try {
            $log = Migrator::run();
            Audit::log('update', 'settings', null, null, null, ['database' => $log], 'Database updated');
            Cache::bump();
            $this->flash('success', __('settings.db_updated', ['n' => count(array_filter($log, fn ($l) => str_starts_with($l, 'applied') || str_starts_with($l, 'skipped')))]));
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
            $this->flash('error', __('settings.db_update_failed') . ' ' . $e->getMessage());
        }
        $this->redirect('/portal/settings');
    }

    /** Settings → Connections: sends a test email, so the mail settings in config.php can be checked from here. */
    public function testEmail(): void
    {
        $this->owner();
        $to = trim((string) Request::post('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', __('validation.email'));
            $this->redirect('/portal/settings');
        }
        $driver = (string) \App\Core\Config::get('mail.driver', 'log');
        $ok = Mailer::send($to, __('settings.test_mail_subject'), '<p>' . e(__('settings.test_mail_body', ['time' => fmt_date(date('Y-m-d H:i:s'), true)])) . '</p>');
        Audit::log('test_email', 'settings', null, null, null, ['to' => $to, 'sent' => $ok, 'driver' => $driver], 'Test email');
        if (!in_array($driver, ['smtp', 'mail'], true)) {
            $this->flash('info', __('settings.test_mail_log'));
        } elseif ($ok) {
            Settings::set('mail.verified_at', date('Y-m-d H:i:s')); // email works: 2FA codes by email can be offered
            $this->flash('success', __('settings.test_mail_sent', ['email' => $to]));
        } else {
            $this->flash('error', __('settings.test_mail_failed') . (Mailer::$lastError ? ' (' . Mailer::$lastError . ')' : ''));
        }
        $this->redirect('/portal/settings');
    }

    public function runBackup(): void
    {
        $this->owner();
        try {
            $files = BackupService::run('manual');
            try {
                $off = \App\Services\Offsite::upload($files);
            } catch (\Throwable $e) {
                \App\Core\ErrorHandler::log($e);
                $off = __('backups.offsite_failed') . ' ' . $e->getMessage();
            }
            $this->flash('success', __('backups.done', ['files' => implode(', ', $files)]) . ($off ? ' · ' . $off : ''));
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
            $this->flash('error', __('backups.failed'));
        }
        $this->redirect('/portal/settings/backups');
    }

    public function downloadBackup(string $name): void
    {
        $this->owner();
        $p = BackupService::path($name) ?? Response::notFound();
        Audit::log('download', 'settings', null, null, null, ['backup' => $name], 'Backup downloaded');
        Response::file($p, str_ends_with($name, '.zip') ? 'application/zip' : 'application/gzip', $name, true);
    }

    // ------------------------------------------------------------------ trash

    public function trash(): void
    {
        $this->owner();
        $rows = DB::all('SELECT t.*, u.name AS by_name FROM trash t LEFT JOIN users u ON u.id = t.deleted_by ORDER BY t.deleted_at DESC LIMIT 500');
        $this->view('portal/admin/trash', ['title' => __('nav.trash'), 'rows' => $rows]);
    }

    public function restore(string $id): void
    {
        $this->owner();
        try {
            TrashService::restore((int) $id);
            $this->flash('success', __('trash.restored'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage()); // e.g. a payment whose bill was paid again meanwhile
        }
        $this->redirect('/portal/trash');
    }

    public function purge(string $id): void
    {
        $this->owner();
        $t = DB::row('SELECT * FROM trash WHERE id = ?', [(int) $id]);
        $ok = TrashService::purge((int) $id);
        $this->flash($ok ? 'success' : 'error', __($ok ? 'trash.purged' : ($t && TrashService::keptForGood($t) ? 'trash.kept_help' : 'trash.purge_blocked')));
        $this->redirect('/portal/trash');
    }

    public function emptyTrash(): void
    {
        $this->owner();
        $kept = 0;
        foreach (DB::column('SELECT id FROM trash') as $tid) {
            if (!TrashService::purge((int) $tid)) {
                $kept++;
            }
        }
        $this->flash($kept ? 'warning' : 'success', $kept ? __('trash.partly_emptied', ['n' => $kept]) : __('trash.emptied'));
        $this->redirect('/portal/trash');
    }

    // ------------------------------------------------------------------ activity log (read-only, tamper-evident)

    /** Only the Owner sees the full log; others with access (Technical Support) see login and security events. */
    private const SECURITY_ACTIONS = ['login', 'logout', 'login_failed', 'login_blocked', 'account_locked', 'access_denied', 'csrf_rejected',
        '2fa_enabled', '2fa_disabled', 'password_changed', 'session_ended', 'sessions_revoked', 'backup', 'unlock'];

    private function activityScope(array &$where, array &$p): void
    {
        if (!Auth::isOwner()) {
            $where[] = 'a.action IN ' . DB::in(self::SECURITY_ACTIONS, 'sa', $p);
        }
    }

    public function activity(): void
    {
        Auth::requirePerm('activity', 'view');
        $where = ['1=1'];
        $p = [];
        $this->activityScope($where, $p);
        foreach (['user_id' => 'a.user_id = :u', 'action' => 'a.action = :a', 'module' => 'a.module = :m'] as $k => $sql) {
            $v = (string) Request::query($k, '');
            if ($v !== '') {
                $where[] = $sql;
                $p[substr($sql, strpos($sql, ':') + 1)] = $v;
            }
        }
        if (($from = (string) Request::query('from', '')) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'a.created_at >= :f';
            $p['f'] = $from . ' 00:00:00';
        }
        if (($to = (string) Request::query('to', '')) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'a.created_at <= :t';
            $p['t'] = $to . ' 23:59:59';
        }
        if (($q = trim((string) Request::query('q', ''))) !== '') {
            $where[] = '(a.summary LIKE :q OR a.user_name LIKE :q2 OR a.ip LIKE :q3)';
            $p['q'] = $p['q2'] = $p['q3'] = '%' . $q . '%';
        }
        $per = in_array((int) Request::query('per'), [25, 50, 100], true) ? (int) Request::query('per') : 50; // the list's 25 / 50 / 100 buttons
        $total = (int) DB::value('SELECT COUNT(*) FROM activity_log a WHERE ' . implode(' AND ', $where), $p);
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, (int) Request::query('page', 1)), $pages);
        if (Request::query('export') === 'xlsx') {
            Auth::requirePerm('activity', 'export');
            $rows = DB::all('SELECT a.created_at, a.user_name, a.action, a.module, a.summary, a.ip, a.device FROM activity_log a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 20000', $p);
            Audit::log('export', 'activity', null, null, null, ['rows' => count($rows)], 'Activity log exported');
            \App\Services\Exporter::output(__('nav.activity'), [__('common.date'), __('activity.user'), __('activity.action'), __('activity.module'), __('activity.summary'), 'IP', __('account.device')], array_map('array_values', $rows), 'xlsx');
        }
        $rows = DB::all('SELECT a.id, a.created_at, a.user_name, a.action, a.module, a.record_type, a.record_id, a.summary, a.ip, a.device FROM activity_log a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $p);
        $this->view('portal/admin/activity', ['title' => __('nav.activity'), 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per,
            'users' => DB::pairs('SELECT id, name FROM users ORDER BY name'), 'actions' => DB::column('SELECT DISTINCT action FROM activity_log ORDER BY action'),
            'modules' => DB::column('SELECT DISTINCT module FROM activity_log WHERE module IS NOT NULL ORDER BY module')]);
    }

    public function activityItem(string $id): void
    {
        Auth::requirePerm('activity', 'view');
        $a = DB::row('SELECT * FROM activity_log WHERE id = ?', [(int) $id]) ?? Response::notFound();
        if (!Auth::isOwner() && !in_array($a['action'], self::SECURITY_ACTIONS, true)) {
            Auth::deny('owner_only');
        }
        $this->view('portal/admin/activity_item', ['title' => __('nav.activity') . ' #' . $a['id'], 'a' => $a, 'intact' => hash_equals(Audit::hashRow($a), (string) $a['hash'])]);
    }

    public function verifyLog(): void
    {
        Auth::requirePerm('activity', 'view');
        [$ok, $n, $broken] = Audit::verify();
        $this->flash($ok ? 'success' : 'error', $ok ? __('activity.chain_ok', ['n' => $n]) : __('activity.chain_broken', ['id' => $broken]));
        $this->redirect('/portal/activity');
    }

    // ------------------------------------------------------------------ migration report

    public function migrationReport(): void
    {
        $this->owner();
        $run = (string) (Request::query('run') ?: DB::value('SELECT run_id FROM migration_report ORDER BY id DESC LIMIT 1'));
        $entity = (string) Request::query('entity', '');
        $p = ['r' => $run];
        $sql = 'SELECT * FROM migration_report WHERE run_id = :r';
        if ($entity !== '') {
            $sql .= ' AND entity = :e';
            $p['e'] = $entity;
        }
        if (Request::query('open')) {
            $sql .= ' AND reviewed = 0';
        }
        $old = (array) \App\Core\Config::get('old_db', []);
        $latest = DB::row('SELECT run_id, MAX(created_at) AS at FROM migration_report GROUP BY run_id ORDER BY at DESC LIMIT 1');
        $this->view('portal/admin/migration', [
            'title' => __('nav.migration_report'), 'run' => $run, 'configured' => !empty($old['name']) && !empty($old['user']),
            'canCommit' => $latest && str_starts_with($latest['run_id'], 'P-') && strtotime($latest['at']) > time() - 86400, 'runs' => DB::column('SELECT DISTINCT run_id FROM migration_report ORDER BY run_id DESC'),
            'summary' => DB::all('SELECT entity, action, COUNT(*) AS n, SUM(reviewed) AS done FROM migration_report WHERE run_id = ? GROUP BY entity, action ORDER BY entity, action', [$run]),
            'rows' => DB::all($sql . ' ORDER BY entity, id LIMIT 1000', $p),
        ]);
    }

    /** Owner runs the old-system import from the portal: preview first, then the real import after review. */
    public function migrationRun(): void
    {
        $this->owner();
        $c = (array) \App\Core\Config::get('old_db', []);
        if (empty($c['name']) || empty($c['user'])) {
            $this->flash('error', __('migration.not_configured'));
            $this->redirect('/portal/migration-report');
        }
        $commit = Request::post('mode') === 'commit';
        if ($commit) {
            $latest = (string) DB::value('SELECT run_id FROM migration_report ORDER BY id DESC LIMIT 1');
            if (!str_starts_with($latest, 'P-') || strtoupper(trim((string) Request::post('confirm'))) !== 'IMPORT') {
                $this->flash('error', __('migration.confirm_needed'));
                $this->redirect('/portal/migration-report');
            }
        }
        try {
            $res = (new \App\Services\OldImport(\App\Services\OldImport::connect($c), $commit, \App\Services\OldImport::overridesFile()))->run();
        } catch (\PDOException $e) {
            \App\Core\ErrorHandler::log($e);
            $this->flash('error', __('migration.connect_failed'));
            $this->redirect('/portal/migration-report');
        }
        if (!$commit) {
            Audit::log('import_preview', 'settings', null, null, null, ['run' => $res['run'], 'counts' => $res['counts']], 'Import preview');
        }
        $this->flash('success', __($commit ? 'migration.imported' : 'migration.previewed', ['n' => array_sum($res['counts'])]));
        $this->redirect('/portal/migration-report?run=' . urlencode($res['run']));
    }

    public function migrationReviewAll(): void
    {
        $this->owner();
        $run = (string) Request::post('run');
        $p = ['r' => $run];
        $sql = 'UPDATE migration_report SET reviewed = 1 WHERE run_id = :r';
        if (($e = (string) Request::post('entity')) !== '') {
            $sql .= ' AND entity = :e';
            $p['e'] = $e;
        }
        DB::run($sql, $p);
        $this->redirect('/portal/migration-report?' . http_build_query(['run' => $run, 'entity' => $e ?: null]));
    }

    public function migrationReviewed(string $id): void
    {
        $this->owner();
        DB::run('UPDATE migration_report SET reviewed = 1 - reviewed WHERE id = ?', [(int) $id]);
        $this->back('/portal/migration-report');
    }
}
