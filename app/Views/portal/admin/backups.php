<div class="page-head"><div><h1><?= e(__('nav.backups')) ?></h1><p class="muted"><?= e(__('backups.intro')) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/portal/settings/backups?test=1')) ?>"><?= e(__('backups.test')) ?></a>
  <form method="post" action="<?= e(url('/portal/settings/backups/run')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('backups.run')) ?></button></form></div></div>
<?php if ($test): ?><div class="alert alert-<?= $test[0] ? 'success' : 'error' ?>"><?= e(__($test[0] ? 'backups.test_ok' : 'backups.test_bad')) ?> — <?= e($test[1]) ?></div><?php endif; ?>
<?php if (!$drive): ?><div class="alert alert-warning"><?= e(__('backups.no_offsite')) ?></div><?php endif; ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('backups.file')) ?></th><th class="num"><?= e(__('backups.size')) ?></th><th><?= e(__('common.date')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($files as $f): ?><tr><td><?= e($f['name']) ?></td><td class="num"><?= e(number_format($f['size'] / 1048576, 2)) ?> MB</td><td><?= e(date('d M Y H:i', $f['time'])) ?></td>
    <td><a class="btn btn-sm" href="<?= e(url('/portal/settings/backups/' . $f['name'])) ?>"><?= icon('download') ?> <?= e(__('common.download')) ?></a></td></tr><?php endforeach; ?>
  <?php if (!$files): ?><tr><td colspan="4" class="muted"><?= e(__('backups.none')) ?></td></tr><?php endif; ?></tbody>
</table></div>
