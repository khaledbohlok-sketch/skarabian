<?php use App\Core\View; use App\Services\SiteData; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_about')) ?></div><h1><?= e(__('site.about_title')) ?></h1></div></section>
<section class="section"><div class="container">
  <div class="story"><div class="prose"><p><?= nl2br(e(setting('site.story_' . lang(), ''))) ?></p></div><blockquote>“<?= e(__('site.story_quote')) ?>”</blockquote></div>
  <div class="pillars"><?php for ($i = 1; $i <= 3; $i++): ?><div class="pillar"><span class="num">0<?= $i ?></span><h3><?= e(setting('site.pillar' . $i . '_title_' . lang(), '')) ?></h3><p><?= e(setting('site.pillar' . $i . '_text_' . lang(), '')) ?></p></div><?php endfor; ?></div>
  <?php if ($experts): ?><div class="section-head" style="margin-top:70px"><div><h2><?= e(__('site.our_experts')) ?></h2></div></div>
  <div class="experts"><?php foreach ($experts as $x): ?><div class="expert"><img src="<?= e(SiteData::img($x['photo_id'] ? (int) $x['photo_id'] : null, 'thumb')) ?>" alt="" loading="lazy"><h3><?= e(loc($x)) ?></h3><p><?= e(loc($x, 'public_title') ?: loc($x, 'position')) ?></p><small><?= e(loc($x, 'public_bio')) ?></small></div><?php endforeach; ?></div><?php endif; ?>
</div></section>
