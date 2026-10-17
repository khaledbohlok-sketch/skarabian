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
$ghost = $ghost ?? '';              // the page's name in the other language, painted large behind the band
$nameplate = !empty($nameplate);    // a horse: the name as a masthead, its other-language name at parity beside it
$registry = $registry ?? '';        // HTML (escaped by the page): "Stallion · Grey · 2017 · by X out of Y"
?>
<section class="page-hero<?= $cls !== '' ? ' ' . e($cls) : '' ?><?= $bg ? ' has-photo' : '' ?><?= $nameplate ? ' has-nameplate' : '' ?>">
  <?php if ($bg): ?><div class="page-hero-bg" aria-hidden="true"><img src="<?= e($bg) ?>" alt="" fetchpriority="high" decoding="async" width="1600" height="1000" data-parallax="0.1"></div><?php endif; ?>
  <svg class="page-arch" viewBox="0 0 800 1000" preserveAspectRatio="none" aria-hidden="true"><path d="M40 1000V420C40 230 190 80 400 40C610 80 760 230 760 420V1000" pathLength="1" vector-effect="non-scaling-stroke"/><path class="inner" d="M54 1000V426C54 246 198 100 400 60C602 100 746 246 746 426V1000" pathLength="1" vector-effect="non-scaling-stroke"/></svg>
  <div class="container"<?= $ghost !== '' ? ' data-ghost="' . e($ghost) . '"' : '' ?>>
    <div class="rise" style="--d:0"><?= View::partial('site/partials/crumbs', ['items' => $crumbs ?? []]) ?></div>
    <?php if (!empty($eyebrow)): ?><span class="eyebrow eyebrow-line rise" style="--d:1"><?= $eyebrow ?></span><?php endif; ?>
    <?php if ($nameplate): ?>
    <div class="nameplate">
      <h1 class="split split-now" style="--chars:<?= mb_strlen((string) $title) ?>"><?= split_title((string) $title, false) ?></h1>
      <?php if (!empty($alt)): ?><span class="nameplate-rule rise" style="--d:3" aria-hidden="true"></span><p class="nameplate-alt rise" style="--d:3" lang="<?= e($alt[1]) ?>"><?= e($alt[0]) ?></p><?php endif; ?>
    </div>
    <?php if ($registry !== ''): ?><p class="registry rise" style="--d:3"><?= $registry ?></p><?php endif; ?>
    <?php else: ?>
    <h1 class="split split-now"><?= split_title((string) $title, true, !empty($italic) ? -1 : null) ?></h1>
    <?php if (!empty($alt)): ?><p class="alt-name rise" style="--d:3" lang="<?= e($alt[1]) ?>"><?= e($alt[0]) ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($lead)): ?><p class="accent lead rise" style="--d:3"><?= e($lead) ?></p><?php endif; ?>
    <?php if (!empty($extra)): ?><div class="rise" style="--d:4"><?= $extra ?></div><?php endif; ?>
    <svg class="orn orn-finial rise" style="--d:5" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#finial"></use></svg>
  </div>
</section>
