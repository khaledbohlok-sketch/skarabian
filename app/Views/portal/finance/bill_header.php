<?php
use App\Core\DB;
use App\Services\FinanceService;
$left = FinanceService::remaining($b);
$ap = $b['status'] === 'pending' ? DB::row("SELECT a.*, u.name AS requester FROM approvals a LEFT JOIN users u ON u.id = a.requested_by WHERE a.type = 'bill' AND a.record_id = ? AND a.status = 'pending'", [$b['id']]) : null;
$last = in_array($b['status'], ['draft'], true) ? DB::row("SELECT a.*, u.name AS decider FROM approvals a LEFT JOIN users u ON u.id = a.decided_by WHERE a.type = 'bill' AND a.record_id = ? AND a.status = 'rejected' ORDER BY a.id DESC LIMIT 1", [$b['id']]) : null;
// the status itself is the badge next to the bill number (page head); these figures say what is paid, what is left and by when
?>
<div class="stat-grid">
  <div class="stat dark"><div class="k"><?= e(__('bills.type_' . $b['type'])) ?> · <?= e(__('bills.amount_qar')) ?></div><div class="v"><?= money($b['amount_qar']) ?></div>
    <div class="s"><?php if ($b['currency'] !== 'QAR'): ?><span dir="ltr"><?= e(money($b['amount_original'], (string) $b['currency'], false) . ' × ' . rtrim(rtrim((string) $b['exchange_rate'], '0'), '.')) ?></span><?php else: ?><?= e(fmt_date($b['bill_date'])) ?><?php endif; ?></div></div>
  <div class="stat"><div class="k"><?= e(__('bills.paid')) ?></div><div class="v"><?= money($b['paid_qar']) ?></div>
    <div class="s"><?php if ($ap): ?><?= e(__('bills.waiting_approval', ['name' => $ap['requester'] ?? '—'])) ?><?php elseif ($b['approved_at']): ?><?= e(__('bills.approved_on', ['date' => fmt_date($b['approved_at'])])) ?><?php endif; ?></div></div>
  <div class="stat<?= $b['status'] === 'overdue' ? ' alert-bad' : '' ?>"><div class="k"><?= e(__('bills.remaining')) ?></div><div class="v"><?= money($b['status'] === 'cancelled' ? 0 : $left) ?></div></div>
  <div class="stat<?= $b['status'] === 'overdue' ? ' alert-bad' : '' ?>"><div class="k"><?= e(__('bills.due_date')) ?></div><div class="v is-text"><?= e(fmt_date(FinanceService::effectiveDue($b))) ?: '—' ?></div>
    <?php if (!$b['due_date']): ?><div class="s"><?= e(__('bills.due_by_terms')) ?></div><?php endif; ?></div>
</div>
<?php if ($last): ?><div class="alert alert-error"><?= e(__('bills.was_rejected', ['name' => $last['decider'] ?? '—'])) ?><?= $last['decision_note'] ? ' — ' . e($last['decision_note']) : '' ?></div><?php endif; ?>
<?php if ($b['status'] === 'pending'): ?><div class="alert alert-info"><?= e(__('bills.pending_note', ['limit' => money(FinanceService::limit(), 'QAR', false)])) ?><?php if ($ap): ?> <a href="<?= e(url('/portal/approvals/' . $ap['id'])) ?>"><?= e(__('bills.open_approval')) ?></a><?php endif; ?></div><?php endif; ?>
<?php if (!empty($b['recurring_bill_id']) && ($rb = DB::row('SELECT id, name FROM recurring_bills WHERE id = ? AND deleted_at IS NULL', [$b['recurring_bill_id']]))): ?>
<p class="muted small"><?= e(__('recurring.made_by')) ?> <a href="<?= e(url('/portal/recurring-bills/' . $rb['id'])) ?>"><?= e($rb['name']) ?></a></p>
<?php endif; ?>
