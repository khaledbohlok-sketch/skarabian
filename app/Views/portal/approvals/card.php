<div class="approval-card">
  <div>
    <strong><?= e($a['title']) ?></strong>
    <div class="meta"><?= e(__('approvals.type_' . $a['type'])) ?> · <?= e(__('approvals.requested_by')) ?> <?= e($a['requester'] ?? '—') ?> · <?= e(fmt_date($a['requested_at'], true)) ?></div>
    <?php if ($a['amount_qar'] !== null): ?><div class="amount"><?= money($a['amount_qar']) ?></div><?php endif; ?>
    <?php $link = match ($a['record_type']) { 'bill' => '/portal/bills/' . $a['record_id'], 'payroll' => '/portal/payroll/' . $a['record_id'], 'horse' => '/portal/horses/' . $a['record_id'], 'purchase_order' => '/portal/purchase-orders/' . $a['record_id'], 'user' => '/portal/users/' . $a['record_id'], 'leave_request' => '/portal/leave-requests/' . $a['record_id'], 'ownership' => '/portal/horses/' . \App\Core\DB::value('SELECT horse_id FROM ownership_history WHERE id = ?', [$a['record_id']]) . '?tab=ownership', default => null }; ?>
    <?php if ($link): ?><a class="small" href="<?= e(url($link)) ?>"><?= e(__('approvals.open_record')) ?></a><?php endif; ?>
  </div>
  <div class="approval-buttons">
    <form method="post" action="<?= e(url('/portal/approvals/' . $a['id'] . '/approve')) ?>" data-ajax-approval><?= csrf_field() ?><button class="btn btn-success" type="submit">✓ <?= e(__('common.approve')) ?></button></form>
    <form method="post" action="<?= e(url('/portal/approvals/' . $a['id'] . '/reject')) ?>" data-ajax-approval data-confirm="<?= e(__('approvals.confirm_reject')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit">✕ <?= e(__('common.reject')) ?></button></form>
  </div>
</div>
