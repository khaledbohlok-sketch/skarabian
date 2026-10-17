<?php if (str_starts_with(current_path(), '/portal')): ?>
<div class="error-page"><h1><?= e(__('site.not_found_title')) ?></h1><p><?= e(__('common.not_found')) ?></p><p><a class="btn btn-primary" href="<?= e(url('/portal')) ?>"><?= e(__('common.go_home')) ?></a></p></div>
<?php else: ?>
<section class="error-section">
  <span class="ph-fx" aria-hidden="true"></span>
  <div class="container">
    <div class="error-card rise">
      <span class="error-code gold-text" aria-hidden="true">404</span>
      <h1><?= e(__('site.not_found_title')) ?></h1>
      <p><?= e(__('common.not_found')) ?></p>
      <div class="error-actions">
        <a class="btn btn-gold" href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a>
        <?php if (\App\Services\SiteData::pageOn('contact')): ?><a class="btn btn-ghost" href="<?= e(site_url('contact')) ?>"><?= e(__('site.nav_contact')) ?></a><?php endif; ?>
      </div>
      <p class="error-more"><a class="link-arrow" href="<?= e(site_url('horses')) ?>"><?= e(__('site.meet_horses')) ?> <span aria-hidden="true"><?= arrow('to') ?></span></a></p>
    </div>
  </div>
</section>
<?php endif; ?>
