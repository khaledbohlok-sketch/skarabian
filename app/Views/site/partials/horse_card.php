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
  <span class="hcard-media">
    <?php if ($h['main_photo_id']): ?>
      <img src="<?= e(SiteData::img((int) $h['main_photo_id'], 'thumb')) ?>" alt="<?= e(loc($h) . ' – ' . horse_kind($h)) ?>" loading="lazy" decoding="async" width="480" height="600">
    <?php else: ?>
      <span class="hcard-ph" aria-hidden="true"><span class="ph-mark"></span><span class="ph-initials"><?= e(SiteData::initials(loc($h))) ?></span></span>
    <?php endif; ?>
    <?php if ($titles): ?><span class="hcard-medals" title="<?= e(__('site.c_titles')) ?>"><?= icon('star') ?> <?= $titles ?><span class="sr-only"> <?= e(__('site.c_titles')) ?></span></span><?php endif; ?>
  </span>
  <span class="hcard-body">
    <span class="hcard-name"><?= e(loc($h)) ?></span>
    <?php if ($other !== '' && $other !== loc($h)): ?><span class="hcard-other" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e($other) ?></span><?php endif; ?>
    <?php if ($parents): ?><span class="hcard-parents"><?= e(loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']]) ?: '—') ?> <span class="x" aria-hidden="true">×</span> <?= e(loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']]) ?: '—') ?></span><?php endif; ?>
    <span class="hcard-meta"><?= e(implode(' · ', $meta)) ?></span>
  </span>
</a>
