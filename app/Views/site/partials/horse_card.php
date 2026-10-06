<?php use App\Services\SiteData; ?>
<a class="horse-card" href="<?= e(site_url('horses/' . $h['slug'])) ?>" data-cat="<?= e($h['category']) ?>">
  <img src="<?= e(SiteData::img($h['main_photo_id'] ? (int) $h['main_photo_id'] : null, 'thumb')) ?>" alt="<?= e(loc($h)) ?>" loading="lazy" width="480" height="640">
  <?php if (!empty($h['born_at_sk'])): ?><span class="flag"><?= e(__('site.born_at_sk_flag')) ?></span><?php endif; ?>
  <div class="info">
    <span class="cat"><?= e(__('horse.cat_' . $h['category'])) ?><?= $h['dob'] ? ' · ' . e(horse_age($h['dob'])) : '' ?></span>
    <h3><?= e(loc($h)) ?></h3>
    <?php if ($h['sire_en'] || $h['dam_en']): ?><span class="meta"><?= e(loc(['name_en' => $h['sire_en'], 'name_ar' => $h['sire_ar']]) ?: '—') ?> × <?= e(loc(['name_en' => $h['dam_en'], 'name_ar' => $h['dam_ar']]) ?: '—') ?></span><?php endif; ?>
  </div>
</a>
