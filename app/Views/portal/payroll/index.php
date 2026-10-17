<?php use App\Core\Auth; ?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => __('nav.payroll'), 'count' => $runs ? plural(count($runs), 'count.payroll_run') : '', 'sub' => e(__('payroll.intro')),
]) ?>
<?php if (Auth::can('payroll', 'create')): ?>
<form method="post" action="<?= e(url('/portal/payroll')) ?>" class="card new-run">
  <?= csrf_field() ?>
  <div class="field"><label for="f_period"><?= e(__('payroll.new_run')) ?></label>
    <input id="f_period" type="month" name="period" value="<?= e($next) ?>" required></div>
  <button class="btn btn-primary" type="submit"><?= icon('plus') ?> <?= e(__('payroll.create')) ?></button>
  <?php if ($sens && $due): ?><p class="muted small new-run-note"><?= e(__('payroll.estimate')) ?>: <?= money($due['estimate']) ?></p><?php endif; ?>
</form>
<?php endif; ?>
<?php if ($runs): $canDelete = Auth::can('payroll', 'delete'); ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('payroll.period')) ?></th><th class="nowrap"><?= e(__('common.status')) ?></th><th class="num"><?= e(__('nav.employees')) ?></th><th class="num"><?= e(__('payroll.paid_count')) ?></th><?php if ($sens): ?><th class="num"><?= e(__('payroll.total_net')) ?></th><?php endif; ?><?php if ($canDelete): ?><th><span class="sr-only"><?= e(__('common.actions')) ?></span></th><?php endif; ?></tr></thead>
  <tbody><?php foreach ($runs as $r): $month = fmt_month($r['period'] . '-01'); ?><tr>
    <td class="is-title" data-label="<?= e(__('payroll.period')) ?>"><a href="<?= e(url('/portal/payroll/' . $r['id'])) ?>"><b><?= e($month ?: $r['period']) ?></b></a></td>
    <td class="nowrap" data-label="<?= e(__('common.status')) ?>"><?= status_badge($r['status'], 'payroll.status') ?></td>
    <td class="num" data-label="<?= e(__('nav.employees')) ?>"><?= number_format((int) $r['employees']) ?></td>
    <td class="num" data-label="<?= e(__('payroll.paid_count')) ?>"><?= number_format((int) $r['paid']) ?> / <?= number_format((int) $r['employees']) ?></td>
    <?php if ($sens): ?><td class="num" data-label="<?= e(__('payroll.total_net')) ?>"><?= money($r['total_net_qar']) ?></td><?php endif; ?>
    <?php if ($canDelete): // a run with paid salaries too (typed confirmation: its payments are removed with it; the typed value stays the stored period) ?><td class="doc-row-actions"><form method="post" action="<?= e(url('/portal/payroll/' . $r['id'] . '/delete')) ?>" class="inline"<?= (int) $r['paid']
        ? ' data-confirm-type="' . e($r['period']) . '" data-confirm-type-message="' . e(__('payroll.confirm_delete_paid', ['n' => (int) $r['paid'], 'amount' => money((float) $r['paid_net'], 'QAR', false), 'period' => $r['period']])) . '"'
        : ' data-confirm="' . e(delete_confirm(in_array($r['status'], ['draft', 'pending'], true) ? 'payroll.confirm_delete' : 'payroll.confirm_delete_approved')) . '"' ?>><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="<?= e(__('common.delete') . ' ' . ($month ?: $r['period'])) ?>" data-tip="<?= e(__('common.delete')) ?>"><?= icon('trash') ?></button></form></td><?php endif; ?>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?php else: ?>
  <?= \App\Core\View::partial('portal/partials/empty', ['icon' => 'payroll', 'message' => __('payroll.none')]) ?>
<?php endif; ?>
