<div class="page-head"><div><h1><?= e(__('nav.sessions')) ?></h1><p class="muted"><?= e(__('sessions.intro')) ?></p></div></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('users.singular')) ?></th><th><?= e(__('account.device')) ?></th><th class="nowrap">IP</th><th class="nowrap"><?= e(__('sessions.started')) ?></th><th class="nowrap"><?= e(__('account.last_seen')) ?></th><th><span class="sr-only"><?= e(__('common.actions')) ?></span></th></tr></thead>
  <tbody><?php foreach ($rows as $s): ?><tr>
    <td class="is-title" data-label="<?= e(__('users.singular')) ?>"><a href="<?= e(url('/portal/users/' . $s['user_id'])) ?>"><?= e($s['name']) ?></a> <small class="muted" dir="ltr"><?= e($s['username']) ?></small></td><td data-label="<?= e(__('account.device')) ?>"><?= e(device_label($s['device'])) ?></td>
    <td class="nowrap" data-label="IP" dir="ltr"><?= e($s['ip']) ?> <?= e($s['country'] ?? '') ?></td><td class="nowrap" data-label="<?= e(__('sessions.started')) ?>"><?= e(fmt_date($s['created_at'], true)) ?></td><td class="nowrap" data-label="<?= e(__('account.last_seen')) ?>"><?= e(fmt_date($s['last_seen_at'], true)) ?></td>
    <td class="doc-row-actions"><?php if ($s['token_hash'] === $current): ?><span class="badge badge-active"><?= e(__('account.this_device')) ?></span><?php else: ?><form method="post" action="<?= e(url('/portal/sessions/' . $s['id'] . '/end')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('account.end')) ?></button></form><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?php if ($devices): ?>
<section class="card" id="devices"><h2><?= e(__('sessions.devices')) ?></h2>
  <p class="muted small"><?= e(__('sessions.devices_help')) ?></p>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('users.singular')) ?></th><th><?= e(__('account.device')) ?></th><th><?= e(__('sessions.network')) ?></th><th><?= e(__('sessions.country')) ?></th><th><?= e(__('sessions.first_seen')) ?></th><th><?= e(__('account.last_seen')) ?></th><th class="num"><?= e(__('sessions.logins')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($devices as $d): ?><tr>
      <td data-label="<?= e(__('users.singular')) ?>"><a href="<?= e(url('/portal/users/' . $d['user_id'])) ?>"><?= e($d['name']) ?></a></td>
      <td data-label="<?= e(__('account.device')) ?>"><?= e(device_label($d['browser'])) ?></td>
      <td data-label="<?= e(__('sessions.network')) ?>" dir="ltr"><?= e($d['ip_net'] ?: '—') ?></td>
      <td data-label="<?= e(__('sessions.country')) ?>"><?= e(\App\Services\Geo::name($d['country'])) ?></td>
      <td data-label="<?= e(__('sessions.first_seen')) ?>" class="nowrap"><?= e(fmt_date($d['first_seen'], true)) ?></td>
      <td data-label="<?= e(__('account.last_seen')) ?>" class="nowrap"><?= e(fmt_date($d['last_seen'], true)) ?></td>
      <td class="num" data-label="<?= e(__('sessions.logins')) ?>"><?= (int) $d['logins'] ?></td>
      <td class="doc-row-actions"><form method="post" action="<?= e(url('/portal/known-devices/' . $d['id'] . '/forget')) ?>" class="inline" data-confirm="<?= e(__('sessions.confirm_forget')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('sessions.forget')) ?></button></form></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <p class="muted small"><?= e(__('sessions.geo_credit')) ?></p>
</section>
<?php endif; ?>
