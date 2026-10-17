<?php
/**
 * One horse card, the same for every category and page: a 4:5 photo (the SK mark and initials until a photo is added),
 * the name, the other-language name, sire × dam, category · age · colour, and the medal count (no sale status: the
 * stud does not sell horses).
 */
use App\Services\SiteData;

$other = lang() === 'ar' ? (string) $h['name_en'] : (string) $h['name_ar'];
$parents = SiteData::show('card_parents') && ($h['sire_en'] || $h['dam_en']);
$titles = SiteData::show('card_titles') ? (int) ($h['titles'] ?? 0) : 0;
$meta = [__('horse.cat_' . $h['category'])];
if ($h['dob'] && SiteData::show('profile_age')) { $meta[] = horse_age($h['dob']); }
if (SiteData::show('profile_colour') && !empty($h['color_en'])) { $meta[] = loc(['name_en' => $h['color_en'], 'name_ar' => $h['color_ar'] ?? null]); }
?>
<a class="hcard" href="<?= e(site_url('horses/' . $h['slug'])) ?>" data-cat="<?= e($h['category']) ?>">
  <span class="hcard-frame">
  <span class="hcard-media">
    <?php if ($h['main_photo_id']): ?>
      <img src="<?= e(SiteData::img((int) $h['main_photo_id'], 'thumb')) ?>" alt="<?= e(loc($h) . ' – ' . horse_kind($h)) ?>" loading="lazy" decoding="async" width="480" height="600">
    <?php else: // the bookplate: a double gold frame, the seal turned a little, the monogram, the other-language name, the category up the side ?>
      <span class="hcard-ph plate" aria-hidden="true" style="--rot:<?= ((int) $h['id'] * 37) % 360 ?>deg"><span class="ph-mark"></span><svg class="ph-seal" viewBox="0 0 100 100"><use href="#seal"></use></svg><span class="ph-initials foil"><?= e(SiteData::initials(loc($h))) ?></span><?php if ($other !== '' && $other !== loc($h)): ?><span class="ph-name" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e($other) ?></span><?php endif; ?><span class="ph-side"><?= e(__('horse.cat_' . $h['category'])) ?></span></span>
    <?php endif; ?>
    <span class="sheen" aria-hidden="true"></span>
  </span>
  <svg class="hcard-arch" viewBox="0 0 400 500" preserveAspectRatio="none" aria-hidden="true"><path class="rim" d="M0 500V110C0 45 80 10 200 0C320 10 400 45 400 110V500" vector-effect="non-scaling-stroke"/><path class="draw" d="M14 500V116C14 58 88 24 200 14C312 24 386 58 386 116V500" pathLength="1" vector-effect="non-scaling-stroke"/></svg>
  <?php if ($titles): ?><span class="hcard-medals" title="<?= e(__('site.c_titles')) ?>"><?= icon('star') ?> <?= $titles ?><span class="sr-only"> <?= e(__('site.c_titles')) ?></span></span><?php endif; ?>
  </span>
  <span class="hcard-body">
    <span class="hcard-name"><?= e(loc($h)) ?></span>
    <?php if ($other !== '' && $other !== loc($h)): ?><span class="hcard-other" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e($other) ?></span><?php endif; ?>
    <?php if ($parents): ?><span class="hcard-parents"><?= e(loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']]) ?: '—') ?> <svg class="orn orn-rosette x" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg> <?= e(loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']]) ?: '—') ?></span><?php endif; ?>
    <span class="hcard-meta"><?= e(implode(' · ', $meta)) ?></span>
  </span>
</a>
