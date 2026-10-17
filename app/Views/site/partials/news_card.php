<?php
/** One news card (homepage and News page). $n = SiteData::news() row. */
use App\Services\SiteData;

?>
<a class="news-card" href="<?= e(site_url('news/' . $n['slug'])) ?>">
  <span class="news-media"><?php if ($n['image_id']): ?><img src="<?= e(SiteData::img((int) $n['image_id'], 'thumb')) ?>" alt="" loading="lazy" decoding="async" width="480" height="320"><?php else: ?><span class="hcard-ph" aria-hidden="true"><span class="ph-mark"></span></span><?php endif; ?></span>
  <span class="news-body">
    <time datetime="<?= e(substr((string) $n['published_at'], 0, 10)) ?>"><?= e(fmt_date($n['published_at'])) ?></time>
    <span class="news-title"><?= e(loc($n, 'title')) ?></span>
    <?php if ($sum = trim((string) loc($n, 'summary'))): ?><span class="news-sum"><?= e(mb_strimwidth($sum, 0, 160, '…')) ?></span><?php endif; ?>
  </span>
</a>
