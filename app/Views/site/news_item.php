<?php use App\Services\SiteData; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url('news')) ?>"><?= e(__('site.nav_news')) ?></a> / <?= e(fmt_date($n['published_at'])) ?></div><h1><?= e(loc($n, 'title')) ?></h1><p><?= e(loc($n, 'summary')) ?></p></div></section>
<section class="section"><div class="container prose">
  <?php if ($n['image_id']): ?><img src="<?= e(SiteData::img((int) $n['image_id'])) ?>" alt="" style="border-radius:14px;margin-bottom:26px"><?php endif; ?>
  <?php foreach (preg_split('/\R{2,}/', (string) loc($n, 'body')) as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
</div></section>
