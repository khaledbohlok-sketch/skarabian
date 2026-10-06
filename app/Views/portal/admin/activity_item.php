<?php $pretty = fn ($j) => $j ? json_encode(json_decode($j, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—'; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/activity')) ?>">← <?= e(__('nav.activity')) ?></a><h1><?= e($title) ?></h1></div></div>
<?php if (!$intact): ?><div class="alert alert-error"><?= e(__('activity.tampered')) ?></div><?php endif; ?>
<section class="card"><dl class="dl dl-grid">
  <div><dt><?= e(__('common.date')) ?></dt><dd><?= e(fmt_date($a['created_at'], true)) ?></dd></div>
  <div><dt><?= e(__('activity.user')) ?></dt><dd><?= e($a['user_name'] ?? '—') ?></dd></div>
  <div><dt><?= e(__('activity.action')) ?></dt><dd><?= e($a['action']) ?></dd></div>
  <div><dt><?= e(__('activity.module')) ?></dt><dd><?= e($a['module'] ?? '—') ?></dd></div>
  <div><dt><?= e(__('activity.record')) ?></dt><dd><?= e(trim(($a['record_type'] ?? '') . ' #' . ($a['record_id'] ?? ''), ' #')) ?: '—' ?></dd></div>
  <div><dt>IP</dt><dd dir="ltr"><?= e($a['ip']) ?></dd></div>
  <div><dt><?= e(__('account.device')) ?></dt><dd><?= e($a['device']) ?></dd></div>
  <div class="wide"><dt><?= e(__('activity.summary')) ?></dt><dd><?= e($a['summary']) ?></dd></div>
</dl></section>
<div class="grid-2">
  <section class="card"><h2><?= e(__('activity.old')) ?></h2><pre class="json"><?= e($pretty($a['old_values'])) ?></pre></section>
  <section class="card"><h2><?= e(__('activity.new')) ?></h2><pre class="json"><?= e($pretty($a['new_values'])) ?></pre></section>
</div>
<p class="muted small" dir="ltr">hash <?= e(substr($a['hash'], 0, 16)) ?>… · prev <?= e(substr((string) $a['prev_hash'], 0, 16)) ?>…</p>
