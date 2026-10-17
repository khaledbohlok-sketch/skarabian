<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;

/**
 * Approval workflow. Requests go to Owner and General Manager (users with "approve" on the module).
 * Nobody can approve their own request. Decisions run the type's handler inside a transaction.
 * Deletions asked for by anyone but the Owner are decided by the Owner alone.
 */
final class Approvals
{
    /** Decided by the Owner alone: deletions asked for by anyone else, and foals shown on the website. */
    private const OWNER_ONLY = ['delete', 'delete_financial', 'foal_website'];

    /** type => [permission module, approve handler, reject handler] */
    public static function types(): array
    {
        return [
            'bill'             => ['finance', [FinanceService::class, 'approveBill'], [FinanceService::class, 'rejectBill']],
            'purchase_order'   => ['finance', [InventoryService::class, 'approvePo'], [InventoryService::class, 'rejectPo']],
            'payroll'          => ['payroll', [PayrollService::class, 'approve'], [PayrollService::class, 'reject']],
            // module: the record's own module comes with each request (payload 'module')
            'delete'           => ['settings', [TrashService::class, 'approveDeletion'], null],
            'delete_financial' => ['finance', [TrashService::class, 'approveDelete'], null],
            'new_user'         => ['users', [UserService::class, 'approveUser'], [UserService::class, 'rejectUser']],
            'foal_website'     => ['cms', [HorseService::class, 'approveFoalWebsite'], null],
            'leave'            => ['hr', [HrService::class, 'approveLeave'], [HrService::class, 'rejectLeave']],
        ];
    }

    /** $requestedBy: whose request it is when no one is logged in (a bill made by the daily recurring task). */
    public static function request(string $type, string $recordType, int $recordId, string $title, ?float $amount = null, array $payload = [], ?int $requestedBy = null): int
    {
        $existing = DB::value("SELECT id FROM approvals WHERE type = ? AND record_type = ? AND record_id = ? AND status = 'pending'", [$type, $recordType, $recordId]);
        if ($existing) {
            return (int) $existing;
        }
        $requestedBy ??= Auth::id();
        $id = DB::insert('approvals', [
            'type' => $type, 'record_type' => $recordType, 'record_id' => $recordId, 'title' => mb_substr($title, 0, 200),
            'amount_qar' => $amount, 'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'requested_by' => $requestedBy ?? 0,
        ]);
        $module = $payload['module'] ?? self::types()[$type][0];
        Audit::log('approval_requested', $module, $recordType, $recordId, null, ['approval_id' => $id, 'type' => $type, 'amount_qar' => $amount], $title);
        $body = $amount !== null ? money($amount, 'QAR', false) . ' — ' . $title : $title;
        // The notification (and its email) opens this one request, with Approve and Reject on it
        $url = '/portal/approvals/' . $id;
        if (in_array($type, ['delete', 'delete_financial'], true)) {
            Notifier::owners('approval', Notifier::msg('approvals.new_request'), $body, $url, true, 'approval-' . $id);
        } else {
            // Owner and General Manager approve; module approvers (e.g. HR for leave) are notified too
            Notifier::permitted($module, 'approve', 'approval', Notifier::msg('approvals.new_request'), $body, $url, true, 'approval-' . $id, $requestedBy);
        }
        return $id;
    }

    /** Security → "The Owner's own requests need a second approver": another Owner or the General Manager decides them. */
    public static function ownerSecond(): bool
    {
        return Settings::get('approval.owner_second', '0') === '1';
    }

    /** Another active Owner (who can decide an Owner's deletion when a second approver is required). */
    public static function otherOwner(): bool
    {
        return (bool) DB::value("SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.status = 'active' AND u.deleted_at IS NULL AND u.id <> ? LIMIT 1", [Auth::id() ?? 0]);
    }

    public static function canDecide(array $approval): bool
    {
        if (!Auth::check() || $approval['status'] !== 'pending') {
            return false;
        }
        if ((int) $approval['requested_by'] === Auth::id() && (Auth::roleSlug() !== 'owner' || self::ownerSecond())) {
            return false; // no one approves their own entry, except the Owner (unless a second approver is required)
        }
        // nor their own leave, whoever entered it (the staff member's login is linked to their employee record)
        if ($approval['type'] === 'leave' && Auth::roleSlug() !== 'owner' && !empty(Auth::user()['employee_id'])
            && (int) DB::value('SELECT employee_id FROM leave_requests WHERE id = ?', [$approval['record_id']]) === (int) Auth::user()['employee_id']) {
            return false;
        }
        $module = self::types()[$approval['type']][0] ?? null;
        if ($module === null) {
            return false;
        }
        // Deletions, and foals shown in "Latest Foals", wait for the Owner
        if (in_array($approval['type'], self::OWNER_ONLY, true)) {
            return Auth::isOwner();
        }
        // Money-related approvals are reserved to Owner and General Manager
        if (in_array($approval['type'], ['bill', 'payroll', 'new_user', 'purchase_order'], true)
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
            $module = $payload['module'] ?? $module;
            if ($approve && $onApprove) {
                call_user_func($onApprove, (int) $a['record_id'], $payload, $a);
            } elseif (!$approve && $onReject) {
                call_user_func($onReject, (int) $a['record_id'], $payload, $a);
            }
            $self = (int) $a['requested_by'] === Auth::id(); // the Owner deciding their own request: shown as "self-approved"
            Audit::log($approve ? 'approve' : 'reject', $module, $a['record_type'], $a['record_id'], ['status' => 'pending'],
                ['status' => $approve ? 'approved' : 'rejected', 'note' => $note] + ($self ? ['self_approved' => true] : []), ($self ? '[self-approved] ' : '') . $a['title']);
            Notifier::user((int) $a['requested_by'], 'approval', Notifier::msg($approve ? 'approvals.was_approved' : 'approvals.was_rejected'), $a['title'] . ($note ? ' — ' . $note : ''), '/portal/approvals/' . $id);
        });
        Cache::bump();
    }

    /** The page of the record a request is about: a delete request carries the record's address, the other types are
     * found from the record type. */
    public static function recordUrl(array $a): ?string
    {
        $p = $a['payload'] ? (json_decode((string) $a['payload'], true) ?: []) : [];
        if (isset($p['url'])) {
            return local_path($p['url'], '') ?: null;
        }
        return match ($a['record_type']) {
            'bill' => '/portal/bills/' . $a['record_id'], 'payroll' => '/portal/payroll/' . $a['record_id'], 'horse' => '/portal/horses/' . $a['record_id'],
            'purchase_order' => '/portal/purchase-orders/' . $a['record_id'], 'user' => '/portal/users/' . $a['record_id'],
            'leave_request' => '/portal/leave-requests/' . $a['record_id'], default => null,
        };
    }

    public static function pendingFor(): array
    {
        $rows = DB::all("SELECT a.*, u.name AS requester FROM approvals a LEFT JOIN users u ON u.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.requested_at ASC LIMIT 200");
        return array_values(array_filter($rows, fn ($a) => self::canDecide($a)));
    }

    /** The record's pending requests are dropped: all of them (it was deleted), or only those of $type (e.g. a bill's
     * amount approval, while a request to delete it stays). */
    public static function cancelFor(string $recordType, int $recordId, ?string $type = null): void
    {
        DB::run("UPDATE approvals SET status = 'cancelled', decided_at = NOW(), decided_by = ? WHERE record_type = ? AND record_id = ? AND status = 'pending'"
            . ($type !== null ? ' AND type = ?' : ''), array_merge([Auth::id(), $recordType, $recordId], $type !== null ? [$type] : []));
    }
}
