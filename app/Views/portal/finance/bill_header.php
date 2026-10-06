<?php
use App\Core\DB;
use App\Services\FinanceService;
$left = FinanceService::remaining($b);
$ap = $b['status'] === 'pending' ? DB::row("SELECT a.*, u.name AS requester FROM approvals a LEFT JOIN users u ON u.id = a.requested_by WHERE a.type = 'bill' AND a.record_id = ? AND a.status = 'pending'", [$b['id']]) : null;
$last = in_array($b['status'], ['draft'], true) ? DB::row("SELECT a.*, u.name AS decider FROM approvals a LEFT JOIN users u ON u.id = a.decided_by WHERE a.type = 'bill' AND a.record_id = ? AND a.status = 'rejected' ORDER BY a.id DESC LIMIT 1", [$b['id']]) : null;
?>
<div class="stat-grid">
  <div class="stat dark"><div class="k"><?= e(__('bills.type_' . $b['type'])) ?> · <?= e(__('bills.amount_qar')) ?></div><div class="v"><?= money($b['amount_qar']) ?></div>
    <div class="s"><?php if ($b['currency'] !== 'QAR'): ?><?= e(number_format((float) $b['amount_original'], 2) . ' ' . $b['currency'] . ' × ' . rtrim(rtrim((string) $b['exchange_rate'], '0'), '.')) ?><?php else: ?><?= e(fmt_date($b['bill_date'])) ?><?php endif; ?></div></div>
  <div class="stat"><div class="k"><?= e(__('bills.paid')) ?></div><div class="v"><?= money($b['paid_qar']) ?></div></div>
  <div class="stat<?= $b['status'] === 'overdue' ? ' alert-bad' : '' ?>"><div class="k"><?= e(__('bills.remaining')) ?></div><div class="v"><?= money($b['status'] === 'cancelled' ? 0 : $left) ?></div>
    <div class="s"><?= $b['due_date'] ? e(__('bills.due_date')) . ' ' . e(fmt_date($b['due_date'])) : '' ?></div></div>
  <div class="stat"><div class="k"><?= e(__('common.status')) ?></div><div class="v" style="font-size:18px"><?= status_badge($b['status']) ?></div>
    <div class="s"><?php if ($ap): ?><?= e(__('bills.waiting_approval', ['name' => $ap['requester'] ?? '—'])) ?><?php elseif ($b['approved_at']): ?><?= e(__('bills.approved_on', ['date' => fmt_date($b['approved_at'])])) ?><?php endif; ?></div></div>
</div>
<?php if ($last): ?><div class="alert alert-error"><?= e(__('bills.was_rejected', ['name' => $last['decider'] ?? '—'])) ?><?= $last['decision_note'] ? ' — ' . e($last['decision_note']) : '' ?></div><?php endif; ?>
<?php if ($b['status'] === 'pending'): ?><div class="alert alert-info"><?= e(__('bills.pending_note', ['limit' => money(FinanceService::limit(), 'QAR', false)])) ?></div><?php endif; ?>
