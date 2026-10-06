<div class="page-head"><h1><?= e(__('account.title')) ?></h1></div>
<div class="grid-2">
  <section class="card">
    <dl class="dl">
      <dt><?= e(__('users.name')) ?></dt><dd><?= e($user['name']) ?></dd>
      <dt><?= e(__('users.username')) ?></dt><dd><?= e($user['username']) ?></dd>
      <dt><?= e(__('users.email')) ?></dt><dd><?= e($user['email']) ?></dd>
      <dt><?= e(__('users.role')) ?></dt><dd><?= e(loc(\App\Core\Auth::role(), 'name')) ?></dd>
      <dt><?= e(__('auth.twofa')) ?></dt><dd><?= e(__('auth.method_' . $user['twofa_method'])) ?></dd>
      <dt><?= e(__('users.last_login')) ?></dt><dd><?= e(fmt_date($user['last_login_at'], true)) ?></dd>
    </dl>
    <p><a class="btn" href="<?= e(url('/portal/account/password')) ?>"><?= e(__('auth.change_password')) ?></a> <a class="btn" href="<?= e(url('/portal/account/2fa')) ?>"><?= e(__('auth.twofa')) ?></a></p>
  </section>
  <section class="card">
    <h2><?= e(__('account.active_sessions')) ?></h2>
    <table class="table compact">
      <thead><tr><th><?= e(__('account.device')) ?></th><th>IP</th><th><?= e(__('account.last_seen')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sessions as $s): ?>
        <tr><td><?= e($s['device']) ?><?= $s['token_hash'] === $currentHash ? ' <span class="badge badge-active">' . e(__('account.this_device')) . '</span>' : '' ?></td>
        <td dir="ltr"><?= e($s['ip']) ?> <?= e($s['country']) ?></td><td><?= e(fmt_date($s['last_seen_at'], true)) ?></td>
        <td><?php if ($s['token_hash'] !== $currentHash): ?><form method="post" action="<?= e(url('/portal/account/sessions/' . $s['id'] . '/end')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('account.end')) ?></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <form method="post" action="<?= e(url('/portal/account/logout-all')) ?>" data-confirm="<?= e(__('account.logout_all_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= e(__('account.logout_all')) ?></button></form>
  </section>
</div>
