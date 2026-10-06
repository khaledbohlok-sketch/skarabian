<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Approval workflow. Requests go to Owner and General Manager (users with "approve" on the module).
 * Nobody can approve their own request. Decisions run the type's handler inside a transaction.
 */
final class Approvals
{
    /** type => [permission module, approve handler, reject handler] */
    public static function types(): array
    {
        return [
            'bill'             => ['finance', [FinanceService::class, 'approveBill'], [FinanceService::class, 'rejectBill']],
            'purchase_order'   => ['finance', [InventoryService::class, 'approvePo'], [InventoryService::class, 'rejectPo']],
            'payroll'          => ['payroll', [PayrollService::class, 'approve'], [PayrollService::class, 'reject']],
            'delete_financial' => ['finance', [TrashService::class, 'approveDelete'], null],
            'new_user'         => ['users', [UserService::class, 'approveUser'], [UserService::class, 'rejectUser']],
            'foal_website'     => ['cms', [HorseService::class, 'approveFoalWebsite'], null],
            'leave'            => ['hr', [HrService::class, 'approveLeave'], [HrService::class, 'rejectLeave']],
        ];
    }

    public static function request(string $type, string $recordType, int $recordId, string $title, ?float $amount = null, array $payload = []): int
    {
        $existing = DB::value("SELECT id FROM approvals WHERE type = ? AND record_type = ? AND record_id = ? AND status = 'pending'", [$type, $recordType, $recordId]);
        if ($existing) {
            return (int) $existing;
        }
        $id = DB::insert('approvals', [
            'type' => $type, 'record_type' => $recordType, 'record_id' => $recordId, 'title' => mb_substr($title, 0, 200),
            'amount_qar' => $amount, 'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'requested_by' => Auth::id() ?? 0,
        ]);
        Audit::log('approval_requested', self::types()[$type][0], $recordType, $recordId, null, ['approval_id' => $id, 'type' => $type, 'amount_qar' => $amount], $title);
        $body = $amount !== null ? money($amount, 'QAR', false) . ' — ' . $title : $title;
        // Owner and General Manager approve; module approvers (e.g. HR for leave) are notified too
        Notifier::permitted(self::types()[$type][0], 'approve', 'approval', __('approvals.new_request'), $body, '/portal/approvals', true, 'approval-' . $id, Auth::id());
        return $id;
    }

    public static function canDecide(array $approval): bool
    {
        if (!Auth::check() || $approval['status'] !== 'pending') {
            return false;
        }
        if ((int) $approval['requested_by'] === Auth::id()) {
            return false; // no one approves their own entry
        }
        $module = self::types()[$approval['type']][0] ?? null;
        if ($module === null) {
            return false;
        }
        // Foals are shown in "Latest Foals" only after the Owner approves
        if ($approval['type'] === 'foal_website') {
            return Auth::isOwner();
        }
        // Money-related approvals are reserved to Owner and General Manager
        if (in_array($approval['type'], ['bill', 'payroll', 'delete_financial', 'new_user', 'purchase_order'], true)
            && !in_array(Auth::roleSlug(), ['owner', 'general_manager'], true)) {
            return false;
        }
        return Auth::can($module, 'approve');
    }

    public static function decide(int $id, bool $approve, ?string $note = null): void
    {
        DB::transaction(function () use ($id, $approve, $note) {
            $a = DB::row('SELECT * FROM approvals WHERE id = ? FOR UPDATE', [$id]);
            if (!$a || !self::canDecide($a)) {
                throw new \DomainException(__('approvals.cannot_decide'));
            }
            DB::update('approvals', [
                'status' => $approve ? 'approved' : 'rejected', 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'),
                'decision_note' => $note !== null ? mb_substr($note, 0, 500) : null,
            ], 'id = :id', ['id' => $id]);
            [$module, $onApprove, $onReject] = self::types()[$a['type']];
            $payload = $a['payload'] ? json_decode($a['payload'], true) : [];
            if ($approve && $onApprove) {
                call_user_func($onApprove, (int) $a['record_id'], $payload, $a);
            } elseif (!$approve && $onReject) {
                call_user_func($onReject, (int) $a['record_id'], $payload, $a);
            }
            Audit::log($approve ? 'approve' : 'reject', $module, $a['record_type'], $a['record_id'], ['status' => 'pending'], ['status' => $approve ? 'approved' : 'rejected', 'note' => $note], $a['title']);
            Notifier::user((int) $a['requested_by'], 'approval', __($approve ? 'approvals.was_approved' : 'approvals.was_rejected'), $a['title'] . ($note ? ' — ' . $note : ''), '/portal/approvals?status=all');
        });
        Cache::bump();
    }

    public static function pendingFor(): array
    {
        $rows = DB::all("SELECT a.*, u.name AS requester FROM approvals a LEFT JOIN users u ON u.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.requested_at ASC LIMIT 200");
        return array_values(array_filter($rows, fn ($a) => self::canDecide($a)));
    }

    public static function cancelFor(string $recordType, int $recordId): void
    {
        DB::run("UPDATE approvals SET status = 'cancelled', decided_at = NOW(), decided_by = ? WHERE record_type = ? AND record_id = ? AND status = 'pending'", [Auth::id(), $recordType, $recordId]);
    }
}
