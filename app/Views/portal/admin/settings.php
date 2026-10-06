<div class="page-head"><div><h1><?= e(__('nav.settings')) ?></h1><p class="muted"><?= e(__('settings.intro')) ?></p></div></div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => !$canEdit]) ?></div>
  <?php if ($canEdit): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div><?php endif; ?>
</form>
<section class="card"><h2><?= e(__('settings.connections')) ?></h2>
  <ul class="alert-list">
    <li><span><?= e(__('settings.mail')) ?></span><span class="pill <?= $mail === 'log' ? 'warn' : 'ok' ?>"><?= e($mail) ?></span></li>
    <li><span><?= e(__('settings.whatsapp')) ?></span><span class="pill <?= $whatsapp ? 'ok' : 'warn' ?>"><?= e(__($whatsapp ? 'settings.connected' : 'settings.not_connected')) ?></span></li>
    <li><span><?= e(__('settings.drive')) ?></span><span class="pill <?= $drive ? 'ok' : 'warn' ?>"><?= e(__($drive ? 'settings.connected' : 'settings.not_connected')) ?></span></li>
  </ul>
  <p class="muted small"><?= e(__('settings.connections_help')) ?></p>
</section>
