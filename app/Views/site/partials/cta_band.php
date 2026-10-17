<?php
/** The closing band: "Interested in one of our horses?" with Enquire and WhatsApp. $enquire = where Enquire goes (optional). */
use App\Services\SiteData;

$wa = site_contact('whatsapp');
$enquire = $enquire ?? (SiteData::pageOn('contact') ? site_url('contact') . '#inquiry' : 'https://wa.me/' . $wa);
$ext = str_starts_with($enquire, 'https://');
?>
<section class="cta-band" aria-labelledby="h-cta">
  <div class="container cta-inner reveal">
    <svg class="orn orn-divider" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#divider"></use></svg>
    <span class="eyebrow eyebrow-line"><?= e(__('site.cta_eyebrow')) ?></span>
    <h2 id="h-cta" class="split"><?= split_title(__('site.cta_title')) ?></h2>
    <p><?= e(__('site.cta_text')) ?></p>
    <div class="cta-actions">
      <a class="btn btn-gold btn-lg" href="<?= e($enquire) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e(__('site.enquire')) ?></a>
      <a class="btn btn-line btn-lg" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> <?= e(__('nav.whatsapp')) ?></a>
    </div>
  </div>
</section>
