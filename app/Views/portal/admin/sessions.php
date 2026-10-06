<div class="page-head"><div><h1><?= e(__('nav.sessions')) ?></h1><p class="muted"><?= e(__('sessions.intro')) ?></p></div></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('users.singular')) ?></th><th><?= e(__('account.device')) ?></th><th>IP</th><th><?= e(__('sessions.started')) ?></th><th><?= e(__('account.last_seen')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $s): ?><tr>
    <td><a href="<?= e(url('/portal/users/' . $s['user_id'])) ?>"><?= e($s['name']) ?></a> <small class="muted"><?= e($s['username']) ?></small></td><td><?= e($s['device']) ?></td>
    <td dir="ltr"><?= e($s['ip']) ?> <?= e($s['country']) ?></td><td><?= e(fmt_date($s['created_at'], true)) ?></td><td><?= e(fmt_date($s['last_seen_at'], true)) ?></td>
    <td><?php if ($s['token_hash'] === $current): ?><span class="badge badge-active"><?= e(__('account.this_device')) ?></span><?php else: ?><form method="post" action="<?= e(url('/portal/sessions/' . $s['id'] . '/end')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('account.end')) ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
