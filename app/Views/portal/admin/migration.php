<?php use App\Core\Lang;
$label = fn (string $prefix, ?string $v) => $v !== null && Lang::has($prefix . $v) ? __($prefix . $v) : (string) $v;
$pill = ['warning' => 'warn', 'fixed' => 'ok', 'merged' => 'ok', 'linked' => '', 'created' => '', 'converted' => '', 'imported' => 'ok', 'skipped' => ''];
$isPreview = $run && str_starts_with($run, 'P-'); ?>
<div class="page-head"><div><h1><?= e(__('nav.migration_report')) ?></h1><p class="muted"><?= e(__('migration.intro')) ?></p></div>
  <?php if ($configured): ?><div class="actions">
    <form method="post" action="<?= e(url('/portal/migration-report/run')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="mode" value="preview"><button class="btn" type="submit"><?= e(__('migration.run_preview')) ?></button></form>
  </div><?php endif; ?></div>
<?php if (!$configured): ?><div class="alert alert-info"><?= e(__('migration.how')) ?></div><?php endif; ?>
<?php if ($configured && $canCommit): ?>
<form method="post" action="<?= e(url('/portal/migration-report/run')) ?>" class="card form-inline" data-confirm="<?= e(__('migration.confirm_import')) ?>"><?= csrf_field() ?><input type="hidden" name="mode" value="commit">
  <div style="flex-basis:100%"><h2 style="margin:0 0 4px"><?= e(__('migration.ready')) ?></h2><p class="muted small" style="margin:0"><?= e(__('migration.ready_help')) ?></p></div>
  <input type="text" name="confirm" placeholder="IMPORT" autocomplete="off" aria-label="<?= e(__('migration.type_import')) ?>" style="max-width:180px" required>
  <button class="btn btn-primary" type="submit"><?= e(__('migration.run_import')) ?></button></form>
<?php endif; ?>
<?php if (!$runs): ?><div class="empty card"><p><?= e(__('migration.none')) ?></p></div><?php return; endif; ?>
<?php if ($isPreview): ?><div class="alert alert-warning"><?= e(__('migration.is_preview')) ?></div><?php endif; ?>
<form method="get" class="filters card">
  <label class="f-item"><span><?= e(__('migration.run')) ?></span><select name="run"><?php foreach ($runs as $r): ?><option value="<?= e($r) ?>"<?= selected($run, $r) ?>><?= e((str_starts_with($r, 'P-') ? __('migration.preview') : __('migration.import')) . ' ' . substr($r, 2)) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('migration.entity')) ?></span><select name="entity"><option value=""><?= e(__('common.all')) ?></option><?php foreach (array_unique(array_column($summary, 'entity')) as $en): ?><option value="<?= e($en) ?>"<?= selected($_GET['entity'] ?? '', $en) ?>><?= e($label('migration.e_', $en)) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('migration.only_open')) ?></span><select name="open"><option value=""><?= e(__('common.all')) ?></option><option value="1"<?= selected($_GET['open'] ?? '', '1') ?>><?= e(__('migration.not_reviewed')) ?></option></select></label>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
</form>
<section class="card"><h2><?= e(__('migration.summary')) ?></h2><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('migration.entity')) ?></th><th><?= e(__('activity.action')) ?></th><th class="num"><?= e(__('migration.count')) ?></th><th class="num"><?= e(__('migration.reviewed')) ?></th></tr></thead><tbody>
<?php foreach ($summary as $s): ?><tr><td><a href="<?= e(query_url(['entity' => $s['entity']])) ?>"><?= e($label('migration.e_', $s['entity'])) ?></a></td><td><span class="pill <?= $pill[$s['action']] ?? '' ?>"><?= e($label('migration.a_', $s['action'])) ?></span></td><td class="num"><?= (int) $s['n'] ?></td><td class="num"><?= (int) $s['done'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<div class="card table-wrap">
  <form method="post" action="<?= e(url('/portal/migration-report/review-all')) ?>" style="margin-bottom:10px"><?= csrf_field() ?><input type="hidden" name="run" value="<?= e($run) ?>"><input type="hidden" name="entity" value="<?= e($_GET['entity'] ?? '') ?>"><button class="btn btn-sm" type="submit"><?= e(__('migration.mark_all')) ?></button></form>
  <table class="table compact">
  <thead><tr><th><?= e(__('migration.entity')) ?></th><th><?= e(__('migration.old_id')) ?></th><th><?= e(__('activity.action')) ?></th><th><?= e(__('migration.details')) ?></th><th><?= e(__('migration.reviewed')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= e($label('migration.e_', $r['entity'])) ?></td><td><?= e($r['old_id']) ?></td><td><span class="pill <?= $pill[$r['action']] ?? '' ?>"><?= e($label('migration.a_', $r['action'])) ?></span></td><td><?= e($r['details']) ?></td>
    <td><form method="post" action="<?= e(url('/portal/migration-report/' . $r['id'] . '/reviewed')) ?>"><?= csrf_field() ?><button class="btn btn-sm<?= $r['reviewed'] ? '' : ' btn-primary' ?>" type="submit"><?= e(__($r['reviewed'] ? 'migration.done' : 'migration.mark')) ?></button></form></td></tr><?php endforeach; ?></tbody>
</table></div>
