<div class="page-head"><div><h1><?= e(__('nav.contact_details')) ?></h1><p class="muted"><?= e(__('cms.contact_intro')) ?></p></div></div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => false]) ?></div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('cms.publish')) ?></button></div>
</form>
