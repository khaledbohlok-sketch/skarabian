<?php
/**
 * The title band of an inner page: breadcrumbs, an eyebrow, the title (its words rise, the last in gold), an italic
 * lead, then whatever the page adds ($extra: HTML the page built, already escaped). $bg: a photo behind it all (a
 * horse's profile). $crumbs = [[label, url|null], …] (Home is added); $cls = extra classes; $alt = [other-language name,
 * its lang]; $eyebrow and $extra are HTML (already escaped); $title and $lead are plain text.
 */
use App\Core\View;

$cls = $cls ?? '';
$bg = $bg ?? null;
?>
<section class="page-hero<?= $cls !== '' ? ' ' . e($cls) : '' ?><?= $bg ? ' has-photo' : '' ?>">
  <?php if ($bg): ?><div class="page-hero-bg" aria-hidden="true"><img src="<?= e($bg) ?>" alt="" fetchpriority="high" decoding="async" width="1600" height="1000" data-parallax="0.1"></div><?php endif; ?>
  <div class="container">
    <div class="rise" style="--d:0"><?= View::partial('site/partials/crumbs', ['items' => $crumbs ?? []]) ?></div>
    <?php if (!empty($eyebrow)): ?><span class="eyebrow eyebrow-line rise" style="--d:1"><?= $eyebrow ?></span><?php endif; ?>
    <h1 class="split split-now"><?= split_title((string) $title) ?></h1>
    <?php if (!empty($alt)): ?><p class="alt-name rise" style="--d:3" lang="<?= e($alt[1]) ?>"><?= e($alt[0]) ?></p><?php endif; ?>
    <?php if (!empty($lead)): ?><p class="accent lead rise" style="--d:3"><?= e($lead) ?></p><?php endif; ?>
    <?php if (!empty($extra)): ?><div class="rise" style="--d:4"><?= $extra ?></div><?php endif; ?>
    <span class="page-hero-rule rise" style="--d:5" aria-hidden="true"></span>
  </div>
</section>
