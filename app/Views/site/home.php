<?php
/**
 * Homepage (version 3). The hero, then the ledger strip, then the chapters chosen and ordered in Website → Homepage &
 * texts (a chapter with nothing to show is left out), then the closing "Enquire / WhatsApp" band. Horses lead: every
 * horse on the website, grouped by category. "Latest foals" and "Bloodlines" only regroup horses already on the
 * website; never breeding plans, pregnancies or embryos, and no testimonials.
 *
 * Look and motion ("Maison"): site.css, site-pages.css → Pages, site.js. Everything is readable without JavaScript and
 * with reduced motion; the effects only add to markup that already works.
 */
use App\Core\View;
use App\Services\SiteData;

$sections = $sections ?? SiteData::homeSections();
$foals = $foals ?? [];
$bloodlines = $bloodlines ?? [];
$video = (string) setting('site.hero_video_url', '');
$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
$stats = array_filter(['horses' => 'site.c_horses', 'titles' => 'site.c_titles'], fn ($k) => (int) $counters[$k] > 0, ARRAY_FILTER_USE_KEY);
if ((int) $counters['horses'] < 3 && (int) $counters['titles'] === 0) { $stats = []; }
$wa = site_contact('whatsapp');
$enquire = in_array('contact', $sections, true) ? '#inquiry' : (SiteData::pageOn('contact') ? site_url('contact') . '#inquiry' : 'https://wa.me/' . $wa);
$enquireExt = str_starts_with($enquire, 'https://');
$horseName = fn (array $r) => loc(['name_en' => $r['horse_en'], 'name_ar' => $r['horse_ar']]);
$story = trim((string) setting('site.story_' . lang(), ''));
$pillars = [];
for ($i = 1; $i <= 3; $i++) {
    if ($t = trim((string) setting('site.pillar' . $i . '_title_' . lang(), ''))) {
        $pillars[] = [$i, $t, trim((string) setting('site.pillar' . $i . '_text_' . lang(), ''))];
    }
}
$heroImgs = $video && preg_match('#\.(mp4|webm)$#i', $video) ? [] : $heroPhotos;
$slideData = fn (array $s) => ' data-name="' . e(loc($s)) . '" data-cat="' . e(__('horse.cat_' . $s['category'])) . '" data-href="' . e(site_url('horses/' . $s['slug'])) . '"';
$first = $heroImgs[0] ?? null;
$latest = $stats ? ($medals[0] ?? null) : null;
$heroTitle = (string) setting('site.hero_title_' . lang(), __('site.tagline'));
$heroSub = (string) setting('site.hero_sub_' . lang(), '');
// sire × dam as a lineage line: "by SIRE out of DAM"
$lineage = function (array $h): string {
    $name = fn (?string $en, ?string $ar) => e(loc(['name_en' => $en, 'name_ar' => $ar]) ?: '—');
    return '<p class="lineage"><span class="by">' . e(__('site.by')) . '</span> <b>' . $name($h['sire_en'], $h['sire_ar']) . '</b> <span class="by">' . e(__('site.out_of')) . '</span> <b>' . $name($h['dam_en'], $h['dam_ar']) . '</b></p>';
};
$n = 0; // chapter index: each chapter head does $n++ first, then prints the index and the ghost numeral behind its title
$index = function (string $label) use (&$n): string {
    return '<span class="chapter-index"><b>' . sprintf('%02d', $n) . '</b> ' . e($label) . '</span>';
};
$ghost = fn (string $key): string => ' data-ghost="' . e(t_other($key)) . '"'; // the chapter's name in the other language, painted large behind the title
$orn = fn (string $id, string $cls = '') => '<svg class="orn ' . $cls . '" aria-hidden="true"><use href="' . e(asset('img/ornaments.svg')) . '#' . $id . '"></use></svg>';
$arch = '<svg class="hcard-arch" viewBox="0 0 400 500" preserveAspectRatio="none" aria-hidden="true"><path class="rim" d="M0 500V110C0 45 80 10 200 0C320 10 400 45 400 110V500" vector-effect="non-scaling-stroke"/><path class="draw" d="M14 500V116C14 58 88 24 200 14C312 24 386 58 386 116V500" pathLength="1" vector-effect="non-scaling-stroke"/></svg>';
$medalOf = fn (array $r) => $medalCls[$r['medal']] ?? 'medal-plain';
?>
<div class="home">
<section class="hero<?= $heroPhotos || $video ? ' has-media' : '' ?>" aria-labelledby="hero-title" data-hero>
  <div class="hero-media" data-slideshow>
    <?php if ($video && preg_match('#\.(mp4|webm)$#i', $video)): ?>
      <video autoplay muted loop playsinline<?= $heroPhotos ? ' poster="' . e(SiteData::img((int) $heroPhotos[0]['main_photo_id'])) . '"' : '' ?>><source src="<?= e($video) ?>"></video>
    <?php else: foreach ($heroImgs as $i => $s): ?>
      <?php if ($i === 0): ?><img class="hero-slide on" src="<?= e(SiteData::img((int) $s['main_photo_id'])) ?>" alt="<?= e(loc($s)) ?>"<?= $slideData($s) ?> fetchpriority="high" decoding="async" width="1600" height="1000">
      <?php else: ?><img class="hero-slide" data-src="<?= e(SiteData::img((int) $s['main_photo_id'])) ?>" alt="<?= e(loc($s)) ?>"<?= $slideData($s) ?> decoding="async" width="1600" height="1000"><?php endif; ?>
    <?php endforeach; endif; ?>
  </div>
  <div class="hero-veil" aria-hidden="true"></div>
  <div class="hero-light" aria-hidden="true"></div>
  <div class="hero-grain" aria-hidden="true"></div>
  <canvas class="hero-dust" aria-hidden="true" data-dust></canvas>
  <div class="hero-sink" aria-hidden="true" data-hero-sink></div>
  <svg class="hero-frame" aria-hidden="true" viewBox="0 0 100 100" preserveAspectRatio="none"><rect x=".5" y=".5" width="99" height="99" pathLength="1" vector-effect="non-scaling-stroke"/></svg>
  <span class="hero-ghost" aria-hidden="true" dir="ltr" lang="en">SK Arabians</span>
  <span class="hero-folio start hero-in" style="--d:1" aria-hidden="true"><?= e(__('site.doha_qatar')) ?> · <span dir="ltr">25.28° N</span></span>
  <span class="hero-folio end hero-in" style="--d:1" aria-hidden="true"><?= e(__('site.est', ['year' => setting('site.established', '2025')])) ?></span>
  <div class="container hero-inner" data-hero-parallax>
    <?php // the gate: a pointed arch in gold hairline draws itself up around the title after the doors part ?>
    <svg class="hero-arch" viewBox="0 0 800 1000" preserveAspectRatio="none" aria-hidden="true"><defs><radialGradient id="keystone"><stop offset="0" stop-color="currentColor" stop-opacity=".55"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></radialGradient></defs><ellipse class="bloom" cx="400" cy="46" rx="150" ry="110" fill="url(#keystone)"/><path class="gate" d="M40 1000V420C40 230 190 80 400 40C610 80 760 230 760 420V1000" pathLength="1" vector-effect="non-scaling-stroke"/><path class="gate inner" d="M54 1000V426C54 246 198 100 400 60C602 100 746 246 746 426V1000" pathLength="1" vector-effect="non-scaling-stroke"/></svg>
    <div class="hero-copy">
      <span class="hero-arabic hero-in" style="--d:0" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>" dir="<?= lang() === 'ar' ? 'ltr' : 'rtl' ?>"><?= e(t_other('site.brand')) ?></span>
      <span class="eyebrow eyebrow-line hero-in" style="--d:0"><?= e(__('site.hero_eyebrow', ['year' => setting('site.established', '2025')])) ?></span>
      <h1 id="hero-title" class="split split-now cover"><?= split_title($heroTitle, true, null, true) ?></h1>
      <?php if ($heroSub !== ''): ?><p class="accent hero-in" style="--d:3"><?= e($heroSub) ?></p><?php endif; ?>
      <div class="hero-actions hero-in" style="--d:4">
        <a class="btn btn-gold btn-lg" href="<?= e($enquire) ?>"<?= $enquireExt ? ' target="_blank" rel="noopener"' : '' ?> data-magnetic><?= e(__('site.enquire')) ?></a>
        <a class="link-arrow down" href="<?= in_array('horses', $sections, true) && $groups ? '#horses' : e(site_url('horses')) ?>"><?= e(__('site.meet_horses')) ?> <?= icon('arrow-down') ?></a>
      </div>
    </div>
    <?php if ($first): ?>
    <div class="hero-foot hero-in" style="--d:5">
      <a class="hero-caption" href="<?= e(site_url('horses/' . $first['slug'])) ?>" data-hero-caption>
        <span class="hero-caption-cat"><?= e(__('horse.cat_' . $first['category'])) ?></span>
        <strong class="hero-caption-name"><?= e(loc($first)) ?></strong>
        <?= icon('arrow-up-right') ?>
      </a>
      <?php if (count($heroImgs) > 1): ?><span class="hero-dots" aria-hidden="true"><?php foreach ($heroImgs as $i => $s): ?><i<?= $i === 0 ? ' class="on"' : '' ?>></i><?php endforeach; ?></span><span class="hero-progress" aria-hidden="true" data-hero-progress><i></i></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <a class="hero-scroll seal" href="#home-start" aria-label="<?= e(__('site.scroll')) ?>"><svg viewBox="0 0 100 100" aria-hidden="true"><use href="#seal"></use></svg><img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="20" height="30"></a>
  <?php $tick = array_slice($medals, 0, 10); if (count($tick) >= 3): // the titles, passing along the bottom of the hero (twice, for a seamless loop) ?>
  <div class="ticker" aria-label="<?= e(__('site.c_titles')) ?>">
    <div class="ticker-track">
      <?php for ($pass = 0; $pass < 2; $pass++): foreach ($tick as $r): ?>
        <span class="ticker-item"<?= $pass ? ' aria-hidden="true"' : '' ?>><?= $orn('rosette', 'orn-rosette') ?><b><?= e($horseName($r)) ?> · <?= e(SiteData::resultText($r)) ?></b><span><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?> · <?= e(substr((string) $r['start_date'], 0, 4)) ?></span></span>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
<span id="home-start" class="home-start" tabindex="-1"></span>
<?php if ($stats): ?>
<div class="hero-ledger" aria-label="<?= e(__('site.achievements')) ?>">
  <div class="container" style="--cols:<?= count($stats) + ($latest ? 1 : 0) ?>">
    <?php foreach ($stats as $k => $l): ?><div class="ledger-stat"><b data-count="<?= (int) $counters[$k] ?>"><?= (int) $counters[$k] ?></b><span><?= e(__($l)) ?></span></div><?php endforeach; ?>
    <?php if ($latest): $tag = $latest['show_on_website'] ? 'a' : 'div'; ?>
      <<?= $tag ?> class="ledger-latest"<?= $latest['show_on_website'] ? ' href="' . e(site_url('horses/' . $latest['slug'])) . '"' : '' ?>>
        <span class="medal <?= e($medalCls[$latest['medal']] ?? 'medal-plain') ?>" aria-hidden="true">★</span>
        <span><strong><?= e($horseName($latest)) ?></strong><small><?= e(SiteData::resultText($latest)) ?> · <?= e(loc(['name_en' => $latest['show_en'], 'name_ar' => $latest['show_ar']])) ?> · <?= e(substr((string) $latest['start_date'], 0, 4)) ?></small></span>
        <?php if ($tag === 'a'): ?><?= icon('arrow-up-right') ?><?php endif; ?>
      </<?= $tag ?>>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php foreach ($sections as $sec): switch ($sec):

case 'horses': if (!$groups) { break; } ?>
<section class="chapter" id="horses" aria-labelledby="h-horses">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head split-head reveal"<?= $ghost('site.our_horses') ?>>
      <div><?= $index(__('site.collection')) ?><h2 id="h-horses" class="split"><?= split_title(__('site.our_horses'), false, -1) ?></h2></div>
      <p class="lead"><?= e(__('site.horses_intro')) ?></p>
    </div>
    <?= View::partial('site/partials/horse_groups', ['groups' => $groups, 'rows' => true, 'editorial' => true]) ?>
    <div class="chapter-more"><a class="btn btn-line" href="<?= e(site_url('horses')) ?>"><?= e(__('site.all_horses')) ?></a></div>
  </div>
</section>
<?php break;

case 'featured': if (!$featured) { break; }
  $champs = array_values(array_filter($horses, fn ($h) => (int) ($h['titles'] ?? 0) > 0 && (int) $h['id'] !== (int) $featured['id']));
  usort($champs, fn ($a, $b) => [$a['category'] !== 'stallion', -(int) $a['titles']] <=> [$b['category'] !== 'stallion', -(int) $b['titles']]);
  $champs = array_slice($champs, 0, 4);
  $fStory = SiteData::show('profile_story') ? trim((string) loc($featured, 'story')) : '';
?>
<section class="chapter night" id="featured" aria-labelledby="h-featured">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head chapter-head-row reveal"<?= $ghost('site.nav_champions') ?>>
      <div><?= $index(__('site.featured_champion')) ?><h2 id="h-featured" class="split"><?= split_title(__('site.nav_champions'), false) ?></h2></div>
      <?php if (SiteData::pageOn('champions')): ?><div class="chapter-more"><a class="link-arrow" href="<?= e(site_url('champions')) ?>"><?= e(__('site.all_results')) ?> <?= icon('arrow-up-right') ?></a></div><?php endif; ?>
    </div>
    <article class="feature bleed">
      <div class="feature-media arched framed wipe reveal" data-wall>
        <?php foreach (['bl', 'br'] as $c): ?><svg class="orn-corner <?= $c ?>" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#corner"></use></svg><?php endforeach; ?>
        <a href="<?= e(site_url('horses/' . $featured['slug'])) ?>" tabindex="-1" aria-hidden="true">
          <?php if ($featured['main_photo_id']): ?><img src="<?= e(SiteData::img((int) $featured['main_photo_id'])) ?>" alt="<?= e(loc($featured)) ?>" loading="lazy" decoding="async" width="1200" height="1500">
          <?php else: ?><span class="hcard-ph big"><span class="ph-mark"></span><span class="ph-initials"><?= e(SiteData::initials(loc($featured))) ?></span></span><?php endif; ?>
        </a>
        <?php if ($featuredResults && $featuredResults[0]['medal'] !== 'none'): ?><svg class="feature-medal <?= e($featuredResults[0]['medal']) ?>" viewBox="0 0 64 88" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#medal"></use></svg><?php endif; ?>
      </div>
      <div class="feature-text reveal">
        <span class="feature-ghost" aria-hidden="true"><?= e(loc($featured)) ?></span>
        <span class="eyebrow eyebrow-line" style="margin:0"><?= e(__('horse.cat_' . $featured['category'])) ?><?= $featured['dob'] && SiteData::show('profile_age') ? ' · ' . e(horse_age($featured['dob'])) : '' ?></span>
        <h3><a href="<?= e(site_url('horses/' . $featured['slug'])) ?>"><?= e(loc($featured)) ?></a></h3>
        <?php if (($o = lang() === 'ar' ? $featured['name_en'] : $featured['name_ar']) && $o !== loc($featured)): ?><p class="alt-name" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e($o) ?></p><?php endif; ?>
        <?php if ($featured['sire_en'] || $featured['dam_en']): ?><?= $lineage($featured) ?><?php endif; ?>
        <?php if ($featuredResults): ?>
          <ol class="ledger">
            <?php foreach ($featuredResults as $r): $show = loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']]); $year = substr((string) $r['start_date'], 0, 4); ?>
              <li><span class="medal <?= e($medalCls[$r['medal']] ?? 'medal-plain') ?>" aria-hidden="true"><?= $r['medal'] !== 'none' ? '★' : ($r['placing'] ? (int) $r['placing'] : '·') ?></span><span class="year"><?= e($year) ?></span>
                <span><?php if (SiteData::showClass()): ?><strong><?= e(SiteData::resultText($r)) ?></strong><small><?= e($show) ?></small><?php else: ?><strong><?= e($show) ?></strong><small><?= e(SiteData::resultText($r)) ?></small><?php endif; ?></span></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <?php if ($fStory !== ''): ?><p class="muted-text"><?= e(mb_strimwidth($fStory, 0, 320, '…')) ?></p><?php endif; ?>
        <?php $fCard = array_values(array_filter($featuredResults, fn ($r) => !empty($r['judge_card']))); if ($fCard): ?><?= View::partial('site/partials/scorecard', ['r' => $fCard[0], 'compact' => true]) ?><?php endif; ?>
        <div class="actions"><a class="btn btn-line" href="<?= e(site_url('horses/' . $featured['slug'])) ?>"><?= e(__('site.view_profile')) ?></a></div>
      </div>
    </article>
    <?php if ($champs): ?>
      <?= $orn('divider', 'orn-divider') ?>
      <h3 class="sub-head reveal"><?= e(__('site.more_champions')) ?></h3>
      <div class="hgrid stagger reveal"><?php foreach ($champs as $c): ?><?= View::partial('site/partials/horse_card', ['h' => $c]) ?><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</section>
<?php break;

case 'foals': if (!$foals) { break; } ?>
<section class="chapter" id="foals" aria-labelledby="h-foals">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head split-head reveal"<?= $ghost('site.latest_foals') ?>>
      <div><?= $index(__('site.foals_eyebrow')) ?><h2 id="h-foals" class="split"><?= split_title(__('site.latest_foals'), false, -1) ?></h2></div>
      <p class="lead"><?= e(__('site.foals_teaser')) ?></p>
    </div>
    <ol class="strip stagger reveal" tabindex="0" aria-label="<?= e(__('site.latest_foals')) ?>" data-cursor="drag" data-parade>
      <?php foreach ($foals as $f): ?>
        <li>
          <a class="hcard" href="<?= e(site_url('horses/' . $f['slug'])) ?>">
            <span class="hcard-frame"><span class="hcard-media">
              <?php if ($f['main_photo_id']): ?><img src="<?= e(SiteData::img((int) $f['main_photo_id'], 'thumb')) ?>" alt="<?= e(loc($f) . ' – ' . horse_kind($f)) ?>" loading="lazy" decoding="async" width="480" height="640">
              <?php else: ?><span class="hcard-ph" aria-hidden="true" style="--rot:<?= ((int) $f['id'] * 37) % 360 ?>deg"><span class="ph-mark"></span><svg class="ph-seal" viewBox="0 0 100 100"><use href="#seal"></use></svg><span class="ph-initials foil"><?= e(SiteData::initials(loc($f))) ?></span></span><?php endif; ?>
              <span class="sheen" aria-hidden="true"></span>
            </span><?= $arch ?></span>
            <span class="hcard-body">
              <?php if ($f['dob']): ?><time datetime="<?= e($f['dob']) ?>"><?= e(fmt_date($f['dob'])) ?></time><?php endif; ?>
              <span class="hcard-name"><?= e(loc($f)) ?></span>
              <?php if ($f['sire_en'] || $f['dam_en']): ?><span class="hcard-parents"><?= e(loc(['name_en' => $f['sire_en'], 'name_ar' => $f['sire_ar']]) ?: '—') ?> <span class="x" aria-hidden="true">×</span> <?= e(loc(['name_en' => $f['dam_en'], 'name_ar' => $f['dam_ar']]) ?: '—') ?></span><?php endif; ?>
              <?php if (!empty($f['born_at_sk'])): ?><span><span class="tl-flag"><?= e(__('site.born_at_sk_flag')) ?></span></span><?php endif; ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
    <div class="row-foot" aria-hidden="true"><span class="row-progress"><i></i></span><span class="row-n" dir="ltr"><b>1</b> / <?= count($foals) ?></span></div>
  </div>
</section>
<?php break;

case 'bloodlines': if (!$bloodlines) { break; } ?>
<section class="chapter night" id="bloodlines" aria-labelledby="h-bloodlines">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="section-pattern" aria-hidden="true"></div>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head split-head reveal"<?= $ghost('site.bloodlines') ?>>
      <div><?= $index(__('site.bloodlines_eyebrow')) ?><h2 id="h-bloodlines" class="split"><?= split_title(__('site.bloodlines'), false) ?></h2></div>
      <p class="lead"><?= e(__('site.bloodlines_intro')) ?></p>
    </div>
    <div class="lines stagger reveal">
      <?php foreach ($bloodlines as $bl):
        $cnt = count($bl['horses']);
        $xs = array_map(fn ($k) => round(($k + .5) * 100 / $cnt, 2), array_keys($bl['horses']));
        $tree = 'M50 0 V10 M' . $xs[0] . ' 10 H' . end($xs) . implode('', array_map(fn ($x) => ' M' . $x . ' 10 V22', $xs)); ?>
        <article class="line">
          <header class="line-head"><h3><?= e(bloodline_label($bl['name'])) ?></h3><span class="line-n"><b><?= (int) $bl['count'] ?></b> <?= e(__('site.c_horses')) ?></span></header>
          <svg class="line-tree" viewBox="0 0 100 22" preserveAspectRatio="none" aria-hidden="true"><path d="<?= $tree ?>"/></svg>
          <ul class="line-horses" style="--n:<?= $cnt ?>">
            <?php foreach ($bl['horses'] as $h): ?>
              <li><a href="<?= e(site_url('horses/' . $h['slug'])) ?>">
                <span class="line-photo"><?php if ($h['main_photo_id']): ?><img src="<?= e(SiteData::img((int) $h['main_photo_id'], 'thumb')) ?>" alt="" loading="lazy" decoding="async" width="96" height="96"><?php else: ?><span><?= e(SiteData::initials(loc($h))) ?></span><?php endif; ?></span>
                <span class="line-name"><?= e(loc($h)) ?></span>
                <small><?= e(__('horse.cat_' . $h['category'])) ?></small>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'achievements': if (!$medals) { break; } ?>
<section class="chapter deep" id="achievements" aria-labelledby="h-ach">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="section-pattern" aria-hidden="true"></div>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head chapter-head-row reveal"<?= $ghost('site.achievements') ?>>
      <div><?= $index(__('site.achievements_eyebrow')) ?><h2 id="h-ach" class="split"><?= split_title(__('site.achievements'), false) ?></h2></div>
      <?php if (SiteData::pageOn('champions')): ?><div class="chapter-more"><a class="link-arrow" href="<?= e(site_url('champions')) ?>"><?= e(__('site.all_results')) ?> <?= icon('arrow-up-right') ?></a></div><?php endif; ?>
    </div>
    <?php if ($stats): ?>
    <div class="counters reveal">
      <?php foreach ($stats as $k => $l): ?><div class="counter"><b data-count="<?= (int) $counters[$k] ?>"><?= (int) $counters[$k] ?></b><span><?= e(__($l)) ?></span></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php $tally = SiteData::medalCounts($medals); ?>
    <div class="cabinet stagger reveal">
      <?php foreach (['gold', 'silver', 'bronze'] as $m): ?>
        <div class="cabinet-item <?= $m ?>"><svg viewBox="0 0 64 88" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#medal"></use></svg><b data-count="<?= (int) $tally[$m] ?>"><?= (int) $tally[$m] ?></b><span><?= e(__('shows.medal_' . $m)) ?></span></div>
      <?php endforeach; ?>
    </div>
    <ol class="ledger medal-ledger stagger reveal">
      <?php foreach (array_slice($medals, 0, 8) as $r): $tag = $r['show_on_website'] ? 'a' : 'span'; ?>
        <li><span class="medal <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span>
          <<?= $tag ?><?= $r['show_on_website'] ? ' href="' . e(site_url('horses/' . $r['slug'])) . '"' : '' ?>><strong><?= e($horseName($r)) ?></strong><small><?= e(SiteData::resultText($r)) ?></small></<?= $tag ?>>
          <span class="ml-show"><i class="leader" aria-hidden="true"></i><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?></span>
          <span class="year"><?= e(substr((string) $r['start_date'], 0, 4)) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php break;

case 'story': if ($story === '' && !$pillars) { break; }
  $storyImg = $heroPhotos[1] ?? ($heroPhotos[0] ?? null);
?>
<section class="chapter" id="story" aria-labelledby="h-story">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <div class="story">
      <?php if ($storyImg): ?><div class="story-media arched round framed wipe reveal"><?php foreach (['bl', 'br'] as $c): ?><svg class="orn-corner <?= $c ?>" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#corner"></use></svg><?php endforeach; ?><img src="<?= e(SiteData::img((int) $storyImg['main_photo_id'])) ?>" alt="" loading="lazy" decoding="async" width="1200" height="1500"></div><?php endif; ?>
      <div class="story-text"<?= $ghost('site.our_story') ?>>
        <?php $n++; ?>
        <div class="reveal"><?= $index(__('site.our_story')) ?></div>
        <h2 id="h-story" class="split reveal"><?= split_title(__('site.story_title'), false, lang() === 'ar' ? 3 : 4) ?></h2>
        <blockquote class="quote split split-fade reveal"><span class="q" aria-hidden="true"><?= lang() === 'ar' ? '«' : '“' ?></span><?= split_title(__('site.story_quote'), false) ?><footer class="quote-by"><?= e(__('site.brand')) ?> · <?= e(__('site.doha_qatar')) ?></footer></blockquote>
        <?php if ($story !== ''): ?><div class="prose reveal"><p class="opens"><?= lede($story) ?></p></div><?php endif; ?>
        <?php if (SiteData::pageOn('about')): ?><div class="reveal"><a class="link-arrow" href="<?= e(site_url('about')) ?>"><?= e(__('site.read_more')) ?> <?= icon('arrow-up-right') ?></a></div><?php endif; ?>
      </div>
    </div>
    <?php if ($pillars): ?>
    <div class="pillars stagger reveal">
      <?php foreach ($pillars as [$pn, $t, $txt]): ?>
        <div class="pillar"><span class="num" aria-hidden="true">0<?= $pn ?></span><h3><?= e($t) ?></h3><?php if ($txt !== ''): ?><p><?= e($txt) ?></p><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php break;

case 'gallery': if (!$gallery) { break; } ?>
<section class="chapter night" id="gallery" aria-labelledby="h-gallery" data-film>
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head chapter-head-row reveal"<?= $ghost('site.gallery') ?>>
      <div><?= $index(__('site.moments')) ?><h2 id="h-gallery" class="split"><?= split_title(__('site.gallery'), false) ?></h2></div>
      <?php if ($ig = setting('company.instagram')): ?><div class="chapter-more"><a class="link-arrow" href="<?= e($ig) ?>" target="_blank" rel="noopener"><?= icon('instagram') ?> <?= e(__('site.follow_instagram')) ?></a></div><?php endif; ?>
    </div>
    <?= View::partial('site/partials/gallery_grid', ['gallery' => $gallery, 'lead' => true]) ?>
  </div>
</section>
<?php break;

case 'news': if (!$news) { break; } ?>
<section class="chapter" id="news" aria-labelledby="h-news">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head chapter-head-row reveal"<?= $ghost('site.news_title') ?>>
      <div><?= $index(__('site.news_eyebrow')) ?><h2 id="h-news" class="split"><?= split_title(__('site.news_title'), false, -1) ?></h2></div>
      <div class="chapter-more"><a class="link-arrow" href="<?= e(site_url('news')) ?>"><?= e(__('site.all_news')) ?> <?= icon('arrow-up-right') ?></a></div>
    </div>
    <div class="news-grid lead-first stagger reveal">
      <?php foreach ($news as $item): ?><?= View::partial('site/partials/news_card', ['n' => $item]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'experts': if (!$experts) { break; } ?>
<section class="chapter" id="experts" aria-labelledby="h-experts">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container">
    <?php $n++; ?>
    <div class="chapter-head reveal"<?= $ghost('site.our_experts') ?>>
      <div><?= $index(__('site.the_team')) ?><h2 id="h-experts" class="split"><?= split_title(__('site.our_experts'), false, -1) ?></h2></div>
    </div>
    <div class="experts stagger reveal">
      <?php foreach ($experts as $x): ?>
        <div class="expert"><?= View::partial('site/partials/expert_photo', ['x' => $x]) ?><h3><?= e(loc($x)) ?></h3><?php if ($role = loc($x, 'public_title') ?: loc($x, 'position')): ?><p><?= e($role) ?></p><?php endif; ?><?php if ($sp = loc($x, 'specialties')): ?><small><?= e($sp) ?></small><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'contact': ?>
<section class="chapter night" id="contact" aria-labelledby="h-contact">
  <span class="threshold" aria-hidden="true"><svg class="orn"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg></span>
  <div class="container contact">
    <?php $n++; ?>
    <div class="contact-info reveal"<?= $ghost('site.nav_contact') ?>>
      <?= $index(__('site.location')) ?>
      <h2 id="h-contact" class="split"><?= split_title(__('site.visit_us'), false, -1) ?></h2>
      <p class="lead"><?= e(__('site.contact_intro')) ?></p>
      <?= View::partial('site/partials/contact_list') ?>
      <div class="map-frame"><iframe class="map" title="<?= e(__('site.map')) ?>" loading="lazy" referrerpolicy="no-referrer" allowfullscreen src="https://www.google.com/maps?q=<?= e(rawurlencode((string) setting('company.map_query', 'Doha, Qatar'))) ?>&amp;output=embed"></iframe></div>
    </div>
    <div class="contact-form reveal"><?= View::partial('site/partials/inquiry_form', ['heading' => __('site.send_message')]) ?></div>
  </div>
</section>
<?php break;
endswitch; endforeach; ?>

<?= View::partial('site/partials/cta_band', ['enquire' => $enquire]) ?>
</div>
