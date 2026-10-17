<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Services\PrintoutService;

/** The original copy of a printout, opened from its QR code's verification page (staff only). */
class PrintoutsController extends Controller
{
    public function show(string $token): void
    {
        Auth::requireLogin();
        $p = PrintoutService::find($token) ?? Response::notFound();
        if (!PrintoutService::canOpen($p)) {
            Auth::deny('printouts.' . $p['module']);
        }
        [$doc, $intact] = PrintoutService::content($p);
        Lang::set($p['lang']); // the copy reads as it was printed
        Audit::log('view', $p['module'], 'printout', (int) $p['id'], null, ['ref' => $p['ref_no'], 'intact' => $intact], 'Printout copy opened: ' . $p['ref_no']);
        $who = ['ref' => $p['ref_no'], 'name' => $p['printed_by_name'] ?? '—', 'date' => fmt_date($p['printed_at'], true)];
        $source = $p['source_url'] ? local_path($p['source_url'], '') : '';
        $voided = !empty($p['is_void']);
        $banner = '<div class="print-banner no-print' . ($doc && $intact && !$voided ? '' : ' bad') . '">'
            . '<b>' . e(__('print.copy_title', $who)) . '</b><br>'
            . ($voided ? e(__('print.copy_void', ['date' => fmt_date((string) $p['voided_at'], true)])) . '<br>' : '')
            . e(__($doc ? ($intact ? 'print.copy_intact' : 'print.copy_changed') : 'print.copy_not_kept'))
            . ($source !== '' ? ' <a href="' . e(url($source)) . '">' . e(__('print.open_list_now')) . '</a>' : '') . '</div>';
        if (!$doc) {
            $doc = PrintoutService::doc($p['title']);
            $doc['at'] = $p['printed_at'];
            $doc['by'] = (string) ($p['printed_by_name'] ?? '');
            $doc['count'] = (int) $p['row_count'];
        }
        PrintoutService::output($doc, PrintoutService::stamp($p['ref_no'], $p['verify_token'], $p['printed_at']), ['banner' => $banner, 'docLang' => $p['lang'], 'voided' => $voided]);
    }

    /** Voids a printout (Studio → Issued documents): it stays in the archive and its QR check says it is no longer valid. */
    public function void(string $token): void
    {
        Auth::requireLogin();
        $p = PrintoutService::find($token) ?? Response::notFound();
        if (!PrintoutService::canOpen($p) || (!Auth::isOwner() && !Auth::can('studio', 'delete'))) {
            Auth::deny('studio.delete');
        }
        $back = local_path(Request::post('back'), '/portal/studio');
        if (!PrintoutService::hasVoid()) {
            $this->flash('error', __('settings.db_pending', ['n' => 1]));
            $this->redirect($back);
        }
        if ($p['is_void']) {
            $this->flash('info', __('studio.already_void'));
            $this->redirect($back);
        }
        DB::update('printouts', ['is_void' => 1, 'voided_by' => Auth::id(), 'voided_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $p['id']]);
        Audit::log('void', $p['module'], 'printout', (int) $p['id'], ['is_void' => 0], ['is_void' => 1, 'ref' => $p['ref_no']], 'Printout voided: ' . $p['ref_no']);
        $this->flash('success', __('studio.voided_ref', ['ref' => $p['ref_no']]));
        $this->redirect($back);
    }
}
