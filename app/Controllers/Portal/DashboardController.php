<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;
use App\Services\Migrator;
use App\Services\Notifier;
use App\Services\StudioDocs;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        if (Auth::horseScopeAssigned() && !Auth::can('finance') && !Auth::can('hr')) {
            $this->redirect('/portal/my-horses'); // grooms get the simple mobile view
        }
        $this->view('portal/dashboard/index', [
            'title' => __('nav.dashboard'),
            'approvals' => (in_array(Auth::roleSlug(), ['owner', 'general_manager'], true) || Auth::canAny(['hr', 'cms'], 'approve')) ? Approvals::pendingFor() : [],
            'pendingDb' => Auth::isOwner() ? Migrator::pendingCount() : 0, // an uploaded update still waiting for Settings → Apply database update
            'activity' => self::recentActivity(),
        ]);
    }

    /**
     * The "Recent activity" card (read only): only for users who may open the Activity log, scoped exactly as that page is
     * (the Owner sees everything; others only the login and security events, the same list AdminController uses).
     */
    private static function recentActivity(): ?array
    {
        if (!Auth::can('activity', 'view')) {
            return null;
        }
        $where = '1=1';
        $p = [];
        if (!Auth::isOwner()) {
            $security = (new \ReflectionClassConstant(AdminController::class, 'SECURITY_ACTIONS'))->getValue();
            $where = 'a.action IN ' . DB::in($security, 'sa', $p);
        }
        return DB::all("SELECT a.id, a.created_at, a.user_name, a.action, a.module, a.summary FROM activity_log a WHERE $where ORDER BY a.id DESC LIMIT 12", $p);
    }

    /** Groom / stable staff: assigned horses, big buttons for feeding, notes and photos. */
    public function myHorses(): void
    {
        Auth::requirePerm('horses');
        $params = [];
        $where = "h.deleted_at IS NULL AND h.status IN ('active','in_shelter')";
        if (Auth::horseScopeAssigned()) {
            $where .= ' AND h.id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'a', $params);
        } else {
            $where .= ' AND h.is_external = 0';
        }
        $horses = DB::all("SELECT h.*, l.value_en AS loc_en, l.value_ar AS loc_ar FROM horses h LEFT JOIN lookups l ON l.id = h.location_id WHERE $where ORDER BY h.name_en LIMIT 200", $params);
        $this->view('portal/dashboard/my_horses', ['title' => __('nav.my_horses'), 'horses' => $horses]);
    }

    /** One search bar for horses, embryos, employees, bills, suppliers/clients and documents (only what the user may see). */
    public function search(): void
    {
        Auth::requireLogin();
        $q = trim((string) Request::query('q', ''));
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            if (Auth::can('horses')) {
                $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
                $scope = '';
                if (Auth::horseScopeAssigned()) {
                    $scope = ' AND id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'a', $params);
                }
                $groups['nav.horses'] = array_map(fn ($r) => ['/portal/horses/' . $r['id'], $r['name_en'] . ($r['name_ar'] ? ' / ' . $r['name_ar'] : ''), __('horse.cat_' . $r['category'])],
                    DB::all("SELECT id, name_en, name_ar, category FROM horses WHERE deleted_at IS NULL AND (name_en LIKE :q1 OR name_ar LIKE :q2 OR microchip LIKE :q3 OR registration_no LIKE :q4) $scope ORDER BY name_en LIMIT 15", $params));
            }
            if (Auth::can('embryos')) {
                $groups['nav.embryos'] = array_map(fn ($r) => ['/portal/embryos/' . $r['id'], $r['code'] . ($r['name'] ? ' — ' . $r['name'] : ''), __('embryos.status_' . $r['status'])],
                    DB::all('SELECT id, code, name, status FROM embryos WHERE deleted_at IS NULL AND (code LIKE ? OR name LIKE ?) LIMIT 10', [$like, $like]));
            }
            if (Auth::can('hr')) {
                $groups['nav.employees'] = array_map(fn ($r) => ['/portal/employees/' . $r['id'], $r['name_en'] . ($r['name_ar'] ? ' / ' . $r['name_ar'] : ''), (string) $r['emp_no']],
                    DB::all('SELECT id, name_en, name_ar, emp_no FROM employees WHERE deleted_at IS NULL AND (name_en LIKE ? OR name_ar LIKE ? OR emp_no LIKE ?) LIMIT 10', [$like, $like, $like]));
            }
            if (Auth::can('finance')) {
                $groups['nav.bills'] = array_map(fn ($r) => ['/portal/bills/' . $r['id'], $r['number'] . ' — ' . $r['description'], money($r['amount_qar'], 'QAR', false)],
                    DB::all('SELECT id, number, description, amount_qar FROM bills WHERE deleted_at IS NULL AND (number LIKE ? OR description LIKE ? OR reference_no LIKE ?) ORDER BY id DESC LIMIT 10', [$like, $like, $like]));
                $groups['nav.parties'] = array_map(fn ($r) => ['/portal/parties/' . $r['id'], loc($r), __('parties.type_' . $r['type'])],
                    DB::all('SELECT id, name_en, name_ar, type FROM parties WHERE deleted_at IS NULL AND (name_en LIKE ? OR name_ar LIKE ?) LIMIT 10', [$like, $like]));
            }
            if (Auth::can('studio')) {
                // as on the Studio page: only types whose module the user may see (salary papers need "sensitive"), drafts only to their author
                $params = ['q1' => $like, 'q2' => $like];
                $types = array_keys(array_filter(StudioDocs::TYPES, fn ($d, $t) => Auth::isOwner() || (Auth::can($d['module'], 'view')
                    && (!in_array($t, StudioDocs::SENSITIVE, true) || Auth::can($d['module'], 'sensitive'))), ARRAY_FILTER_USE_BOTH));
                $scope = ' AND doc_type IN ' . DB::in($types ?: ['-'], 'v', $params);
                if (!Auth::isOwner()) {
                    $scope .= " AND (status <> 'draft' OR created_by = :me)";
                    $params['me'] = Auth::id();
                }
                $groups['nav.studio'] = array_map(fn ($r) => ['/portal/studio/' . $r['id'], $r['ref_no'] . ' — ' . $r['title'], fmt_date($r['doc_date'])],
                    DB::all("SELECT id, ref_no, title, doc_date FROM documents WHERE deleted_at IS NULL AND (ref_no LIKE :q1 OR title LIKE :q2) $scope ORDER BY id DESC LIMIT 10", $params));
            }
            if (Auth::can('inventory')) {
                $groups['nav.items'] = array_map(fn ($r) => ['/portal/items/' . $r['id'], loc($r), rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . lookup_label('unit', $r['unit'])],
                    DB::all('SELECT id, name_en, name_ar, quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND (name_en LIKE ? OR name_ar LIKE ? OR sku LIKE ?) LIMIT 10', [$like, $like, $like]));
            }
            if (Auth::can('documents')) {
                $groups['nav.documents'] = array_map(fn ($r) => ['/portal/documents/' . $r['id'], $r['title'], fmt_date($r['doc_date'] ?: $r['created_at'])],
                    DB::all('SELECT id, title, doc_date, created_at FROM media_documents WHERE deleted_at IS NULL AND (title LIKE ? OR notes LIKE ?) ORDER BY id DESC LIMIT 10', [$like, $like]));
            }
            $groups = array_filter($groups);
            // A single exact hit opens directly (handy when scanning or typing a bill number)
            $all = array_merge(...array_values($groups ?: [[]]));
            if (count($all) === 1 && Request::query('go') !== '0') {
                $this->redirect($all[0][0]);
            }
        }
        $this->view('portal/dashboard/search', ['title' => __('common.search'), 'q' => $q, 'groups' => $groups]);
    }

    public function notifications(): void
    {
        Auth::requireLogin();
        // Shown in the reader's language: rows saved with a language key are translated now, older rows keep their text
        $rows = array_map([Notifier::class, 'localize'], DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [Auth::id()]));
        DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [Auth::id()]);
        $this->view('portal/dashboard/notifications', ['title' => __('nav.notifications'), 'rows' => $rows]);
    }

    public function markRead(): void
    {
        Auth::requireLogin();
        DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [Auth::id()]);
        $this->back();
    }

    public function notificationsJson(): void
    {
        Auth::requireLogin();
        Response::json(['unread' => (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [Auth::id()])]);
    }
}
