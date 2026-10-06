<div class="page-head"><div><h1><?= e(__('nav.roles')) ?></h1><p class="muted"><?= e(__('roles.intro')) ?></p></div></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('users.role')) ?></th><th class="num"><?= e(__('nav.users')) ?></th><th><?= e(__('roles.twofa')) ?></th><th><?= e(__('roles.horse_scope')) ?></th><th><?= e(__('roles.edit_window')) ?></th></tr></thead>
  <tbody><?php foreach ($roles as $r): ?><tr>
    <td><a href="<?= e(url('/portal/roles/' . $r['id'])) ?>"><b><?= e(loc($r, 'name')) ?></b></a></td><td class="num"><?= (int) $r['users'] ?></td>
    <td><?= $r['require_2fa'] ? '✓' : '—' ?></td><td><?= e(__('roles.scope_' . $r['horse_scope'])) ?></td><td><?= e(__('roles.window_' . $r['edit_window'])) ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<form method="post" class="card form-inline">
  <?= csrf_field() ?>
  <h2 style="flex-basis:100%;margin:0 0 6px"><?= e(__('roles.new')) ?></h2>
  <input type="text" name="name_en" maxlength="80" required placeholder="<?= e(__('roles.name_en')) ?>">
  <input type="text" name="name_ar" maxlength="80" dir="rtl" placeholder="<?= e(__('roles.name_ar')) ?>">
  <select name="copy_from"><option value="0"><?= e(__('roles.start_empty')) ?></option><?php foreach ($roles as $r): if ($r['slug'] === 'owner') { continue; } ?><option value="<?= (int) $r['id'] ?>"><?= e(__('roles.copy_of', ['name' => loc($r, 'name')])) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" type="submit"><?= e(__('roles.create')) ?></button>
</form>
