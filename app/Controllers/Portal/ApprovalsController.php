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

    /** One request: where its notification (and email) leads — the details, Approve / Reject, or how it was decided. */
    public function show(string $id): void
    {
        Auth::requireLogin();
        $a = DB::row('SELECT a.*, u.name AS requester, d.name AS decider FROM approvals a LEFT JOIN users u ON u.id = a.requested_by LEFT JOIN users d ON d.id = a.decided_by WHERE a.id = ?', [(int) $id]) ?? Response::notFound();
        $canDecide = Approvals::canDecide($a);
        // Seen by those who may (or could, while it waited) decide it, and by whoever asked or decided
        if (!$canDecide && !Approvals::canDecide(['status' => 'pending'] + $a) && !in_array(Auth::id(), [(int) $a['requested_by'], (int) $a['decided_by']], true)) {
            Response::notFound();
        }
        $this->view('portal/approvals/show', ['title' => $a['title'], 'a' => $a, 'canDecide' => $canDecide, 'details' => self::details($a)]);
    }

    /** What the decision is about, so it can be made from the phone without opening the record. */
    private static function details(array $a): array
    {
        $p = $a['payload'] ? (json_decode((string) $a['payload'], true) ?: []) : [];
        $out = [];
        if ($a['type'] === 'bill') {
            $b = DB::row('SELECT b.*, p.name_en AS party_en, p.name_ar AS party_ar, emp.name_en AS emp_en, emp.name_ar AS emp_ar, c.name_en AS cat_en, c.name_ar AS cat_ar,
                    sc.name_en AS sub_en, sc.name_ar AS sub_ar, h.name_en AS horse_en, h.name_ar AS horse_ar
                FROM bills b LEFT JOIN parties p ON p.id = b.party_id LEFT JOIN employees emp ON emp.id = b.employee_id
                LEFT JOIN finance_categories c ON c.id = b.category_id LEFT JOIN finance_categories sc ON sc.id = b.subcategory_id LEFT JOIN horses h ON h.id = b.horse_id
                WHERE b.id = ?', [$a['record_id']]);
            if ($b) {
                $out = [
                    'bills.number' => $b['number'],
                    'bills.party' => loc(['name_en' => $b['party_en'] ?? $b['emp_en'], 'name_ar' => $b['party_ar'] ?? $b['emp_ar']]) ?: '—',
                    'finance.category' => trim(loc(['name_en' => $b['cat_en'], 'name_ar' => $b['cat_ar']]) . ($b['sub_en'] ? ' › ' . loc(['name_en' => $b['sub_en'], 'name_ar' => $b['sub_ar']]) : '')) ?: '—',
                    'common.description' => $b['description'] ?: '—',
                ];
                if ($b['currency'] !== 'QAR') {
                    $out['common.amount'] = money($b['amount_original'], $b['currency'], false); // the QAR value is the request's amount
                }
                $out['bills.bill_date'] = fmt_date($b['bill_date']);
                if ($b['due_date']) {
                    $out['bills.due_date'] = fmt_date($b['due_date']);
                }
                if ($b['horse_en']) {
                    $out['bills.horse'] = loc(['name_en' => $b['horse_en'], 'name_ar' => $b['horse_ar']]);
                }
                if (!empty($p['pay_account_id'])) {
                    $out['approvals.paid_from'] = (string) DB::value('SELECT name FROM accounts WHERE id = ?', [$p['pay_account_id']]);
                }
                if (!empty($b['recurring_bill_id'])) {
                    $out['recurring.singular'] = (string) DB::value('SELECT name FROM recurring_bills WHERE id = ?', [$b['recurring_bill_id']]);
                }
            }
        } elseif ($a['type'] === 'payroll') {
            $r = DB::row('SELECT r.period, r.total_net_qar, COUNT(l.id) AS n FROM payroll_runs r LEFT JOIN payroll_lines l ON l.payroll_run_id = r.id WHERE r.id = ? GROUP BY r.id, r.period, r.total_net_qar', [$a['record_id']]);
            if ($r) {
                $out = ['payroll.period' => fmt_month($r['period'] . '-01'), 'nav.employees' => (string) $r['n'], 'payroll.total_net' => money($r['total_net_qar'], 'QAR', false)];
            }
        }
        return $out;
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
