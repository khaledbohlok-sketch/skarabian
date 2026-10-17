<div class="approval-card">
  <div>
    <strong><a href="<?= e(url('/portal/approvals/' . $a['id'])) ?>"><?= e($a['title']) ?></a></strong>
    <div class="meta"><?= e(__('approvals.type_' . $a['type'])) ?> · <?= e(__('approvals.requested_by')) ?> <?= e($a['requester'] ?? '—') ?> · <time datetime="<?= e(substr((string) $a['requested_at'], 0, 10)) ?>"><?= e(fmt_date($a['requested_at'])) ?></time></div>
    <?php if ($a['amount_qar'] !== null): ?><div class="amount"><?= money($a['amount_qar']) ?></div><?php endif; ?>
    <?php $link = \App\Services\Approvals::recordUrl($a); ?>
    <?php if ($link): ?><a class="small" href="<?= e(url($link)) ?>"><?= e(__('approvals.open_record')) ?></a><?php endif; ?>
  </div>
  <div class="approval-buttons">
    <form method="post" action="<?= e(url('/portal/approvals/' . $a['id'] . '/approve')) ?>" data-ajax-approval><?= csrf_field() ?><button class="btn btn-success" type="submit"><?= icon('check') ?> <?= e(__('common.approve')) ?></button></form>
    <form method="post" action="<?= e(url('/portal/approvals/' . $a['id'] . '/reject')) ?>" data-ajax-approval data-confirm="<?= e(__('approvals.confirm_reject')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('x') ?> <?= e(__('common.reject')) ?></button></form>
  </div>
</div>
