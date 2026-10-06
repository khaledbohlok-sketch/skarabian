<?php $pillFor = ['new' => 'warn', 'read' => '', 'replied' => 'ok', 'closed' => '']; ?>
<div class="page-head"><div><h1><?= e(__('nav.inbox')) ?></h1><p class="muted"><?= e(__('inbox.intro')) ?></p></div></div>
<nav class="tabs">
  <a href="<?= e(query_url(['status' => null, 'page' => null])) ?>" class="<?= !$status ? 'on' : '' ?>"><?= e(__('inbox.open')) ?></a>
  <?php foreach (['new', 'read', 'replied', 'closed'] as $s): ?><a href="<?= e(query_url(['status' => $s, 'page' => null])) ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= e(__('inbox.st_' . $s)) ?><span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a><?php endforeach; ?>
</nav>
<form method="get" class="filters card"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="field"><label for="q"><?= e(__('common.search')) ?></label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('inbox.search_ph')) ?>"></div>
  <div class="field"><label for="type"><?= e(__('inbox.type')) ?></label><select id="type" name="type"><option value=""><?= e(__('common.all')) ?></option>
    <?php foreach (['general', 'horse', 'visit', 'media'] as $t): ?><option value="<?= $t ?>"<?= selected($type, $t) ?>><?= e(__('inbox.type_' . $t)) ?></option><?php endforeach; ?></select></div>
  <div class="field field-check"><label><input type="checkbox" name="mine" value="1"<?= checked($mine) ?>> <?= e(__('inbox.mine')) ?></label></div>
  <div class="field"><button class="btn" type="submit"><?= e(__('common.apply')) ?></button></div>
</form>
<?php if (!$rows): ?><div class="card empty"><?= e(__('inbox.empty')) ?></div>
<?php else: ?>
<div class="card table-wrap"><table class="table">
  <thead><tr><th><?= e(__('inbox.from')) ?></th><th><?= e(__('inbox.type')) ?></th><th><?= e(__('inbox.message')) ?></th><th><?= e(__('inbox.assigned')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('inbox.received')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr class="inbox-row<?= $r['status'] === 'new' ? ' unread' : '' ?>">
      <td><a href="<?= e(url('/portal/inbox/' . $r['id'])) ?>"><?= e($r['name']) ?></a><div class="muted small" dir="ltr"><?= e($r['email'] ?: $r['phone']) ?></div></td>
      <td><?= e(__('inbox.type_' . $r['type'])) ?><?php if ($r['horse']): ?><div class="muted small"><?= e($r['horse']) ?></div><?php endif; ?></td>
      <td class="muted"><?= e(mb_strimwidth($r['message'], 0, 90, '…')) ?></td>
      <td><?= e($r['assignee'] ?? '—') ?></td>
      <td><span class="pill <?= $pillFor[$r['status']] ?>"><?= e(__('inbox.st_' . $r['status'])) ?></span></td>
      <td class="nowrap"><?= e(fmt_date($r['created_at'], true)) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => $pages]) ?>
<?php endif; ?>
