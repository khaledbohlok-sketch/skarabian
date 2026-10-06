<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Approvals;
use App\Services\Passwords;

/**
 * Staff accounts. Each person has their own account (no shared logins). A new account gets a one-time password
 * and must change it at first login; accounts created by anyone but the Owner wait for Owner / GM approval.
 */
class Users extends Resource
{
    public string $key = 'users';
    public string $table = 'users';
    public string $module = 'users';
    public string $recordType = 'user';
    public string $title = 'nav.users';
    public string $singular = 'users.singular';
    public array $search = ['t.name', 't.username', 't.email'];
    public string $sort = 't.status = \'active\' DESC, t.name';

    public static ?string $newPassword = null;

    private static function roleOptions(): array
    {
        $o = [];
        foreach (DB::all('SELECT id, slug, name_en, name_ar FROM roles ORDER BY id') as $r) {
            // Only the Owner can give someone the Owner or General Manager role
            if (!Auth::isOwner() && in_array($r['slug'], ['owner', 'general_manager'], true)) {
                continue;
            }
            $o[(string) $r['id']] = loc($r, 'name');
        }
        return $o;
    }

    public function fields(): array
    {
        return [
            'name'        => ['type' => 'text', 'label' => 'users.name', 'required' => true, 'max' => 120, 'col' => 6, 'no_phone' => true],
            'username'    => ['type' => 'text', 'label' => 'users.username', 'required' => true, 'max' => 60, 'col' => 6, 'pattern' => '/^[a-z0-9._-]{3,60}$/', 'pattern_msg' => 'users.username_format', 'attrs' => ['dir' => 'ltr', 'autocomplete' => 'off']],
            'email'       => ['type' => 'email', 'label' => 'users.email', 'required' => true, 'col' => 6],
            'phone'       => ['type' => 'phone', 'label' => 'users.phone', 'col' => 6, 'help' => 'users.phone_help'],
            // Nobody changes their own role (shown read-only on their own record)
            'role_id'     => ['type' => 'select', 'label' => 'users.role', 'options' => fn () => self::roleOptions(), 'required' => true, 'col' => 6,
                'readonly' => (int) \App\Core\Request::param('id') === Auth::id() && Auth::id() !== null],
            'employee_id' => ['type' => 'picker', 'source' => 'employees', 'label' => 'users.employee', 'col' => 6, 'help' => 'users.employee_help', 'no_add' => true],
            'lang'        => ['type' => 'select', 'label' => 'users.lang', 'options' => ['en' => 'users.lang_en', 'ar' => 'users.lang_ar'], 'default' => 'en', 'col' => 6],
        ];
    }

    public function from(): string
    {
        return '`users` t JOIN roles r ON r.id = t.role_id';
    }

    public function columns(): array
    {
        return [
            'name'   => ['label' => 'users.name', 'sql' => 't.name', 'sort' => true],
            'user'   => ['label' => 'users.username', 'sql' => 't.username'],
            'role'   => ['label' => 'users.role', 'sql' => 'r.name_en'],
            'status' => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'users.status'],
            'twofa'  => ['label' => 'auth.twofa', 'sql' => 't.twofa_method', 'fmt' => 'enum', 'prefix' => 'auth.method'],
            'last'   => ['label' => 'users.last_login', 'sql' => 't.last_login_at', 'fmt' => 'datetime', 'sort' => true],
            'locked' => ['label' => 'users.locked', 'sql' => 't.locked_until > NOW()', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return [
            'status'  => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => ['active' => 'users.status_active', 'pending' => 'users.status_pending', 'inactive' => 'users.status_inactive']],
            'role_id' => ['type' => 'select', 'label' => 'users.role', 'sql' => 't.role_id', 'options' => fn () => array_map(fn ($x) => $x, DB::pairs('SELECT id, name_en FROM roles ORDER BY id'))],
        ];
    }

    public function label(array $row): string
    {
        return $row['name'] . ' (' . $row['username'] . ')';
    }

    private function isOwnerAccount(array $row): bool
    {
        return DB::value('SELECT slug FROM roles WHERE id = ?', [$row['role_id']]) === 'owner';
    }

    /** Only the Owner manages Owner accounts; nobody changes their own role here. */
    public function canEdit(array $row): bool
    {
        if (!Auth::can('users', 'edit')) {
            return false;
        }
        return Auth::isOwner() || !$this->isOwnerAccount($row);
    }

    public function canDelete(array $row): bool
    {
        return false; // accounts are deactivated, never deleted (the activity log keeps pointing to them)
    }

    public function headerBadges(array $row): string
    {
        return status_badge($row['status'], 'users.status') . ($row['locked_until'] && strtotime($row['locked_until']) > time() ? ' <span class="badge badge-bad">' . e(__('users.locked')) . '</span>' : '');
    }

    public function prepare(array $data, ?array $old): array
    {
        if ($old && !isset($data['role_id'])) {
            $data['role_id'] = $old['role_id']; // read-only on your own record
        }
        $data['username'] = mb_strtolower($data['username']);
        foreach (['username', 'email'] as $k) {
            if (DB::value("SELECT id FROM users WHERE LOWER($k) = ? AND id <> ?", [mb_strtolower($data[$k]), $old['id'] ?? 0])) {
                throw ValidationException::one($k, 'users.taken');
            }
        }
        if (!empty($data['employee_id']) && DB::value('SELECT id FROM users WHERE employee_id = ? AND id <> ? AND deleted_at IS NULL', [$data['employee_id'], $old['id'] ?? 0])) {
            throw ValidationException::one('employee_id', 'users.employee_taken');
        }
        if ($old) {
            if ((int) $old['id'] === Auth::id() && (int) $data['role_id'] !== (int) $old['role_id']) {
                throw ValidationException::one('role_id', 'users.own_role');
            }
            $wasOwner = $this->isOwnerAccount($old);
            if ($wasOwner && (int) $data['role_id'] !== (int) $old['role_id'] && (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL") <= 1) {
                throw ValidationException::one('role_id', 'users.last_owner');
            }
            return $data;
        }
        self::$newPassword = Passwords::generate();
        $data += [
            'password_hash' => Passwords::hash(self::$newPassword), 'must_change_password' => 1,
            'status' => Auth::isOwner() ? 'active' : 'pending', 'twofa_method' => 'none',
        ];
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created && $data['status'] === 'pending') {
            $role = (string) DB::value('SELECT name_en FROM roles WHERE id = ?', [$data['role_id']]);
            Approvals::request('new_user', 'user', $id, __('users.approval_title', ['name' => $data['name'], 'role' => $role]));
        }
        if ($created) {
            \App\Core\Session::flash('info', __('users.temp_password', ['password' => self::$newPassword]));
        }
        if ($old && (int) $data['role_id'] !== (int) $old['role_id']) {
            // New permissions apply at once: the person signs in again
            \App\Services\AuthService::logoutAll($id);
        }
    }

    public function actions(array $row): array
    {
        if (!$this->canEdit($row) || (int) $row['id'] === Auth::id()) {
            return [];
        }
        $id = (int) $row['id'];
        $a = [];
        if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
            $a[] = ['label' => 'users.unlock', 'url' => "/portal/users/$id/unlock", 'method' => 'post', 'class' => 'btn-primary'];
        }
        $a[] = ['label' => 'users.reset_password', 'url' => "/portal/users/$id/reset-password", 'method' => 'post', 'confirm' => 'users.confirm_reset_password'];
        if ($row['twofa_method'] !== 'none') {
            $a[] = ['label' => 'users.reset_2fa', 'url' => "/portal/users/$id/reset-2fa", 'method' => 'post', 'confirm' => 'users.confirm_reset_2fa'];
        }
        $a[] = ['label' => 'users.end_sessions', 'url' => "/portal/users/$id/sessions/end", 'method' => 'post'];
        if ($row['status'] !== 'pending') {
            $a[] = $row['status'] === 'active'
                ? ['label' => 'users.deactivate', 'url' => "/portal/users/$id/status", 'method' => 'post', 'class' => 'btn-danger', 'confirm' => 'users.confirm_deactivate']
                : ['label' => 'users.activate', 'url' => "/portal/users/$id/status", 'method' => 'post', 'class' => 'btn-primary'];
        }
        return $a;
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/admin/user_extra', ['u' => $row]);
    }

    public function tabs(array $row): array
    {
        return array_intersect_key($this->standardTabs($row), ['history' => 1]);
    }
}
