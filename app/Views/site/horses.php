<?php use App\Core\View; ?>
<?= View::partial('site/partials/page_hero', ['crumbs' => [[__('site.nav_horses'), null]], 'eyebrow' => e(__('site.collection')), 'title' => __('site.our_horses'), 'lead' => __('site.horses_intro')]) ?>
<section class="chapter horses-page">
  <div class="container">
    <?php if ($groups): ?>
      <?= View::partial('site/partials/horse_groups', ['groups' => $groups, 'active' => $active]) ?>
    <?php else: ?>
      <div class="empty-note"><?= e(__('site.no_horses')) ?></div>
    <?php endif; ?>
  </div>
</section>
<?= View::partial('site/partials/cta_band') ?>
