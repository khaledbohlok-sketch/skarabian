<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\Cache;
use App\Services\FinanceService;
use App\Services\LeaveService;
use App\Services\NumberRegister;
use App\Services\Offsite;
use App\Services\PayrollService;
use App\Services\Settings;

/**
 * Owner's system check (Phase 1 of the 8 Oct 2026 review): what each fix finds in the live data, and every data fix
 * as a preview first. Nothing changes until the Owner presses its button; each fix is written to the activity log.
 */
class ChecksController extends Controller
{
    private function owner(): void
    {
        Auth::requireLogin();
        if (!Auth::isOwner()) {
            Auth::deny('owner_only');
        }
    }

    public function index(): void
    {
        $this->owner();
        if (Request::isPost()) {
            $this->apply((string) Request::post('fix'));
            $this->redirect('/portal/settings/checks');
        }
        $this->view('portal/admin/checks', ['title' => __('nav.checks')] + self::findings());
    }

    /** Everything the page shows (also used by the tests). */
    public static function findings(): array
    {
        $ready = DB::hasColumn('roles', 'read_only'); // database update 020 applied
        // 1. deletions whose label carries a document number: what kind of record each one really was
        $deleted = DB::all("SELECT record_type, label, deleted_at FROM trash WHERE record_type IN ('bill','bill_payment','invoice','purchase_order') ORDER BY deleted_at DESC LIMIT 50");
        // 6. payroll runs sent or approved for a month that has not started
        $future = DB::all("SELECT r.*, (SELECT COUNT(*) FROM payroll_lines l WHERE l.payroll_run_id = r.id) AS employees FROM payroll_runs r
            WHERE r.deleted_at IS NULL AND r.status <> 'draft' AND r.period > ? ORDER BY r.period", [date('Y-m')]);
        // 5. bills without a due date: the date the payment terms give them
        $due = FinanceService::dueSql('b', 'p');
        $noDue = DB::all("SELECT b.id, b.number, b.bill_date, b.type, b.amount_qar - b.paid_qar AS open, p.name_en AS party, $due AS due
            FROM bills b LEFT JOIN parties p ON p.id = b.party_id WHERE b.deleted_at IS NULL AND b.due_date IS NULL AND b.status <> 'cancelled' ORDER BY b.bill_date, b.id LIMIT 300");
        // 4. leave balances: today's figure and the calculated one
        $leave = [];
        if ($ready) {
            foreach (DB::all("SELECT * FROM employees WHERE deleted_at IS NULL AND status <> 'left' ORDER BY name_en") as $e) {
                [$open, $since] = LeaveService::calculated() && $e['leave_opening_date'] ? [(float) $e['leave_opening_balance'], (string) $e['leave_opening_date']] : LeaveService::proposedOpening($e);
                $b = LeaveService::balance(['leave_opening_balance' => $open, 'leave_opening_date' => $since] + $e);
                $earned = LeaveService::earned((float) $e['annual_leave_days'], $since, date('Y-m-d'), $e['hire_date']);
                $used = (float) DB::value("SELECT COALESCE(SUM(days), 0) FROM leave_requests WHERE employee_id = ? AND leave_type = 'annual' AND status = 'approved' AND deleted_at IS NULL AND start_date >= ?", [$e['id'], $since]);
                $leave[] = ['id' => (int) $e['id'], 'name' => $e['name_en'], 'per_year' => (float) $e['annual_leave_days'], 'typed' => (float) $e['leave_balance'],
                            'open' => $open, 'since' => $since, 'earned' => round($earned, 1), 'used' => $used, 'new' => round($open + $earned - $used, 1), 'mode' => $b['mode']];
            }
        }
        // 8. roles: misspelled names, view-only roles not marked as such, country rules (now enforced)
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS users FROM roles r ORDER BY r.id');
        $perms = [];
        foreach (DB::all('SELECT role_id, action FROM role_permissions') as $p) {
            $perms[(int) $p['role_id']][$p['action']] = true;
        }
        foreach ($roles as &$r) {
            $acts = array_keys($perms[(int) $r['id']] ?? []);
            $r['view_only_perms'] = $r['slug'] !== 'owner' && $acts && !array_diff($acts, Auth::READ_ACTIONS);
            $r['rename'] = preg_match('/veiw|vew\b|veiew/i', (string) $r['name_en']) ? preg_replace('/\s*v[ei]+e?w\b/i', '', (string) $r['name_en']) . ' (view only)' : null;
        }
        unset($r);
        // 9. users without the 2FA their role requires
        $no2fa = DB::all("SELECT u.id, u.name, u.created_at, u.twofa_method, " . (DB::hasColumn('roles', 'twofa_since') ? 'r.twofa_since' : 'NULL AS twofa_since') . " FROM users u JOIN roles r ON r.id = u.role_id
            WHERE r.require_2fa = 1 AND u.twofa_method = 'none' AND u.status = 'active' AND u.deleted_at IS NULL");
        $blocked = count(array_filter($no2fa, fn ($u) => AuthService::twofaGraceUntil($u, ['require_2fa' => 1, 'twofa_since' => $u['twofa_since']]) === null));
        return [
            'ready' => $ready, 'deleted' => $deleted, 'duplicates' => NumberRegister::duplicates(), 'future' => $future, 'noDue' => $noDue, 'leave' => $leave,
            'leaveMode' => LeaveService::calculated() ? 'calculated' : 'typed', 'roles' => $roles, 'no2fa' => count($no2fa), 'blocked2fa' => $blocked,
            'offsite' => ['dest' => Offsite::destination(), 'last' => Offsite::lastOk(), 'late' => Offsite::late()],
            'ownerSecond' => Settings::get('approval.owner_second', '0') === '1', 'owners' => (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL"),
        ];
    }

    /** One data fix, chosen on the page. */
    private function apply(string $fix): void
    {
        try {
            match ($fix) {
                'payroll_draft' => $this->payrollToDraft((int) Request::post('id')),
                'fill_due' => $this->fillDueDates(),
                'leave_calculated' => $this->leaveCalculated(),
                'leave_typed' => $this->leaveTyped(),
                'role_rename' => $this->roleRename((int) Request::post('id')),
                'role_view_only' => $this->roleViewOnly((int) Request::post('id')),
                default => Response::notFound(),
            };
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
    }

    private function payrollToDraft(int $id): void
    {
        $run = DB::row('SELECT * FROM payroll_runs WHERE id = ? AND deleted_at IS NULL', [$id]) ?? Response::notFound();
        if (PayrollService::started($run['period'])) {
            throw new \DomainException(__('checks.payroll_started'));
        }
        PayrollService::backToDraft($id);
        $this->flash('success', __('checks.payroll_drafted', ['period' => $run['period']]));
    }

    /** Bills without a due date get the one their payment terms give them (the same date the reports already use). */
    private function fillDueDates(): void
    {
        $due = FinanceService::dueSql('b', 'p');
        $rows = DB::all("SELECT b.id, b.number, $due AS due FROM bills b LEFT JOIN parties p ON p.id = b.party_id
            WHERE b.deleted_at IS NULL AND b.due_date IS NULL AND b.status <> 'cancelled'");
        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                DB::update('bills', ['due_date' => $r['due']], 'id = :id AND due_date IS NULL', ['id' => $r['id']]);
            }
            Audit::log('data_fix', 'finance', 'bill', null, null, ['due_dates_filled' => count($rows), 'bills' => array_column($rows, 'number')], 'Due dates filled from payment terms: ' . count($rows) . ' bills');
        });
        FinanceService::refreshOverdue();
        Cache::bump();
        $this->flash('success', __('checks.due_filled', ['n' => count($rows)]));
    }

    /** Leave balances become calculated: each employee's opening balance and date as previewed (kept if already set). */
    private function leaveCalculated(): void
    {
        if (!DB::hasColumn('employees', 'leave_opening_date')) {
            throw new \DomainException(__('checks.needs_update'));
        }
        $set = [];
        DB::transaction(function () use (&$set) {
            foreach (DB::all("SELECT * FROM employees WHERE deleted_at IS NULL AND leave_opening_date IS NULL") as $e) {
                [$open, $since] = LeaveService::proposedOpening($e);
                DB::update('employees', ['leave_opening_balance' => $open, 'leave_opening_date' => $since], 'id = :id', ['id' => $e['id']]);
                $set[] = $e['name_en'] . ': ' . $open . ' @ ' . $since;
            }
            Settings::set('hr.leave_balance_mode', 'calculated');
            Audit::log('data_fix', 'hr', 'employee', null, ['leave_balance_mode' => 'typed'], ['leave_balance_mode' => 'calculated', 'opening' => $set], 'Leave balances switched to calculated');
        });
        Cache::bump();
        $this->flash('success', __('checks.leave_switched'));
    }

    /**
     * Back to typed balances: each employee's typed figure becomes what the calculation shows now (leave approved while
     * the balances were calculated never came off the typed figures, and cancelling it would add days never taken).
     */
    private function leaveTyped(): void
    {
        $set = [];
        DB::transaction(function () use (&$set) {
            if (LeaveService::calculated()) {
                foreach (DB::all('SELECT * FROM employees WHERE deleted_at IS NULL') as $e) {
                    $b = LeaveService::balance($e)['balance'];
                    DB::update('employees', ['leave_balance' => $b], 'id = :id', ['id' => $e['id']]);
                    $set[] = $e['name_en'] . ': ' . $b;
                }
            }
            Settings::set('hr.leave_balance_mode', 'typed');
            Audit::log('data_fix', 'hr', 'employee', null, ['leave_balance_mode' => 'calculated'], ['leave_balance_mode' => 'typed', 'balances' => $set], 'Leave balances switched back to typed');
        });
        Cache::bump();
        $this->flash('success', __('checks.leave_typed'));
    }

    private function roleRename(int $id): void
    {
        $role = DB::row('SELECT * FROM roles WHERE id = ?', [$id]) ?? Response::notFound();
        $new = preg_replace('/\s*v[ei]+e?w\b/i', '', (string) $role['name_en']) . ' (view only)';
        // the Arabic name gets "(view only)" too; a role without one keeps showing the English name
        DB::update('roles', ['name_en' => $new, 'name_ar' => $role['name_ar'] ? preg_replace('/\s*\(.*\)$/u', '', (string) $role['name_ar']) . ' (عرض فقط)' : $role['name_ar'],
            'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        Audit::log('update', 'users', 'role', $id, ['name_en' => $role['name_en']], ['name_en' => $new], 'Role renamed: ' . $new);
        $this->flash('success', __('checks.role_renamed', ['name' => $new]));
    }

    private function roleViewOnly(int $id): void
    {
        $role = DB::row('SELECT * FROM roles WHERE id = ?', [$id]) ?? Response::notFound();
        if ($role['slug'] === 'owner' || !DB::hasColumn('roles', 'read_only')) {
            Response::notFound();
        }
        DB::transaction(function () use ($role) {
            DB::run("DELETE FROM role_permissions WHERE role_id = ? AND action NOT IN ('view','export','print','sensitive')", [$role['id']]);
            DB::update('roles', ['read_only' => 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $role['id']]);
            Audit::log('permissions', 'users', 'role', (int) $role['id'], ['read_only' => 0], ['read_only' => 1], 'Role made view only: ' . $role['name_en']);
        });
        Cache::bump();
        $this->flash('success', __('checks.role_view_only', ['name' => $role['name_en']]));
    }
}
