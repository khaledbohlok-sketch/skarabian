<?php
use App\Core\View;
use App\Services\SiteData;
?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_breeding')) ?></div><h1><?= e(__('site.breeding_title')) ?></h1><p><?= e(__('site.breeding_intro')) ?></p></div></section>
<section class="section"><div class="container">
  <div class="counters" style="color:var(--navy)">
    <?php foreach (['foals' => 'site.c_foals', 'titles' => 'site.c_titles', 'shows' => 'site.c_shows', 'horses' => 'site.c_horses'] as $k => $l): ?>
      <div class="counter" style="border-color:var(--line)"><b data-count="<?= (int) $counters[$k] ?>" style="color:var(--gold)"><?= (int) $counters[$k] ?></b><span style="color:var(--muted)"><?= e(__($l)) ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if ($sires): ?>
  <div class="section-head" style="margin-top:30px"><div><h2><?= e(__('site.breeding_stallions')) ?></h2><p><?= e(__('site.sires_teaser')) ?></p></div></div>
  <div class="horse-grid"><?php foreach ($sires as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ($mares): ?>
  <div class="section-head" style="margin-top:70px"><div><h2><?= e(__('site.broodmares')) ?></h2><p><?= e(__('site.mares_teaser')) ?></p></div></div>
  <div class="horse-grid"><?php foreach ($mares as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ($foals): ?>
  <div class="section-head" style="margin-top:70px"><div><h2><?= e(__('site.latest_foals')) ?></h2><p><?= e(__('site.foals_teaser')) ?></p></div></div>
  <div class="foal-grid">
    <?php foreach ($foals as $f): ?>
      <a class="foal-card reveal" href="<?= e(site_url('horses/' . $f['slug'])) ?>">
        <img src="<?= e(SiteData::img($f['main_photo_id'] ? (int) $f['main_photo_id'] : null, 'thumb')) ?>" alt="<?= e(loc($f)) ?>" loading="lazy">
        <div><h3><?= e(loc($f)) ?></h3><p><?= e(loc(['name_en' => $f['sire_en'], 'name_ar' => $f['sire_ar']])) ?> × <?= e(loc(['name_en' => $f['dam_en'], 'name_ar' => $f['dam_ar']])) ?></p><p><?= e(fmt_date($f['dob'])) ?></p><span class="born-tag"><?= e(__('site.born_at_sk')) ?></span></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$sires && !$mares && !$foals): ?><div class="empty-note"><?= e(__('site.programme_soon')) ?></div><?php endif; ?>
</div></section>
