<section class="page-hero"><div class="container"><h1><?= e(__('verify.title')) ?></h1></div></section>
<section class="section"><div class="container"><div class="verify-card">
<?php if ($doc && !$doc['is_void']): ?>
  <p class="verify-ok">✓ <?= e(__('verify.genuine')) ?></p>
  <dl class="facts"><div><dt><?= e(__('verify.reference')) ?></dt><dd dir="ltr"><?= e($doc['ref_no']) ?></dd></div><div><dt><?= e(__('verify.type')) ?></dt><dd><?= e(__('studio.type_' . $doc['doc_type'])) ?></dd></div><div><dt><?= e(__('verify.date')) ?></dt><dd><?= e(fmt_date($doc['doc_date'])) ?></dd></div><div><dt><?= e(__('verify.issuer')) ?></dt><dd>SK Arabians</dd></div></dl>
  <p style="color:var(--muted);font-size:14px"><?= e(__('verify.note')) ?></p>
<?php elseif ($doc): ?>
  <p class="verify-bad">✕ <?= e(__('verify.void')) ?></p><p dir="ltr"><?= e($doc['ref_no']) ?></p>
<?php else: ?>
  <p class="verify-bad">✕ <?= e(__('verify.not_found')) ?></p><p style="color:var(--muted)"><?= e(__('verify.not_found_text')) ?></p>
<?php endif; ?>
</div></div></section>
