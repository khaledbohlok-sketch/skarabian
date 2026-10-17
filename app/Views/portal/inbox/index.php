<?php
$active = ($type !== '' && $type !== null ? 1 : 0) + ($mine ? 1 : 0);
$filtered = $active || $q !== '';
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => __('nav.inbox'), 'count' => plural((int) $total, 'count.message'), 'sub' => e(__('inbox.intro')),
    'search' => true, 'placeholder' => __('inbox.search_ph'), 'filters' => $active,
]) ?>
<nav class="tabs" aria-label="<?= e(__('common.status')) ?>">
  <a href="<?= e(query_url(['status' => null, 'page' => null])) ?>" class="<?= !$status ? 'on' : '' ?>"<?= !$status ? ' aria-current="page"' : '' ?>><?= e(__('inbox.open')) ?></a>
  <?php foreach (['new', 'read', 'replied', 'closed'] as $s): ?><a href="<?= e(query_url(['status' => $s, 'page' => null])) ?>" class="<?= $status === $s ? 'on' : '' ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(__('inbox.st_' . $s)) ?><span class="count"><?= number_format((int) ($counts[$s] ?? 0)) ?></span></a><?php endforeach; ?>
</nav>
<form method="get" class="filters card<?= $active ? ' open' : ' is-collapsed' ?>" id="list-filters" data-collapsible action="<?= e(url(current_path())) ?>">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
  <label class="f-item"><span><?= e(__('inbox.type')) ?></span><select name="type"><option value=""><?= e(__('common.all')) ?></option>
    <?php foreach (['general', 'horse', 'visit', 'media'] as $t): ?><option value="<?= $t ?>"<?= selected($type, $t) ?>><?= e(__('inbox.type_' . $t)) ?></option><?php endforeach; ?></select></label>
  <label class="check"><input type="checkbox" name="mine" value="1"<?= checked($mine) ?>> <?= e(__('inbox.mine')) ?></label>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button><?php if ($filtered): ?><a class="btn ghost" href="<?= e(url(current_path() . ($status ? '?status=' . rawurlencode($status) : ''))) ?>"><?= e(__('common.reset')) ?></a><?php endif; ?></div>
</form>
<?php if (!$rows): ?>
  <?= \App\Core\View::partial('portal/partials/empty', ['icon' => $filtered ? 'search' : 'inbox', 'message' => $filtered ? __('ui.empty_filtered') : __('inbox.empty'), 'clear' => $filtered ? current_path() : null]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('inbox.from')) ?></th><th><?= e(__('inbox.type')) ?></th><th><?= e(__('inbox.message')) ?></th><th><?= e(__('inbox.assigned')) ?></th><th class="nowrap"><?= e(__('common.status')) ?></th><th class="nowrap"><?= e(__('inbox.received')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr class="inbox-row<?= $r['status'] === 'new' ? ' unread' : '' ?>">
      <td class="is-title" data-label="<?= e(__('inbox.from')) ?>"><a href="<?= e(url('/portal/inbox/' . $r['id'])) ?>"><?= e($r['name']) ?></a><div class="muted small" dir="ltr"><?= e($r['email'] ?: $r['phone']) ?></div></td>
      <td data-label="<?= e(__('inbox.type')) ?>"><?= e(__('inbox.type_' . $r['type'])) ?><?php if ($r['horse']): ?><div class="muted small"><?= e($r['horse']) ?></div><?php endif; ?></td>
      <td class="c-text muted" data-label="<?= e(__('inbox.message')) ?>"><?= e(mb_strimwidth($r['message'], 0, 90, '…')) ?></td>
      <td class="<?= $r['assignee'] ? '' : 'is-empty' ?>" data-label="<?= e(__('inbox.assigned')) ?>"><?= $r['assignee'] ? e($r['assignee']) : '<span class="muted">—</span>' ?></td>
      <td class="nowrap" data-label="<?= e(__('common.status')) ?>"><span class="badge badge-<?= e($r['status']) ?>"><?= e(__('inbox.st_' . $r['status'])) ?></span></td>
      <td class="nowrap" data-label="<?= e(__('inbox.received')) ?>"><?= e(fmt_date($r['created_at'], true)) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => max(1, (int) $pages), 'per' => $per ?? null, 'total' => (int) $total]) ?>
<?php endif; ?>
