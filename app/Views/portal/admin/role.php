<?php use App\Core\Auth; $locked = $role['slug'] === 'owner'; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/roles')) ?>">← <?= e(__('nav.roles')) ?></a><h1><?= e($title) ?></h1>
  <p class="muted"><?= e(__($locked ? 'roles.owner_locked' : 'roles.matrix_help')) ?></p></div>
  <?php if ($isDefault && !$locked): ?><div class="actions"><form method="post" action="<?= e(url('/portal/roles/' . $role['id'] . '/reset')) ?>" data-confirm="<?= e(__('roles.confirm_reset')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= e(__('roles.reset')) ?></button></form></div><?php endif; ?>
</div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid-fields">
    <div class="field col-6"><label><?= e(__('roles.name_en')) ?></label><input type="text" name="name_en" value="<?= e($role['name_en']) ?>" maxlength="80"<?= $locked ? ' disabled' : '' ?>></div>
    <div class="field col-6"><label><?= e(__('roles.name_ar')) ?></label><input type="text" name="name_ar" value="<?= e($role['name_ar']) ?>" maxlength="80" dir="rtl"<?= $locked ? ' disabled' : '' ?>></div>
    <div class="field col-3 field-check"><label><input type="checkbox" name="require_2fa" value="1"<?= checked($role['require_2fa']) ?><?= $locked ? ' disabled' : '' ?>> <?= e(__('roles.twofa')) ?></label></div>
    <div class="field col-3"><label><?= e(__('roles.horse_scope')) ?></label><select name="horse_scope"<?= $locked ? ' disabled' : '' ?>><?php foreach (['all', 'assigned'] as $v): ?><option value="<?= $v ?>"<?= selected($role['horse_scope'], $v) ?>><?= e(__('roles.scope_' . $v)) ?></option><?php endforeach; ?></select></div>
    <div class="field col-3"><label><?= e(__('roles.edit_window')) ?></label><select name="edit_window"<?= $locked ? ' disabled' : '' ?>><?php foreach (['any', 'own_24h'] as $v): ?><option value="<?= $v ?>"<?= selected($role['edit_window'], $v) ?>><?= e(__('roles.window_' . $v)) ?></option><?php endforeach; ?></select></div>
    <div class="field col-3"><label><?= e(__('roles.country')) ?></label><input type="text" name="restrict_country" maxlength="2" value="<?= e($role['restrict_country']) ?>" placeholder="QA" dir="ltr"<?= $locked ? ' disabled' : '' ?>><small class="help"><?= e(__('roles.country_help')) ?></small></div>
    <div class="field col-6 field-check"><label><input type="checkbox" name="business_hours_only" value="1"<?= checked($role['business_hours_only']) ?><?= $locked ? ' disabled' : '' ?>> <?= e(__('roles.hours_only', ['hours' => setting('security.business_hours', '06:00-22:00')])) ?></label></div>
  </div>
  <div class="table-wrap"><table class="table perm-matrix">
    <thead><tr><th><?= e(__('roles.module')) ?></th><?php foreach (Auth::ACTIONS as $a): ?><th class="c"><?= e(__('roles.a_' . $a)) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach (Auth::MODULES as $m): ?><tr><td><b><?= e(__('roles.m_' . $m)) ?></b></td>
      <?php foreach (Auth::ACTIONS as $a): ?><td class="c"><input type="checkbox" name="perm[<?= e($m) ?>][<?= e($a) ?>]" value="1"<?= checked($locked || !empty($have[$m][$a])) ?><?= $locked ? ' disabled' : '' ?> aria-label="<?= e(__('roles.m_' . $m) . ' — ' . __('roles.a_' . $a)) ?>"></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  </table></div>
  <p class="muted small"><?= e(__('roles.sensitive_help')) ?></p>
  <?php if (!$locked): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div><?php endif; ?>
</form>
