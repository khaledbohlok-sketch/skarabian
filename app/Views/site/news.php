<?php use App\Core\View; ?>
<?= View::partial('site/partials/page_hero', ['crumbs' => [[__('site.nav_news'), null]], 'eyebrow' => e(__('site.news_eyebrow')), 'title' => __('site.news_title'), 'lead' => __('site.meta_news')]) ?>
<section class="chapter news-page">
  <div class="container">
    <?php if ($items): ?>
      <div class="news-grid lead-first stagger reveal"><?php foreach ($items as $n): ?><?= View::partial('site/partials/news_card', ['n' => $n]) ?><?php endforeach; ?></div>
    <?php else: ?>
      <div class="empty-note"><?= e(__('site.no_news')) ?></div>
    <?php endif; ?>
  </div>
</section>
<?= View::partial('site/partials/cta_band') ?>
