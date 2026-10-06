<?php use App\Core\Auth; ?>
<div class="page-head"><div><h1><?= e(__('nav.payroll')) ?></h1><p class="muted"><?= e(__('payroll.intro')) ?></p></div></div>
<?php if (Auth::can('payroll', 'create')): ?>
<form method="post" action="<?= e(url('/portal/payroll')) ?>" class="card form-inline">
  <?= csrf_field() ?>
  <label for="f_period"><?= e(__('payroll.new_run')) ?></label>
  <input id="f_period" type="month" name="period" value="<?= e($next) ?>" required>
  <button class="btn btn-primary" type="submit"><?= e(__('payroll.create')) ?></button>
  <?php if ($sens && $due): ?><span class="muted small"><?= e(__('payroll.estimate')) ?>: <?= money($due['estimate']) ?></span><?php endif; ?>
</form>
<?php endif; ?>
<?php if ($runs): ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('payroll.period')) ?></th><th><?= e(__('common.status')) ?></th><th class="num"><?= e(__('nav.employees')) ?></th><th class="num"><?= e(__('payroll.paid_count')) ?></th><?php if ($sens): ?><th class="num"><?= e(__('payroll.total_net')) ?></th><?php endif; ?></tr></thead>
  <tbody><?php foreach ($runs as $r): ?><tr>
    <td data-label="<?= e(__('payroll.period')) ?>"><a href="<?= e(url('/portal/payroll/' . $r['id'])) ?>"><b><?= e($r['period']) ?></b></a></td>
    <td data-label="<?= e(__('common.status')) ?>"><?= status_badge($r['status'], 'payroll.status') ?></td>
    <td class="num" data-label="<?= e(__('nav.employees')) ?>"><?= (int) $r['employees'] ?></td>
    <td class="num" data-label="<?= e(__('payroll.paid_count')) ?>"><?= (int) $r['paid'] ?> / <?= (int) $r['employees'] ?></td>
    <?php if ($sens): ?><td class="num" data-label="<?= e(__('payroll.total_net')) ?>"><?= money($r['total_net_qar']) ?></td><?php endif; ?>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?php else: ?><div class="empty card"><p><?= e(__('payroll.none')) ?></p></div><?php endif; ?>
