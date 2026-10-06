<div class="page-head"><div><h1><?= e(__('nav.trash')) ?></h1><p class="muted"><?= e(__('trash.intro')) ?></p></div>
  <?php if ($rows): ?><div class="actions"><form method="post" action="<?= e(url('/portal/trash/empty')) ?>" data-confirm="<?= e(__('trash.confirm_empty')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= e(__('trash.empty')) ?></button></form></div><?php endif; ?></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('trash.item')) ?></th><th><?= e(__('activity.module')) ?></th><th><?= e(__('trash.deleted_by')) ?></th><th><?= e(__('common.date')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $t): ?><tr>
    <td><?= e($t['label']) ?></td><td><?= e(__('roles.m_' . $t['module'])) ?></td><td><?= e($t['by_name'] ?? '—') ?></td><td><?= e(fmt_date($t['deleted_at'], true)) ?></td>
    <td class="actions-cell"><form method="post" action="<?= e(url('/portal/trash/' . $t['id'] . '/restore')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><?= e(__('trash.restore')) ?></button></form>
      <form method="post" action="<?= e(url('/portal/trash/' . $t['id'] . '/purge')) ?>" class="inline" data-confirm="<?= e(__('trash.confirm_purge')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('trash.purge')) ?></button></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= e(__('trash.none')) ?></td></tr><?php endif; ?></tbody>
</table></div>
