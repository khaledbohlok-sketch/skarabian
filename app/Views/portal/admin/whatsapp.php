<div class="page-head"><div><h1><?= e(__('settings.whatsapp')) ?></h1><p class="muted"><?= e(__('settings.whatsapp_intro')) ?></p></div></div>
<p><span class="pill <?= $connected ? 'ok' : 'warn' ?>"><?= e(__($connected ? 'settings.connected' : 'settings.not_connected')) ?></span></p>
<form method="post" class="card record-form" data-autosave="whatsapp-settings">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => false]) ?></div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
<section class="card"><h2><?= e(__('settings.wa_help_title')) ?></h2>
  <p class="muted small"><?= e(__('settings.wa_help_text')) ?></p>
</section>
