<?php
/**
 * Public check of a document or printout (QR code): one centred card with the result — Valid (green check),
 * Cancelled (red, with its message) or Not found (red). There is no expiry date in what is checked, so no "Expired".
 */
if ($doc && !$doc['is_void']) { $state = 'ok'; }
elseif ($doc || (!empty($printout) && !empty($printout['is_void']))) { $state = 'void'; }
elseif (!empty($printout)) { $state = 'ok'; }
else { $state = 'bad'; }
$badge = ['ok' => 'site.verify_valid', 'void' => 'site.verify_cancelled', 'bad' => 'site.verify_invalid'][$state];
?>
<section class="verify-page">
  <span class="ph-fx" aria-hidden="true"></span>
  <div class="container">
    <div class="verify-card verify-<?= $state ?>">
      <p class="verify-kicker"><?= e(__('verify.title')) ?></p>
      <span class="verify-icon" aria-hidden="true"><?= $state === 'ok' ? icon('check') : icon('x') ?></span>
      <p class="verify-state"><?= e(__($badge)) ?></p>
<?php if ($doc && !$doc['is_void']): ?>
      <h1><?= e(__('verify.genuine')) ?></h1>
      <dl class="facts"><div><dt><?= e(__('verify.reference')) ?></dt><dd dir="ltr"><?= e($doc['ref_no']) ?></dd></div><div><dt><?= e(__('verify.type')) ?></dt><dd><?= e(__('studio.type_' . $doc['doc_type'])) ?></dd></div><div><dt><?= e(__('verify.date')) ?></dt><dd><?= e(fmt_date($doc['doc_date'])) ?></dd></div><div><dt><?= e(__('verify.issuer')) ?></dt><dd>SK Arabians</dd></div></dl>
      <p class="verify-note"><?= e(__('verify.note')) ?></p>
<?php elseif ($doc): ?>
      <h1><?= e(__('verify.void')) ?></h1>
      <dl class="facts"><div><dt><?= e(__('verify.reference')) ?></dt><dd dir="ltr"><?= e($doc['ref_no']) ?></dd></div></dl>
<?php elseif (!empty($printout) && !empty($printout['is_void'])): ?>
      <h1><?= e(__('verify.printout_void')) ?></h1>
      <dl class="facts"><div><dt><?= e(__('verify.reference')) ?></dt><dd dir="ltr"><?= e($printout['ref_no']) ?></dd></div></dl>
<?php elseif (!empty($printout)): ?>
      <h1><?= e(__('verify.printout_genuine')) ?></h1>
      <dl class="facts">
        <div><dt><?= e(__('verify.reference')) ?></dt><dd dir="ltr"><?= e($printout['ref_no']) ?></dd></div>
        <div><dt><?= e(__('verify.printout_of')) ?></dt><dd><?= e($printout['title']) ?></dd></div>
        <div><dt><?= e(__('verify.printed_on')) ?></dt><dd><?= e(fmt_date($printout['printed_at'], true)) ?></dd></div>
        <div><dt><?= e(__('verify.records')) ?></dt><dd class="num"><?= e(number_format((int) $printout['row_count'])) ?></dd></div>
        <div><dt><?= e(__('verify.issuer')) ?></dt><dd>SK Arabians</dd></div>
      </dl>
      <p class="verify-note"><?= e(__('verify.printout_note')) ?></p>
      <p><a class="btn btn-ghost" href="<?= e(url('/portal/printouts/' . $token)) ?>" rel="nofollow"><?= e(__('verify.staff_open')) ?></a></p>
<?php else: ?>
      <h1><?= e(__('verify.not_found')) ?></h1>
      <p class="verify-note"><?= e(__('verify.not_found_text')) ?></p>
<?php endif; ?>
    </div>
  </div>
</section>
