<?php use App\Core\View; ?>
<?php $bandPhoto = null; foreach ($groups as $list) { foreach ($list as $x) { if ($x['main_photo_id']) { $bandPhoto = \App\Services\SiteData::img((int) $x['main_photo_id']); break 2; } } } ?>
<?= View::partial('site/partials/page_hero', ['bg' => $bandPhoto, 'cls' => 'band-photo', 'crumbs' => [[__('site.nav_horses'), null]], 'eyebrow' => e(__('site.collection')), 'title' => __('site.our_horses'), 'lead' => __('site.horses_intro'), 'ghost' => t_other('site.our_horses'), 'italic' => true]) ?>
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
