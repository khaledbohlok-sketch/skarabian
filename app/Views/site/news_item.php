<?php
use App\Core\View;
use App\Services\SiteData;

$dateHtml = '<time datetime="' . e(substr((string) $n['published_at'], 0, 10)) . '">' . e(fmt_date($n['published_at'])) . '</time>';
?>
<?= View::partial('site/partials/page_hero', ['crumbs' => [[__('site.nav_news'), site_url('news')], [loc($n, 'title'), null]], 'cls' => 'article-hero', 'eyebrow' => $dateHtml,
    'title' => (string) loc($n, 'title'), 'lead' => trim((string) loc($n, 'summary'))]) ?>
<section class="chapter">
  <div class="container article">
    <?php if ($n['image_id']): ?><a class="article-media wipe reveal" href="<?= e(SiteData::img((int) $n['image_id'])) ?>" data-lb="<?= e(SiteData::img((int) $n['image_id'])) ?>" data-caption="<?= e(loc($n, 'title')) ?>"><img src="<?= e(SiteData::img((int) $n['image_id'])) ?>" alt="<?= e(loc($n, 'title')) ?>" decoding="async" fetchpriority="high" width="1200" height="750"></a><?php endif; ?>
    <div class="prose">
      <?php foreach (preg_split('/\R{2,}/', (string) loc($n, 'body')) as $p): if (trim($p) === '') { continue; } ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
    </div>
    <p class="article-back"><a class="link-arrow back" href="<?= e(site_url('news')) ?>"><span aria-hidden="true"><?= arrow('back') ?></span> <?= e(__('site.all_news')) ?></a></p>
  </div>
</section>
<?= View::partial('site/partials/cta_band') ?>
