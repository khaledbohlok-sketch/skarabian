<div class="page-head"><div><h1><?= e(__('nav.migration_report')) ?></h1><p class="muted"><?= e(__('migration.intro')) ?></p></div></div>
<?php if (!$runs): ?><div class="empty card"><p><?= e(__('migration.none')) ?></p></div><?php return; endif; ?>
<form method="get" class="filters card">
  <label class="f-item"><span><?= e(__('migration.run')) ?></span><select name="run"><?php foreach ($runs as $r): ?><option<?= selected($run, $r) ?>><?= e($r) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('migration.entity')) ?></span><select name="entity"><option value=""><?= e(__('common.all')) ?></option><?php foreach (array_unique(array_column($summary, 'entity')) as $en): ?><option<?= selected($_GET['entity'] ?? '', $en) ?>><?= e($en) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('migration.only_open')) ?></span><select name="open"><option value=""><?= e(__('common.all')) ?></option><option value="1"<?= selected($_GET['open'] ?? '', '1') ?>><?= e(__('common.yes')) ?></option></select></label>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
</form>
<section class="card"><h2><?= e(__('migration.summary')) ?></h2><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('migration.entity')) ?></th><th><?= e(__('activity.action')) ?></th><th class="num"><?= e(__('migration.count')) ?></th><th class="num"><?= e(__('migration.reviewed')) ?></th></tr></thead><tbody>
<?php foreach ($summary as $s): ?><tr><td><?= e($s['entity']) ?></td><td><?= e($s['action']) ?></td><td class="num"><?= (int) $s['n'] ?></td><td class="num"><?= (int) $s['done'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<div class="table-wrap"><table class="table compact">
  <thead><tr><th><?= e(__('migration.entity')) ?></th><th><?= e(__('migration.old_id')) ?></th><th><?= e(__('activity.action')) ?></th><th><?= e(__('migration.details')) ?></th><th><?= e(__('migration.reviewed')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr class="<?= $r['action'] === 'warning' ? 'row-pending' : '' ?>"><td><?= e($r['entity']) ?><?= $r['new_id'] ? ' #' . (int) $r['new_id'] : '' ?></td><td><?= e($r['old_id']) ?></td><td><?= e($r['action']) ?></td><td><?= e($r['details']) ?></td>
    <td><form method="post" action="<?= e(url('/portal/migration-report/' . $r['id'] . '/reviewed')) ?>"><?= csrf_field() ?><button class="btn btn-xs<?= $r['reviewed'] ? '' : ' btn-primary' ?>" type="submit"><?= e(__($r['reviewed'] ? 'migration.done' : 'migration.mark')) ?></button></form></td></tr><?php endforeach; ?></tbody>
</table></div>
