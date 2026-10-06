<?php use App\Core\View; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_breeding')) ?></div><h1><?= e(__('site.breeding_title')) ?></h1><p><?= e(__('site.breeding_intro')) ?></p></div></section>
<section class="section"><div class="container">
  <div class="section-head"><div><h2><?= e(__('site.stallions_at_stud')) ?></h2><p><?= e(__('site.fees_on_request')) ?></p></div></div>
  <?php if ($stallions): ?><div class="horse-grid"><?php foreach ($stallions as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?></div><?php else: ?><div class="empty-note"><?= e(__('site.stud_soon')) ?></div><?php endif; ?>
  <div class="section-head" id="embryos" style="margin-top:70px"><div><h2><?= e(__('site.embryo_program')) ?></h2><p><?= e(__('site.embryo_intro')) ?></p></div></div>
  <?= View::partial('site/partials/embryo_list', ['embryos' => $embryos]) ?>
  <div class="contact" style="margin-top:60px"><div><h2 style="font-size:40px"><?= e(__('site.breeding_inquiry')) ?></h2><p><?= e(__('site.breeding_inquiry_text')) ?></p><?= View::partial('site/partials/contact_list') ?></div>
    <?= View::partial('site/partials/inquiry_form', ['type' => 'breeding']) ?></div>
</div></section>
