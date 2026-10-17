<?php $link = \App\Services\Approvals::recordUrl($a); ?>
<div class="page-head"><div>
  <ol class="breadcrumbs" aria-label="<?= e(__('ui.breadcrumb')) ?>"><li><a href="<?= e(url('/portal/approvals')) ?>"><?= e(__('nav.approvals')) ?></a></li><li aria-current="page"><?= e(__('approvals.type_' . $a['type'])) ?></li></ol>
  <h1><?= e($a['title']) ?> <?= status_badge($a['status'], 'approvals.status') ?></h1>
</div>
<?php if ($link): ?><div class="actions"><a class="btn" href="<?= e(url($link)) ?>"><?= icon('external-link') ?> <?= e(__('approvals.open_record')) ?></a></div><?php endif; ?>
</div>
<section class="card">
  <dl class="dl dl-grid">
    <div><dt><?= e(__('common.type')) ?></dt><dd><?= e(__('approvals.type_' . $a['type'])) ?></dd></div>
    <div><dt><?= e(__('approvals.requested_by')) ?></dt><dd><?= e($a['requester'] ?? '—') ?> · <?= e(fmt_date($a['requested_at'], true)) ?></dd></div>
    <?php if ($a['amount_qar'] !== null): ?><div><dt><?= e(__('approvals.amount_qar')) ?></dt><dd class="amount"><?= money($a['amount_qar']) ?></dd></div><?php endif; ?>
    <?php foreach ($details as $label => $value): ?><div><dt><?= e(__($label)) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
    <?php if ($a['status'] !== 'pending'): ?>
      <div><dt><?= e(__('approvals.decided_by')) ?></dt><dd><?= e($a['decider'] ?? '—') ?><?= $a['decided_at'] ? ' · ' . e(fmt_date($a['decided_at'], true)) : '' ?>
        <?= $a['decided_by'] && (int) $a['decided_by'] === (int) $a['requested_by'] && $a['status'] === 'approved' ? ' <span class="badge badge-pending">' . e(__('activity.self_approved')) . '</span>' : '' ?></dd></div>
      <?php if ($a['decision_note']): ?><div class="wide"><dt><?= e(__('approvals.note')) ?></dt><dd><?= e($a['decision_note']) ?></dd></div><?php endif; ?>
    <?php endif; ?>
  </dl>
</section>
<?php if ($canDecide): ?>
<section class="card">
  <h2><?= e(__('approvals.your_decision')) ?></h2>
  <?php if ((int) $a['requested_by'] === \App\Core\Auth::id()): // only the Owner may decide their own request ?><div class="alert alert-warning"><?= e(__('approvals.own_request')) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/portal/approvals/' . $a['id'] . '/approve')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="decision-note"><?= e(__('approvals.note')) ?> <span class="muted small">(<?= e(__('common.optional')) ?>)</span></label>
      <textarea id="decision-note" name="note" rows="2" maxlength="500"></textarea></div>
    <div class="approval-buttons">
      <button class="btn btn-success" type="submit"><?= icon('check') ?> <?= e(__('common.approve')) ?></button>
      <button class="btn btn-danger" type="submit" formaction="<?= e(url('/portal/approvals/' . $a['id'] . '/reject')) ?>" data-confirm-click="<?= e(__('approvals.confirm_reject')) ?>"><?= icon('x') ?> <?= e(__('common.reject')) ?></button>
    </div>
  </form>
</section>
<?php elseif ($a['status'] === 'pending'): ?>
<p class="muted"><?= e(__('approvals.waiting')) ?></p>
<?php endif; ?>
