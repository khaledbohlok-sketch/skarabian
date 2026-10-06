<?php use App\Core\View; $emb = (int) ($_GET['embryo'] ?? 0); ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_for_sale')) ?></div><h1><?= e(__('site.for_sale_title')) ?></h1><p><?= e(__('site.for_sale_intro')) ?></p></div></section>
<section class="section"><div class="container">
  <div class="section-head"><div><h2><?= e(__('site.horses_for_sale')) ?></h2><p><?= e(__('site.price_on_request')) ?></p></div></div>
  <?php if ($horses): ?><div class="horse-grid"><?php foreach ($horses as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?></div><?php else: ?><div class="empty-note"><?= e(__('site.none_for_sale')) ?></div><?php endif; ?>
  <div class="section-head" style="margin-top:70px"><div><h2><?= e(__('site.embryos_available')) ?></h2></div></div>
  <?= View::partial('site/partials/embryo_list', ['embryos' => $embryos]) ?>
  <div class="contact" style="margin-top:60px"><div><h2 style="font-size:40px"><?= e(__('site.sales_inquiry')) ?></h2><?= View::partial('site/partials/contact_list') ?></div>
    <?= View::partial('site/partials/inquiry_form', ['type' => $emb ? 'embryo' : 'sale', 'embryoId' => $emb ?: null]) ?></div>
</div></section>
