<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Approvals;

/** "Waiting for my approval" — one-tap approve / reject (works from the phone). */
class ApprovalsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $status = (string) Request::query('status', 'pending');
        if ($status === 'all') {
            $mine = DB::all('SELECT a.*, u.name AS requester, d.name AS decider FROM approvals a LEFT JOIN users u ON u.id = a.requested_by LEFT JOIN users d ON d.id = a.decided_by
                WHERE a.requested_by = ? OR a.decided_by = ? ORDER BY a.id DESC LIMIT 200', [Auth::id(), Auth::id()]);
        } else {
            $mine = DB::all("SELECT a.*, u.name AS requester FROM approvals a LEFT JOIN users u ON u.id = a.requested_by WHERE a.requested_by = ? AND a.status = 'pending' ORDER BY a.id DESC", [Auth::id()]);
        }
        $this->view('portal/approvals/index', ['title' => __('nav.approvals'), 'pending' => Approvals::pendingFor(), 'mine' => $mine, 'status' => $status]);
    }

    public function approve(string $id): void
    {
        $this->decide((int) $id, true);
    }

    public function reject(string $id): void
    {
        $this->decide((int) $id, false);
    }

    private function decide(int $id, bool $approve): never
    {
        Auth::requireLogin();
        try {
            Approvals::decide($id, $approve, mb_substr((string) Request::post('note', ''), 0, 500) ?: null);
            $label = __($approve ? 'approvals.status_approved' : 'approvals.status_rejected');
            if (Request::wantsJson()) {
                Response::json(['ok' => true, 'status' => $approve ? 'approved' : 'rejected', 'label' => $label]);
            }
            $this->flash('success', $label);
        } catch (\DomainException $e) {
            if (Request::wantsJson()) {
                Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            $this->flash('error', $e->getMessage());
        }
        $this->back('/portal/approvals');
    }
}
