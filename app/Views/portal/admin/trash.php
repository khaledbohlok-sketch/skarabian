<div class="page-head"><div><h1><?= e(__('nav.trash')) ?></h1><p class="muted"><?= $rows ? e(plural(count($rows), 'count.record')) . ' · ' : '' ?><?= e(__('trash.intro')) ?></p></div>
  <?php if ($rows): ?><div class="actions"><form method="post" action="<?= e(url('/portal/trash/empty')) ?>" data-confirm="<?= e(__('trash.confirm_empty')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?> <?= e(__('trash.empty')) ?></button></form></div><?php endif; ?></div>
<?php if ($rows): ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('trash.item')) ?></th><th><?= e(__('activity.module')) ?></th><th><?= e(__('trash.deleted_by')) ?></th><th class="nowrap"><?= e(__('common.date')) ?></th><th><span class="sr-only"><?= e(__('common.actions')) ?></span></th></tr></thead>
  <tbody><?php foreach ($rows as $t): ?><tr>
    <td class="is-title" data-label="<?= e(__('trash.item')) ?>"><?= e($t['label']) ?></td><td data-label="<?= e(__('activity.module')) ?>"><?= e(__('roles.m_' . $t['module'])) ?></td><td data-label="<?= e(__('trash.deleted_by')) ?>"><?= e($t['by_name'] ?? '—') ?></td><td class="nowrap" data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($t['deleted_at'])) ?></td>
    <td class="doc-row-actions"><form method="post" action="<?= e(url('/portal/trash/' . $t['id'] . '/restore')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('refresh') ?> <?= e(__('trash.restore')) ?></button></form>
      <?php if (\App\Services\TrashService::keptForGood($t)): // its number stays on record ?><span class="badge badge-neutral" data-tip="<?= e(__('trash.kept_help')) ?>" title="<?= e(__('trash.kept_help')) ?>"><?= e(__('trash.kept')) ?></span>
      <?php else: ?><form method="post" action="<?= e(url('/portal/trash/' . $t['id'] . '/purge')) ?>" class="inline" data-confirm="<?= e(__('trash.confirm_purge')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('trash.purge')) ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?php else: ?>
  <?= \App\Core\View::partial('portal/partials/empty', ['icon' => 'trash', 'message' => __('trash.none')]) ?>
<?php endif; ?>
