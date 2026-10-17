<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Current user + server-side permission checks (module × action matrix).
 * Every controller action calls Auth::requirePerm(); hiding a button is never the only protection.
 */
final class Auth
{
    public const MODULES = [
        'horses', 'horse_health', 'horse_breeding', 'horse_diet', 'horse_training', 'horse_notes',
        'embryos', 'semen_stock', 'hr', 'finance', 'payroll', 'inventory', 'studio', 'cms', 'inbox', 'reports',
        'documents', 'users', 'settings', 'activity', 'attendance_scan',
    ];
    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'print', 'sensitive'];
    /** Modules that use only some actions (the roles screen shows just those): the gate scanner only records scans. */
    public const MODULE_ACTIONS = ['attendance_scan' => ['create']];

    private static ?array $user = null;
    private static ?array $perms = null;
    private static ?array $role = null;
    private static ?array $assignedHorseIds = null;

    public static function setUser(?array $user): void
    {
        self::$user = $user;
        self::$perms = null;
        self::$role = null;
        self::$assignedHorseIds = null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user ? (int) self::$user['id'] : null;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function role(): array
    {
        if (self::$role === null) {
            self::$role = self::$user ? (DB::row('SELECT * FROM roles WHERE id = ?', [self::$user['role_id']]) ?? []) : [];
        }
        return self::$role;
    }

    public static function isOwner(): bool
    {
        return (self::role()['slug'] ?? '') === 'owner';
    }

    public static function roleSlug(): string
    {
        return (string) (self::role()['slug'] ?? '');
    }

    /** Loads role permissions plus unexpired temporary grants. */
    public static function permissions(): array
    {
        if (self::$perms !== null) {
            return self::$perms;
        }
        self::$perms = [];
        if (!self::$user) {
            return self::$perms;
        }
        $rows = DB::all('SELECT module, action FROM role_permissions WHERE role_id = ?', [self::$user['role_id']]);
        $rows = array_merge($rows, DB::all('SELECT module, action FROM user_temp_grants WHERE user_id = ? AND expires_at > NOW()', [self::$user['id']]));
        foreach ($rows as $r) {
            self::$perms[$r['module']][$r['action']] = true;
        }
        return self::$perms;
    }

    /** Actions a view-only role may still have: looking, exporting, printing (and seeing sensitive fields if ticked). */
    public const READ_ACTIONS = ['view', 'export', 'print', 'sensitive'];

    public static function can(string $module, string $action = 'view'): bool
    {
        if (!self::$user) {
            return false;
        }
        if (self::isOwner()) {
            return true;
        }
        if (self::readOnly() && !in_array($action, self::READ_ACTIONS, true)) {
            return false; // a view-only role never changes anything, whatever is ticked
        }
        return !empty(self::permissions()[$module][$action]);
    }

    /** The user's role is "view only" (the Owner role never is). */
    public static function readOnly(): bool
    {
        return self::$user !== null && !empty(self::role()['read_only']) && (self::role()['slug'] ?? '') !== 'owner';
    }

    public static function canAny(array $modules, string $action = 'view'): bool
    {
        foreach ($modules as $m) {
            if (self::can($m, $action)) {
                return true;
            }
        }
        return false;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (Request::wantsJson()) {
                Response::json(['error' => 'unauthenticated'], 401);
            }
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/portal');
            Response::redirect('/portal/login');
        }
    }

    /** Denies with 403 and writes the attempt to the activity log. */
    public static function requirePerm(string $module, string $action = 'view'): void
    {
        self::requireLogin();
        if (!self::can($module, $action)) {
            self::deny("$module.$action");
        }
    }

    public static function requireOwner(): void
    {
        self::requireLogin();
        if (!self::isOwner()) {
            self::deny('owner_only');
        }
    }

    public static function deny(string $what): never
    {
        Audit::log('access_denied', null, null, null, null, ['permission' => $what, 'path' => Request::path()], 'Access denied: ' . $what);
        Response::forbidden();
    }

    /** Groom/Stable Staff only see horses assigned to them (record-level limit). */
    public static function horseScopeAssigned(): bool
    {
        return (self::role()['horse_scope'] ?? 'all') === 'assigned' && !self::isOwner();
    }

    public static function assignedHorseIds(): array
    {
        if (self::$assignedHorseIds === null) {
            $emp = self::$user['employee_id'] ?? null;
            self::$assignedHorseIds = $emp ? array_map('intval', DB::column('SELECT horse_id FROM horse_assignments WHERE employee_id = ?', [$emp])) : [];
        }
        return self::$assignedHorseIds;
    }

    public static function canSeeHorse(int $horseId): bool
    {
        return !self::horseScopeAssigned() || in_array($horseId, self::assignedHorseIds(), true);
    }

    /**
     * Staff roles may edit only their own records within 24 hours; managers may edit any.
     * $row needs created_by and created_at.
     */
    public static function canEditRecord(string $module, array $row): bool
    {
        if (!self::can($module, 'edit') && !self::can($module, 'create')) {
            return false;
        }
        if ((self::role()['edit_window'] ?? 'any') === 'any' || self::isOwner()) {
            return self::can($module, 'edit');
        }
        $own = (int) ($row['created_by'] ?? 0) === self::id();
        $fresh = isset($row['created_at']) && strtotime((string) $row['created_at']) > time() - 86400;
        return $own && $fresh;
    }
}
