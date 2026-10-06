<?php if (str_starts_with(current_path(), '/portal')): ?>
<div class="error-page"><h1>404</h1><p><?= e(__('common.not_found')) ?></p><p><a class="btn" href="<?= e(url('/portal')) ?>"><?= e(__('common.go_home')) ?></a></p></div>
<?php else: ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / 404</div><h1><?= e(__('site.not_found_title')) ?></h1><p><?= e(__('common.not_found')) ?></p></div></section>
<section class="section"><div class="container" style="display:flex;gap:12px;flex-wrap:wrap">
  <a class="btn btn-gold" href="<?= e(site_url('horses')) ?>"><?= e(__('site.meet_horses')) ?></a>
  <a class="btn btn-line" href="<?= e(site_url()) ?>"><?= e(__('common.go_home')) ?></a>
  <a class="btn btn-line" href="<?= e(site_url('contact')) ?>"><?= e(__('site.nav_contact')) ?></a>
</div></section>
<?php endif; ?>
