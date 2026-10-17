<?php use App\Core\View; use App\Services\SiteAssistant;
$status = $enabled ? ['ok', 'ai.on'] : ($hasKey ? ['warn', 'ai.off'] : ['warn', 'ai.no_key']); ?>
<div class="page-head"><div><h1><?= e(__('ai.title')) ?></h1><p class="muted"><?= e(__('ai.intro')) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/' . lang())) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('cms.view_site')) ?></a></div></div>
<div class="stat-grid">
  <div class="stat"><div class="k"><?= e(__('common.status')) ?></div><div class="v"><span class="pill <?= $status[0] ?>"><?= e(__($status[1])) ?></span></div></div>
  <div class="stat"><div class="k"><?= e(__('ai.today_translations')) ?></div><div class="v"><?= (int) ($today['ai_translate'] ?? 0) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('ai.today_chat')) ?></div><div class="v"><?= (int) ($today['ai_site_chat'] ?? 0) ?> <small class="muted">/ <?= SiteAssistant::siteCap() ?></small></div></div>
</div>
<form method="post" class="stack record-form">
  <?= csrf_field() ?>
  <section class="card"><h2><?= e(__('ai.key_title')) ?></h2><p class="muted small"><?= e(__('ai.key_help')) ?></p>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => false]) ?></div>
  </section>
  <section class="card"><h2><?= e(__('ai.chat_title')) ?></h2><p class="muted small"><?= e(__('ai.chat_help')) ?></p>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $chat, 'disabled' => false]) ?></div>
    <p class="muted small"><?= e(__($chatOn ? 'ai.chat_is_on' : 'ai.chat_is_off')) ?></p>
  </section>
  <div class="form-actions sticky-actions">
    <button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button>
    <button class="btn" type="submit" name="action" value="test"><?= icon('check') ?> <?= e(__('ai.save_test')) ?></button>
  </div>
</form>
<section class="card"><h2><?= e(__('ai.help_title')) ?></h2>
  <ol class="small">
    <li><?= e(__('ai.help_1')) ?></li>
    <li><?= e(__('ai.help_2')) ?></li>
    <li><?= e(__('ai.help_3')) ?></li>
    <li><?= e(__('ai.help_4')) ?></li>
  </ol>
  <h3><?= e(__('ai.privacy_title')) ?></h3>
  <p class="muted small"><?= e(__('ai.privacy')) ?></p>
</section>
