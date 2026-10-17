<?php use App\Core\View; ?>
<?= View::partial('site/partials/page_hero', ['crumbs' => [[__('site.nav_contact'), null]], 'eyebrow' => e(__('site.get_in_touch')), 'title' => __('site.contact_title'), 'lead' => __('site.contact_intro')]) ?>
<section class="chapter">
  <div class="container contact">
    <div class="contact-info reveal">
      <span class="chapter-index"><b>01</b> <?= e(__('site.location')) ?></span>
      <h2 class="split"><?= split_title(__('site.visit_us'), false) ?></h2>
      <p class="lead"><?= e(__('site.cta_text')) ?></p>
      <?= View::partial('site/partials/contact_list') ?>
      <div class="map-frame"><iframe class="map" title="<?= e(__('site.map')) ?>" loading="lazy" referrerpolicy="no-referrer" allowfullscreen src="https://www.google.com/maps?q=<?= e(rawurlencode((string) setting('company.map_query', 'Doha, Qatar'))) ?>&amp;output=embed"></iframe></div>
    </div>
    <div class="reveal"><?= View::partial('site/partials/inquiry_form', ['heading' => __('site.send_message')]) ?></div>
  </div>
</section>
