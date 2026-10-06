<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Resources\Registry;
use App\Services\Cache;
use App\Services\HorseService;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\QrCode;

/** Horse actions beyond plain editing. Every action checks permissions on the server. */
class HorsesController extends Controller
{
    private function horse(string $id, string $module = 'horses', string $action = 'view'): array
    {
        Auth::requirePerm($module, $action);
        $h = DB::row('SELECT * FROM horses WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$h) {
            Response::notFound();
        }
        if (!Auth::canSeeHorse((int) $h['id'])) {
            Auth::deny('horses.scope');
        }
        return $h;
    }

    /** Printable QR sticker for the stable door. Scanning opens the horse's portal page (after login). */
    public function qr(string $id): void
    {
        $h = $this->horse($id, 'horses', 'print');
        Audit::log('print', 'horses', 'horse', $h['id'], null, null, 'QR sticker');
        $this->view('portal/horses/qr', ['title' => __('horses.qr_sticker'), 'horses' => [$h]], 'bare');
    }

    public function qrSheet(): void
    {
        Auth::requirePerm('horses', 'print');
        $params = [];
        $where = "deleted_at IS NULL AND is_external = 0 AND status IN ('active','in_shelter')";
        if (Auth::horseScopeAssigned()) {
            $where .= ' AND id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'a', $params);
        }
        $horses = DB::all("SELECT * FROM horses WHERE $where ORDER BY name_en", $params);
        Audit::log('print', 'horses', 'horse', null, null, ['count' => count($horses)], 'QR sticker sheet');
        $this->view('portal/horses/qr', ['title' => __('nav.qr_sheet'), 'horses' => $horses], 'bare');
    }

    /** Target of the stable-door QR code: a phone-friendly page with the quick actions. */
    public function scan(string $id): void
    {
        Auth::requireLogin();
        $h = $this->horse($id);
        $this->redirect('/portal/horses/' . $h['id'] . (Auth::can('horse_diet', 'create') ? '?tab=diet' : ''));
    }

    public function pedigree(string $id): void
    {
        $h = $this->horse($id);
        $this->redirect('/portal/studio/new/profile?record_id=' . $h['id']);
    }

    /** One tap "fed" from the diet plan: logs the planned feed, stock goes down, cost added to the horse. */
    public function quickFeed(string $id): void
    {
        $h = $this->horse($id, 'horse_diet', 'create');
        $plan = DB::row('SELECT * FROM diet_plans WHERE id = ? AND horse_id = ? AND deleted_at IS NULL', [(int) Request::post('plan_id'), $h['id']]);
        if (!$plan) {
            Response::notFound();
        }
        try {
            DB::transaction(function () use ($h, $plan) {
                $logId = DB::insert('diet_logs', [
                    'horse_id' => $h['id'], 'log_date' => date('Y-m-d'), 'feeding' => $plan['feeding'], 'item_id' => $plan['item_id'],
                    'quantity' => $plan['quantity'], 'notes' => $plan['notes'], 'created_by' => Auth::id(),
                ]);
                InventoryService::syncUsage('diet', $logId, (int) $h['id'], (int) $plan['item_id'], (float) $plan['quantity'], date('Y-m-d'));
                Audit::log('create', 'horse_diet', 'diet_log', $logId, null, ['horse_id' => $h['id'], 'item_id' => $plan['item_id'], 'quantity' => $plan['quantity']], 'Feeding logged');
            });
            $this->flash('success', __('diet.logged'));
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        Cache::bump();
        $this->redirect('/portal/horses/' . $h['id'] . '?tab=diet');
    }

    public function assign(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        $emp = (int) Request::post('employee_id');
        if (!DB::value('SELECT 1 FROM employees WHERE id = ? AND deleted_at IS NULL', [$emp])) {
            $this->flash('error', __('validation.pick_from_list'));
            $this->back();
        }
        DB::run('INSERT IGNORE INTO horse_assignments (horse_id, employee_id) VALUES (?, ?)', [$h['id'], $emp]);
        Audit::log('update', 'horses', 'horse', $h['id'], null, ['assigned_employee' => $emp], 'Staff assigned');
        $this->flash('success', __('common.saved'));
        $this->back();
    }

    public function unassign(string $id, string $empId): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        DB::run('DELETE FROM horse_assignments WHERE horse_id = ? AND employee_id = ?', [$h['id'], (int) $empId]);
        Audit::log('update', 'horses', 'horse', $h['id'], ['assigned_employee' => (int) $empId], null, 'Staff unassigned');
        $this->back();
    }

    public function requestWebsite(string $id): void
    {
        $h = $this->horse($id, 'horses', 'edit');
        HorseService::requestWebsite((int) $h['id']);
        $this->flash('success', __(Auth::isOwner() ? 'common.saved' : 'approvals.sent'));
        $this->back();
    }

    public function recalc(): void
    {
        Auth::requirePerm('horses', 'edit');
        $n = HorseService::recalcCategories();
        $this->flash('success', __('horses.recalculated', ['n' => $n]));
        $this->redirect('/portal/horses');
    }

    /** Record a foaling from the mare (optionally a specific breeding record). */
    public function foaling(string $id): void
    {
        $mare = $this->horse($id, 'horse_breeding', 'create');
        if ($mare['sex'] !== 'female') {
            Response::notFound();
        }
        $records = DB::all("SELECT b.*, s.name_en AS stallion, e.code FROM breeding_records b LEFT JOIN horses s ON s.id = b.stallion_id LEFT JOIN embryos e ON e.id = b.embryo_id
            WHERE b.mare_id = ? AND b.status IN ('open','pregnant') AND b.deleted_at IS NULL ORDER BY b.start_date DESC", [$mare['id']]);
        if (Request::isPost()) {
            $this->saveFoaling(['breeding_record_id' => (int) Request::post('breeding_record_id') ?: null, 'mare_id' => (int) $mare['id'], 'stallion_id' => (int) Request::post('stallion_id') ?: null], '/portal/horses/' . $mare['id'] . '/foaling');
        }
        $this->view('portal/horses/foaling', ['title' => __('horses.record_foaling') . ': ' . $mare['name_en'], 'mare' => $mare, 'records' => $records, 'embryo' => null]);
    }

    /** Record a foaling from an embryo (the foal's dam is the donor mare, not the recipient). */
    public function embryoFoaling(string $id): void
    {
        Auth::requirePerm('embryos', 'edit');
        $e = DB::row('SELECT * FROM embryos WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$e) {
            Response::notFound();
        }
        if (Request::isPost()) {
            $this->saveFoaling(['embryo_id' => (int) $e['id']], '/portal/embryos/' . $e['id'] . '/foaling');
        }
        $mare = DB::row('SELECT * FROM horses WHERE id = ?', [$e['recipient_mare_id'] ?: $e['donor_mare_id']]);
        $this->view('portal/horses/foaling', ['title' => __('horses.record_foaling') . ': ' . $e['code'], 'mare' => $mare, 'records' => [], 'embryo' => $e]);
    }

    private function saveFoaling(array $source, string $back): never
    {
        $dob = (string) Request::post('dob');
        $sex = (string) Request::post('sex');
        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || $dob > date('Y-m-d')) {
            $errors['dob'] = __('validation.date');
        }
        if (!in_array($sex, ['male', 'female'], true)) {
            $errors['sex'] = __('validation.required');
        }
        $colorId = (int) Request::post('color_id') ?: null;
        if ($colorId && !DB::value("SELECT 1 FROM lookups WHERE id = ? AND type = 'color'", [$colorId])) {
            $colorId = null;
        }
        $locId = (int) Request::post('location_id') ?: null;
        if ($locId && !DB::value("SELECT 1 FROM lookups WHERE id = ? AND type = 'location'", [$locId])) {
            $locId = null;
        }
        if ($errors) {
            Session::errors($errors);
            Session::keepOld($_POST);
            $this->redirect($back);
        }
        try {
            $id = HorseService::recordFoaling($source, [
                'name_en' => mb_substr((string) Request::post('name_en'), 0, 120), 'name_ar' => mb_substr((string) Request::post('name_ar'), 0, 120) ?: null,
                'dob' => $dob, 'sex' => $sex, 'color_id' => $colorId, 'location_id' => $locId, 'notes' => mb_substr((string) Request::post('notes'), 0, 2000) ?: null,
                'website' => (bool) Request::post('website'),
            ]);
        } catch (\DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($back);
        }
        $this->flash('success', __('horses.foal_created'));
        $this->redirect('/portal/horses/' . $id);
    }

    public function addCheck(string $id): void
    {
        Auth::requirePerm('horse_breeding', 'create');
        $br = DB::row('SELECT * FROM breeding_records WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$br || !Auth::canSeeHorse((int) $br['mare_id'])) {
            Response::notFound();
        }
        $date = (string) Request::post('check_date');
        $result = (string) Request::post('result');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($result, ['scheduled', 'positive', 'negative', 'inconclusive'], true)) {
            $this->flash('error', __('validation.fix_errors'));
            $this->back();
        }
        DB::transaction(function () use ($br, $date, $result) {
            $cid = DB::insert('pregnancy_checks', [
                'breeding_record_id' => $br['id'], 'check_date' => $date, 'result' => $result,
                'days_pregnant' => Request::post('days_pregnant') !== '' ? max(0, min(400, (int) Request::post('days_pregnant'))) : null,
                'notes' => mb_substr((string) Request::post('notes'), 0, 255) ?: null, 'created_by' => Auth::id(),
            ]);
            // Result updates the pregnancy status (and the embryo, if any)
            $status = match ($result) { 'positive' => 'pregnant', 'negative' => 'not_pregnant', default => null };
            if ($status) {
                DB::update('breeding_records', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $br['id']]);
                if ($br['embryo_id']) {
                    DB::update('embryos', ['status' => $status === 'pregnant' ? 'pregnant' : 'failed'], "id = :id AND status IN ('transferred','pregnant')", ['id' => $br['embryo_id']]);
                }
            }
            Audit::log('create', 'horse_breeding', 'breeding', $br['id'], null, ['check_id' => $cid, 'result' => $result, 'date' => $date], 'Pregnancy check');
        });
        Cache::bump();
        $this->flash('success', __('common.saved'));
        $this->redirect('/portal/breeding-records/' . $br['id']);
    }
}
