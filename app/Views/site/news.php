<?php use App\Services\SiteData; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_news')) ?></div><h1><?= e(__('site.news_title')) ?></h1></div></section>
<section class="section"><div class="container">
<?php if ($items): ?><div class="news-grid"><?php foreach ($items as $n): ?>
  <a class="news-card reveal" href="<?= e(site_url('news/' . $n['slug'])) ?>"><img src="<?= e(SiteData::img($n['image_id'] ? (int) $n['image_id'] : null, 'thumb')) ?>" alt="" loading="lazy"><div><time datetime="<?= e($n['published_at']) ?>"><?= e(fmt_date($n['published_at'])) ?></time><h3><?= e(loc($n, 'title')) ?></h3><p style="color:var(--muted);margin:0"><?= e(loc($n, 'summary')) ?></p></div></a>
<?php endforeach; ?></div><?php else: ?><div class="empty-note"><?= e(__('site.no_news')) ?></div><?php endif; ?>
</div></section>
