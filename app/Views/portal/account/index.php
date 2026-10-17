<?php $role = \App\Core\Auth::role(); ?>
<div class="page-head">
  <div class="head-media">
    <span class="avatar lg" aria-hidden="true"><?= e(mb_substr((string) $user['name'], 0, 1)) ?></span>
    <div class="head-text"><h1><?= e(__('account.title')) ?></h1><p class="muted"><?= e($user['name']) ?> · <?= e(loc($role, 'name')) ?></p></div>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('/portal/account/2fa')) ?>"><?= icon('shield') ?> <?= e(__('auth.twofa')) ?></a>
    <a class="btn btn-primary" href="<?= e(url('/portal/account/password')) ?>"><?= icon('key') ?> <?= e(__('auth.change_password')) ?></a>
  </div>
</div>
<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2><?= e(__('account.details')) ?></h2></div>
    <dl class="dl dl-grid two">
      <div><dt><?= e(__('users.name')) ?></dt><dd><?= e($user['name']) ?></dd></div>
      <div><dt><?= e(__('users.username')) ?></dt><dd dir="ltr"><?= e($user['username']) ?></dd></div>
      <div><dt><?= e(__('users.email')) ?></dt><dd dir="ltr"><?= e($user['email']) ?></dd></div>
      <div><dt><?= e(__('users.role')) ?></dt><dd><?= e(loc($role, 'name')) ?></dd></div>
      <div><dt><?= e(__('auth.twofa')) ?></dt><dd><?= $user['twofa_method'] === 'none' ? '<span class="badge badge-warn">' . e(__('auth.method_none')) . '</span>' : '<span class="badge badge-active">' . e(__('auth.method_' . $user['twofa_method'])) . '</span>' ?></dd></div>
      <div><dt><?= e(__('users.last_login')) ?></dt><dd><?= $user['last_login_at'] ? e(fmt_date($user['last_login_at'], true)) : '<span class="muted">—</span>' ?></dd></div>
    </dl>
  </section>
  <section class="card">
    <div class="card-head"><h2><?= e(__('account.active_sessions')) ?></h2>
      <form method="post" action="<?= e(url('/portal/account/logout-all')) ?>" data-confirm="<?= e(__('account.logout_all_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= icon('log-out') ?> <?= e(__('account.logout_all')) ?></button></form></div>
    <div class="table-wrap"><table class="table compact">
      <thead><tr><th><?= e(__('account.device')) ?></th><th class="nowrap">IP</th><th class="nowrap"><?= e(__('account.last_seen')) ?></th><th><span class="sr-only"><?= e(__('common.actions')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($sessions as $s): $here = $s['token_hash'] === $currentHash; ?>
        <tr><td class="is-title" data-label="<?= e(__('account.device')) ?>"><?= e(device_label($s['device'])) ?><?= $here ? ' <span class="badge badge-active">' . e(__('account.this_device')) . '</span>' : '' ?></td>
        <td class="nowrap" data-label="IP" dir="ltr"><?= e($s['ip']) ?> <?= e($s['country']) ?></td><td class="nowrap" data-label="<?= e(__('account.last_seen')) ?>"><?= e(fmt_date($s['last_seen_at'], true)) ?></td>
        <td class="doc-row-actions<?= $here ? ' is-empty' : '' ?>"><?php if (!$here): ?><form method="post" action="<?= e(url('/portal/account/sessions/' . $s['id'] . '/end')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('account.end')) ?></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>
<?php // appearance, kept in this browser: the theme (the same switch as the account menu) and fewer animations ?>
<section class="card appearance">
  <div class="card-head"><h2><?= e(__('theme.label')) ?></h2></div>
  <div class="theme-switch" role="group" aria-label="<?= e(__('theme.label')) ?>">
    <?php foreach (['system' => 'monitor', 'light' => 'sun', 'dark' => 'moon'] as $tk => $tic): ?>
      <button type="button" role="radio" aria-checked="<?= ui_theme() === $tk ? 'true' : 'false' ?>" data-theme-set="<?= $tk ?>"><?= icon($tic) ?><span><?= e(__('theme.' . $tk)) ?></span></button>
    <?php endforeach; ?>
  </div>
  <label class="switch"><input type="checkbox" data-motion-toggle<?= ui_motion() === 'reduce' ? ' checked' : '' ?>><span></span> <?= e(__('account.reduce_motion')) ?></label>
  <p class="help"><?= e(__('account.reduce_motion_help')) ?></p>
</section>
