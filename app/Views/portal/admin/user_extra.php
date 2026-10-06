<?php
use App\Core\Auth;
use App\Core\DB;
$sessions = DB::all('SELECT * FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC LIMIT 20', [$u['id']]);
$grants = DB::all('SELECT g.*, b.name AS by_name FROM user_temp_grants g LEFT JOIN users b ON b.id = g.granted_by WHERE g.user_id = ? AND g.expires_at > NOW() ORDER BY g.expires_at', [$u['id']]);
?>
<section class="card">
  <h2><?= e(__('account.active_sessions')) ?></h2>
  <?php if ($sessions): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('account.device')) ?></th><th>IP</th><th><?= e(__('account.last_seen')) ?></th></tr></thead><tbody>
  <?php foreach ($sessions as $s): ?><tr><td><?= e($s['device']) ?></td><td dir="ltr"><?= e($s['ip']) ?> <?= e($s['country']) ?></td><td><?= e(fmt_date($s['last_seen_at'], true)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><p class="muted"><?= e(__('users.no_sessions')) ?></p><?php endif; ?>
</section>
<?php if (Auth::isOwner() && (int) $u['id'] !== Auth::id()): ?>
<section class="card">
  <h2><?= e(__('users.temp_access')) ?></h2>
  <p class="muted small"><?= e(__('users.temp_access_help')) ?></p>
  <?php if ($grants): ?><ul class="alert-list"><?php foreach ($grants as $g): ?><li><span><b><?= e(__('roles.m_' . $g['module'])) ?></b> · <?= e(__('roles.a_' . $g['action'])) ?> · <?= e(__('users.until', ['date' => fmt_date($g['expires_at'], true)])) ?></span>
    <form method="post" action="<?= e(url('/portal/users/' . $u['id'] . '/grant/' . $g['id'] . '/revoke')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('users.revoke')) ?></button></form></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('/portal/users/' . $u['id'] . '/grant')) ?>" class="form-inline grant-form">
    <?= csrf_field() ?>
    <select name="module" required><?php foreach (Auth::MODULES as $m): ?><option value="<?= e($m) ?>"><?= e(__('roles.m_' . $m)) ?></option><?php endforeach; ?></select>
    <select name="action" required><?php foreach (Auth::ACTIONS as $a): ?><option value="<?= e($a) ?>"><?= e(__('roles.a_' . $a)) ?></option><?php endforeach; ?></select>
    <input type="datetime-local" name="expires_at" required value="<?= e(date('Y-m-d\TH:i', strtotime('+1 day'))) ?>">
    <button class="btn btn-primary" type="submit"><?= e(__('users.grant')) ?></button>
  </form>
</section>
<?php endif; ?>
