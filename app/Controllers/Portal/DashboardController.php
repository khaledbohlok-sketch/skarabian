<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;

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
        ]);
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
                $groups['nav.parties'] = array_map(fn ($r) => ['/portal/parties/' . $r['id'], $r['name_en'], __('parties.type_' . $r['type'])],
                    DB::all('SELECT id, name_en, type FROM parties WHERE deleted_at IS NULL AND (name_en LIKE ? OR name_ar LIKE ?) LIMIT 10', [$like, $like]));
            }
            if (Auth::can('studio')) {
                $groups['nav.studio'] = array_map(fn ($r) => ['/portal/studio/' . $r['id'], $r['ref_no'] . ' — ' . $r['title'], fmt_date($r['doc_date'])],
                    DB::all('SELECT id, ref_no, title, doc_date FROM documents WHERE deleted_at IS NULL AND (ref_no LIKE ? OR title LIKE ?) ORDER BY id DESC LIMIT 10', [$like, $like]));
            }
            if (Auth::can('inventory')) {
                $groups['nav.items'] = array_map(fn ($r) => ['/portal/items/' . $r['id'], $r['name_en'], rtrim(rtrim((string) $r['quantity'], '0'), '.') . ' ' . $r['unit']],
                    DB::all('SELECT id, name_en, quantity, unit FROM inventory_items WHERE deleted_at IS NULL AND (name_en LIKE ? OR name_ar LIKE ? OR sku LIKE ?) LIMIT 10', [$like, $like, $like]));
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
        $rows = DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [Auth::id()]);
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
