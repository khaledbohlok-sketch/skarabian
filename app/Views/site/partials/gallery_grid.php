<?php
/**
 * The photo gallery: an editorial grid (some frames span two columns); a photo opens in the photo viewer (site.js), a
 * video link opens in a new tab. Without JavaScript a photo still links where it did (the horse's page, or the photo).
 * $lead = true (homepage): the large frames load the full-size image instead of the thumbnail.
 */
use App\Services\SiteData;

$lead = !empty($lead);
?>
<ul class="gallery" data-lb-group data-cursor="open">
  <?php foreach ($gallery as $gi => $g):
    $alt = trim((string) loc($g, 'caption')) ?: __('site.gallery_photo');
    $full = SiteData::img($g['file_id'] ? (int) $g['file_id'] : null);
    $big = $lead && ($gi % 6 === 0 || $gi % 6 === 3);
    $href = $g['video_url'] ?: ($g['slug'] ? site_url('horses/' . $g['slug']) : $full); ?>
    <li><a<?= $big ? ' class="framed"' : '' ?> href="<?= e($href) ?>"<?= $g['video_url'] ? ' target="_blank" rel="noopener"' : ' data-lb="' . e($full) . '" data-caption="' . e($alt) . '"' . ($g['slug'] ? ' data-link="' . e(site_url('horses/' . $g['slug'])) . '"' : '') ?>>
      <?php if ($big): foreach (['tl', 'tr', 'bl', 'br'] as $c): ?><svg class="orn-corner <?= $c ?>" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#corner"></use></svg><?php endforeach; endif; ?>
      <img src="<?= e(SiteData::img($g['file_id'] ? (int) $g['file_id'] : null, $big ? 'full' : 'thumb')) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async" width="600" height="600">
      <?php if ($g['video_url']): ?><span class="play" aria-hidden="true"><span><?= icon('play') ?></span></span><?php endif; ?>
      <span class="cap" aria-hidden="true"><?= e($alt) ?></span>
    </a></li>
  <?php endforeach; ?>
</ul>
