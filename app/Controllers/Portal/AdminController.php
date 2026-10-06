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
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\Cache;
use App\Services\DefaultRoles;
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
            DB::transaction(function () use ($role, $in) {
                DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$role['id']]);
                foreach (Auth::MODULES as $m) {
                    foreach (Auth::ACTIONS as $a) {
                        if (!empty($in[$m][$a])) {
                            DB::insert('role_permissions', ['role_id' => $role['id'], 'module' => $m, 'action' => $a]);
                        }
                    }
                }
                $country = strtoupper(trim((string) Request::post('restrict_country')));
                DB::update('roles', [
                    'name_en' => trim((string) Request::post('name_en')) ?: $role['name_en'], 'name_ar' => trim((string) Request::post('name_ar')) ?: null,
                    'require_2fa' => Request::post('require_2fa') ? 1 : 0, 'horse_scope' => Request::post('horse_scope') === 'assigned' ? 'assigned' : 'all',
                    'edit_window' => Request::post('edit_window') === 'own_24h' ? 'own_24h' : 'any', 'restrict_country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
                    'business_hours_only' => Request::post('business_hours_only') ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $role['id']]);
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
                DB::update('roles', ['require_2fa' => $def['require_2fa'], 'horse_scope' => $def['horse_scope'], 'edit_window' => $def['edit_window']], 'id = :id', ['id' => $role['id']]);
            });
            Audit::log('permissions', 'users', 'role', $role['id'], null, ['reset' => 'default'], 'Role reset to default: ' . $role['name_en']);
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

    public function resetPassword(string $id): void
    {
        $u = $this->user($id);
        $pw = UserService::resetPassword((int) $u['id']);
        $this->flash('info', __('users.temp_password', ['password' => $pw]));
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

    // ------------------------------------------------------------------ sessions

    public function sessions(): void
    {
        $this->owner();
        $rows = DB::all('SELECT s.*, u.name, u.username FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.revoked_at IS NULL AND s.last_seen_at > NOW() - INTERVAL 7 DAY ORDER BY s.last_seen_at DESC LIMIT 300');
        $this->view('portal/admin/sessions', ['title' => __('nav.sessions'), 'rows' => $rows, 'current' => hash('sha256', (string) \App\Core\Session::get('sid_token'))]);
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
        'approval.bill_limit_qar'       => ['money', 'settings.bill_limit'],
        'notify.owner_phone'            => ['phone', 'settings.owner_phone'],
        'notify.daily_summary_email'    => ['bool', 'settings.daily_email'],
        'notify.daily_summary_whatsapp' => ['bool', 'settings.daily_whatsapp'],
        'notify.daily_summary_time'     => ['time', 'settings.daily_time'],
        'company.name_en'               => ['text', 'settings.company_en'],
        'company.name_ar'               => ['text', 'settings.company_ar'],
        'company.cr'                    => ['text', 'settings.cr'],
    ];

    public static function saveSettings(array $defs): array
    {
        $errors = [];
        foreach ($defs as $key => [$type]) {
            $field = str_replace('.', '__', $key);
            $v = trim((string) ($_POST[$field] ?? ''));
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
            $errors = self::saveSettings(self::SETTINGS);
            \App\Core\Session::errors($errors);
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'common.saved'));
            Cache::bump();
            $this->redirect('/portal/settings');
        }
        $mail = \App\Core\Config::get('mail.driver', 'log');
        $this->view('portal/admin/settings', ['title' => __('nav.settings'), 'defs' => self::SETTINGS, 'mail' => $mail, 'canEdit' => Auth::can('settings', 'edit'),
            'whatsapp' => (bool) \App\Core\Config::get('whatsapp.token'), 'drive' => \App\Services\Offsite::configured(),
            'cron' => array_map(fn ($t) => ['task' => $t] + (DB::row('SELECT started_at, ok, message FROM cron_runs WHERE task = ? ORDER BY id DESC LIMIT 1', [$t]) ?? []), array_keys(\App\Services\Cron::TASKS)),
            'cronLast' => DB::value('SELECT MAX(started_at) FROM cron_runs')]);
    }

    private const SECURITY = [
        'security.business_hours'      => ['hours', 'security.business_hours'],
        'security.alert_new_device'    => ['bool', 'security.alert_new_device'],
        'security.weekly_report'       => ['bool', 'security.weekly_report'],
        'security.large_export_rows'   => ['int', 'security.large_export'],
    ];

    public function security(): void
    {
        $this->owner();
        if (Request::isPost()) {
            if (Request::post('action') === 'logout_everyone') {
                $n = DB::run('UPDATE user_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND token_hash <> ?', [hash('sha256', (string) \App\Core\Session::get('sid_token'))])->rowCount();
                Audit::log('sessions_revoked', 'users', null, null, null, ['count' => $n], 'Everyone logged out by the Owner');
                $this->flash('success', __('users.sessions_ended', ['n' => $n]));
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
            'no2fa'  => DB::all("SELECT u.id, u.name, r.name_en AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE r.require_2fa = 1 AND u.twofa_method = 'none' AND u.status = 'active' AND u.deleted_at IS NULL"),
            'roles'  => DB::all('SELECT id, name_en, name_ar, require_2fa, restrict_country, business_hours_only FROM roles ORDER BY id'),
            'chain'  => Audit::verify(),
        ];
        $this->view('portal/admin/security', ['title' => __('nav.security'), 'defs' => self::SECURITY, 'stats' => $stats]);
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
        $this->view('portal/admin/backups', ['title' => __('nav.backups'), 'files' => BackupService::list(), 'test' => (string) Request::query('test') === '1' ? BackupService::restoreTest() : null,
            'drive' => \App\Services\Offsite::configured(), 'last' => DB::row("SELECT created_at, new_values FROM activity_log WHERE action = 'backup' ORDER BY id DESC LIMIT 1")]);
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
        TrashService::restore((int) $id);
        $this->flash('success', __('trash.restored'));
        $this->redirect('/portal/trash');
    }

    public function purge(string $id): void
    {
        $this->owner();
        $ok = TrashService::purge((int) $id);
        $this->flash($ok ? 'success' : 'error', __($ok ? 'trash.purged' : 'trash.purge_blocked'));
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
        $page = max(1, (int) Request::query('page', 1));
        $total = (int) DB::value('SELECT COUNT(*) FROM activity_log a WHERE ' . implode(' AND ', $where), $p);
        if (Request::query('export') === 'xlsx') {
            Auth::requirePerm('activity', 'export');
            $rows = DB::all('SELECT a.created_at, a.user_name, a.action, a.module, a.summary, a.ip, a.device FROM activity_log a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 20000', $p);
            Audit::log('export', 'activity', null, null, null, ['rows' => count($rows)], 'Activity log exported');
            \App\Services\Exporter::output(__('nav.activity'), [__('common.date'), __('activity.user'), __('activity.action'), __('activity.module'), __('activity.summary'), 'IP', __('account.device')], array_map('array_values', $rows), 'xlsx');
        }
        $rows = DB::all('SELECT a.id, a.created_at, a.user_name, a.action, a.module, a.record_type, a.record_id, a.summary, a.ip, a.device FROM activity_log a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $p);
        $this->view('portal/admin/activity', ['title' => __('nav.activity'), 'rows' => $rows, 'total' => $total, 'page' => $page,
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
