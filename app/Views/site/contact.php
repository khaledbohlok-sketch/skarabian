<?php use App\Core\View; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_contact')) ?></div><h1><?= e(__('site.contact_title')) ?></h1><p><?= e(__('site.contact_intro')) ?></p></div></section>
<section class="section"><div class="container contact">
  <div><?= View::partial('site/partials/contact_list') ?><iframe class="map" title="<?= e(__('site.map')) ?>" loading="lazy" referrerpolicy="no-referrer" src="https://www.google.com/maps?q=<?= e(rawurlencode((string) setting('company.map_query', 'Doha, Qatar'))) ?>&output=embed"></iframe></div>
  <?= View::partial('site/partials/inquiry_form', ['heading' => __('site.send_message')]) ?>
</div></section>
