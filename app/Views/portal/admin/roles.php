<?php $yes = '<span class="tick" title="' . e(__('common.yes')) . '">✓</span>'; $no = '<span class="muted">—</span>'; ?>
<div class="page-head"><div><h1><?= e(__('nav.roles')) ?></h1><p class="muted"><?= e(plural(count($roles), 'count.record')) ?> · <?= e(__('roles.intro')) ?></p></div>
  <div class="actions"><a class="btn btn-primary" href="#new-role"><?= icon('plus') ?> <?= e(__('roles.new')) ?></a></div></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('users.role')) ?></th><th class="num"><?= e(__('nav.users')) ?></th><th class="nowrap"><?= e(__('roles.twofa')) ?></th><th><?= e(__('roles.horse_scope')) ?></th><th><?= e(__('roles.edit_window')) ?></th></tr></thead>
  <tbody><?php foreach ($roles as $r): ?><tr>
    <td class="is-title" data-label="<?= e(__('users.role')) ?>"><a href="<?= e(url('/portal/roles/' . $r['id'])) ?>"><b><?= e(loc($r, 'name')) ?></b></a><?= !empty($r['read_only']) ? ' <span class="badge badge-info">' . e(__('roles.read_only_badge')) . '</span>' : '' ?></td><td class="num" data-label="<?= e(__('nav.users')) ?>"><?= number_format((int) $r['users']) ?></td>
    <td data-label="<?= e(__('roles.twofa')) ?>"><?= $r['require_2fa'] ? $yes : $no ?></td><td data-label="<?= e(__('roles.horse_scope')) ?>"><?= e(__('roles.scope_' . $r['horse_scope'])) ?></td><td data-label="<?= e(__('roles.edit_window')) ?>"><?= e(__(!empty($r['read_only']) ? 'roles.window_na' : 'roles.window_' . $r['edit_window'])) ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<form method="post" class="card record-form" id="new-role">
  <?= csrf_field() ?>
  <div class="card-head"><h2><?= e(__('roles.new')) ?></h2></div>
  <div class="grid-fields">
    <div class="field col-4"><label for="f_role_en"><?= e(__('roles.name_en')) ?> <span class="req">*</span></label><input id="f_role_en" type="text" name="name_en" maxlength="80" required></div>
    <div class="field col-4"><label for="f_role_ar"><?= e(__('roles.name_ar')) ?></label><input id="f_role_ar" type="text" name="name_ar" maxlength="80" dir="rtl"></div>
    <div class="field col-4"><label for="f_role_copy"><?= e(__('roles.copy_label')) ?></label><select id="f_role_copy" name="copy_from"><option value="0"><?= e(__('roles.start_empty')) ?></option><?php foreach ($roles as $r): if ($r['slug'] === 'owner') { continue; } ?><option value="<?= (int) $r['id'] ?>"><?= e(__('roles.copy_of', ['name' => loc($r, 'name')])) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('roles.create')) ?></button></div>
</form>
