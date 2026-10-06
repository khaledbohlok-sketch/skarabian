<?php
use App\Core\View;
use App\Services\SiteData;
$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
$imgs = array_values(array_filter($photos, fn ($p) => str_starts_with($p['mime'], 'image/')));
$videos = array_values(array_filter($photos, fn ($p) => $p['mime'] === 'video/mp4'));
$yt = null;
if ($h['video_url'] && preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $h['video_url'], $m)) { $yt = $m[1]; }
?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <a href="<?= e(site_url('horses')) ?>"><?= e(__('site.nav_horses')) ?></a></div>
  <h1><?= e(loc($h)) ?></h1><p><?= e(lang() === 'ar' ? $h['name_en'] : (string) $h['name_ar']) ?></p></div></section>
<section class="section"><div class="container">
  <div class="profile-top">
    <div data-gallery>
      <div class="pgallery-main"><img src="<?= e(SiteData::img($imgs ? (int) $imgs[0]['id'] : null)) ?>" alt="<?= e(loc($h)) ?>" data-gallery-main></div>
      <?php if (count($imgs) > 1): ?><div class="pgallery-thumbs"><?php foreach ($imgs as $i => $p): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e(SiteData::img((int) $p['id'])) ?>"><img src="<?= e(SiteData::img((int) $p['id'], 'thumb')) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div>
      <span class="eyebrow" style="color:var(--gold)"><?= e(__('horse.cat_' . $h['category'])) ?></span>
      <dl class="facts">
        <div><dt><?= e(__('horse.sex')) ?></dt><dd><?= e(__('horse.sex_' . $h['sex'])) ?></dd></div>
        <div><dt><?= e(__('site.age')) ?></dt><dd><?= e($h['dob'] ? horse_age($h['dob']) . ' (' . substr($h['dob'], 0, 4) . ')' : '—') ?></dd></div>
        <div><dt><?= e(__('horse.color')) ?></dt><dd><?= e(loc(['value_en' => $h['color_en'], 'value_ar' => $h['color_ar']], 'value') ?: '—') ?></dd></div>
        <div><dt><?= e(__('horse.breed')) ?></dt><dd><?= e(loc(['value_en' => $h['breed_en'], 'value_ar' => $h['breed_ar']], 'value') ?: '—') ?></dd></div>
        <div><dt><?= e(__('horse.sire')) ?></dt><dd><?php if ($h['sire_slug'] && $h['sire_public']): ?><a href="<?= e(site_url('horses/' . $h['sire_slug'])) ?>"><?= e(loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']])) ?></a><?php else: ?><?= e(loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']]) ?: '—') ?><?php endif; ?></dd></div>
        <div><dt><?= e(__('horse.dam')) ?></dt><dd><?php if ($h['dam_slug'] && $h['dam_public']): ?><a href="<?= e(site_url('horses/' . $h['dam_slug'])) ?>"><?= e(loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']])) ?></a><?php else: ?><?= e(loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']]) ?: '—') ?><?php endif; ?></dd></div>
        <?php if ($h['bloodline']): ?><div><dt><?= e(__('horse.bloodline')) ?></dt><dd><?= e($h['bloodline']) ?></dd></div><?php endif; ?>
        <?php if ($h['breeder']): ?><div><dt><?= e(__('horse.breeder')) ?></dt><dd><?= e($h['breeder']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($story = loc($h, 'story')): ?><div class="prose"><?php foreach (preg_split('/\R{2,}/', $story) as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?></div><?php endif; ?>
      <a class="btn btn-gold" href="#inquiry"><?= e(__('site.inquire_horse')) ?></a>
    </div>
  </div>

  <?php if ($yt || $videos): ?>
  <div style="margin-top:50px"><h2 style="font-size:36px"><?= e(__('site.video')) ?></h2>
    <div class="video-embed"><?php if ($yt): ?><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="<?= e(loc($h)) ?>" allowfullscreen loading="lazy"></iframe><?php else: ?><video controls preload="metadata" src="<?= e(url('/media/' . $videos[0]['id'] . '/full')) ?>"></video><?php endif; ?></div>
  </div>
  <?php endif; ?>

  <div style="margin-top:56px"><h2 style="font-size:40px"><?= e(__('site.pedigree')) ?></h2><?= View::partial('site/partials/pedigree', ['ped' => $pedigree, 'gens' => 4]) ?></div>

  <?php if ($results): ?>
  <div style="margin-top:56px"><h2 style="font-size:40px"><?= e(__('site.show_results')) ?></h2>
    <div style="overflow-x:auto"><table class="results-table"><thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('shows.show')) ?></th><th><?= e(__('shows.class')) ?></th><th><?= e(__('shows.result')) ?></th></tr></thead><tbody>
    <?php foreach ($results as $r): ?><tr><td><?= e(fmt_date($r['start_date'])) ?></td><td><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?></td><td><?= e($r['class_name']) ?></td>
      <td><?php if ($r['medal'] !== 'none'): ?><span class="medal <?= e($medalCls[$r['medal']]) ?>" style="display:inline-grid;width:22px;height:22px;font-size:11px;vertical-align:middle">★</span> <?php endif; ?><strong><?= e(loc($r, 'title') ?: ($r['placing'] ? '#' . $r['placing'] : '')) ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <?php endif; ?>

  <?php if ($offspring): ?>
  <div style="margin-top:56px"><h2 style="font-size:40px"><?= e(__('site.offspring')) ?></h2>
    <div class="foal-grid"><?php foreach ($offspring as $o): ?><a class="foal-card" href="<?= e(site_url('horses/' . $o['slug'])) ?>"><img src="<?= e(SiteData::img($o['main_photo_id'] ? (int) $o['main_photo_id'] : null, 'thumb')) ?>" alt="" loading="lazy"><div><h3><?= e(loc($o)) ?></h3><p><?= e(__('horse.cat_' . $o['category'])) ?> · <?= e(substr((string) $o['dob'], 0, 4)) ?></p></div></a><?php endforeach; ?></div></div>
  <?php endif; ?>

  <div class="contact" style="margin-top:60px">
    <div><h2 style="font-size:40px"><?= e(__('site.inquire_horse')) ?></h2><p><?= e(__('site.inquire_horse_text', ['name' => loc($h)])) ?></p><?= View::partial('site/partials/contact_list') ?></div>
    <?= View::partial('site/partials/inquiry_form', ['horseId' => $h['id'], 'type' => 'horse', 'prefill' => __('site.inquiry_prefill', ['name' => loc($h)])]) ?>
  </div>
</div></section>
