<?php [$ok, $n, $broken] = $stats['chain']; ?>
<div class="page-head"><div><h1><?= e(__('nav.security')) ?></h1><p class="muted"><?= e(__('security.intro')) ?></p></div></div>
<div class="stat-grid">
  <div class="stat<?= $stats['failed'] ? ' alert-warn' : '' ?>"><div class="k"><?= e(__('security.failed_7d')) ?></div><div class="v"><?= (int) $stats['failed'] ?></div></div>
  <div class="stat<?= $stats['locked'] ? ' alert-bad' : '' ?>"><div class="k"><?= e(__('security.locked_now')) ?></div><div class="v"><?= (int) $stats['locked'] ?></div></div>
  <div class="stat"><div class="k"><?= e(__('security.denied_7d')) ?></div><div class="v"><?= (int) $stats['denied'] ?></div></div>
  <div class="stat<?= $ok ? '' : ' alert-bad' ?>"><div class="k"><?= e(__('security.log_chain')) ?></div><div class="v" style="font-size:18px"><?= e($ok ? __('security.intact', ['n' => $n]) : __('security.broken', ['id' => $broken])) ?></div></div>
</div>
<?php if ($stats['no2fa']): ?><div class="alert alert-warning"><?= e(__('security.no2fa')) ?> <?php foreach ($stats['no2fa'] as $u): ?><a href="<?= e(url('/portal/users/' . $u['id'])) ?>"><?= e($u['name']) ?></a> (<?= e($u['role']) ?>) <?php endforeach; ?></div><?php endif; ?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => false]) ?></div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
<section class="card"><h2><?= e(__('security.role_rules')) ?></h2>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('users.role')) ?></th><th><?= e(__('roles.twofa')) ?></th><th><?= e(__('roles.country')) ?></th><th><?= e(__('security.hours')) ?></th></tr></thead><tbody>
  <?php foreach ($stats['roles'] as $r): ?><tr><td><a href="<?= e(url('/portal/roles/' . $r['id'])) ?>"><?= e(loc($r, 'name')) ?></a></td><td><?= $r['require_2fa'] ? '✓' : '—' ?></td><td><?= e($r['restrict_country'] ?: '—') ?></td><td><?= $r['business_hours_only'] ? '✓' : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<form method="post" class="card" data-confirm="<?= e(__('security.confirm_logout_everyone')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout_everyone">
  <h2><?= e(__('security.logout_everyone')) ?></h2><p class="muted"><?= e(__('security.logout_everyone_help')) ?></p><button class="btn btn-danger" type="submit"><?= e(__('security.logout_everyone')) ?></button></form>
