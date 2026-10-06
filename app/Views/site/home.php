<?php
use App\Core\View;
use App\Services\SiteData;

$sections = array_filter(array_map('trim', explode(',', (string) setting('site.sections', 'featured,horses,achievements,foals,bloodlines,breeding,gallery,story,experts,contact'))));
$heroSlides = array_slice(array_values(array_filter($horses, fn ($h) => $h['main_photo_id'])), 0, 5);
$video = (string) setting('site.hero_video_url', '');
$cats = ['stallion' => 'site.f_stallions', 'mare' => 'site.f_mares', 'colt' => 'site.f_colts', 'filly' => 'site.f_fillies', 'foal' => 'site.f_foals'];
$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
?>
<section class="hero" aria-label="SK Arabians">
  <div class="hero-media" data-slideshow>
    <?php if ($video && preg_match('#\.(mp4|webm)$#i', $video)): ?>
      <video autoplay muted loop playsinline poster="<?= e($heroSlides ? SiteData::img((int) $heroSlides[0]['main_photo_id']) : asset('img/horse-placeholder.svg')) ?>"><source src="<?= e($video) ?>"></video>
    <?php elseif ($heroSlides): foreach ($heroSlides as $i => $s): ?>
      <div class="hero-slide<?= $i === 0 ? ' on' : '' ?>" data-bg="<?= e(SiteData::img((int) $s['main_photo_id'])) ?>" role="img" aria-label="<?= e(loc($s)) ?>"></div>
    <?php endforeach; else: ?>
      <div class="hero-slide on" data-bg="<?= e(asset('img/horse-placeholder.svg')) ?>"></div>
    <?php endif; ?>
  </div>
  <div class="container hero-inner">
    <img class="hero-logo" src="<?= e(asset('img/sk-logo-white.png')) ?>" alt="SK Arabians — اس كي ارابيان للتجارة" width="260" height="119">
    <span class="eyebrow"><?= e(__('site.hero_eyebrow', ['year' => setting('site.established', '2025')])) ?></span>
    <h1><?= e(setting('site.hero_title_' . lang(), __('site.tagline'))) ?></h1>
    <p class="lead"><?= e(setting('site.hero_sub_' . lang(), '')) ?></p>
    <div class="hero-actions">
      <a class="btn btn-gold" href="<?= e(site_url('horses')) ?>"><?= e(__('site.meet_horses')) ?></a>
      <a class="btn btn-outline" href="<?= e(site_url('breeding')) ?>"><?= e(__('site.breeding_sales')) ?></a>
    </div>
  </div>
  <a class="hero-scroll" href="#featured" aria-label="<?= e(__('site.scroll')) ?>"></a>
</section>

<?php foreach ($sections as $sec): switch ($sec):

case 'featured': if (!$featured) { break; } ?>
<section class="section" id="featured">
  <div class="container featured reveal">
    <div class="featured-media">
      <img src="<?= e(SiteData::img($featured['main_photo_id'] ? (int) $featured['main_photo_id'] : null)) ?>" alt="<?= e(loc($featured)) ?>" loading="lazy">
      <span class="featured-badge"><?= e(__('site.featured_champion')) ?></span>
    </div>
    <div>
      <span class="eyebrow" style="color:var(--gold)"><?= e(__('horse.cat_' . $featured['category'])) ?><?= $featured['dob'] ? ' · ' . e(horse_age($featured['dob'])) : '' ?></span>
      <h3><?= e($featured['name_en']) ?></h3>
      <?php if ($featured['name_ar']): ?><div class="ar-name"><?= e($featured['name_ar']) ?></div><?php endif; ?>
      <p style="color:var(--muted)"><?= e($featured['sire_en'] ? loc(['name_en' => $featured['sire_en'], 'name_ar' => $featured['sire_ar']]) : '—') ?> × <?= e($featured['dam_en'] ? loc(['name_en' => $featured['dam_en'], 'name_ar' => $featured['dam_ar']]) : '—') ?></p>
      <?php if ($featuredResults): ?>
        <ul class="titles">
          <?php foreach ($featuredResults as $r): ?>
            <li><span class="medal <?= e($medalCls[$r['medal']] ?? 'medal-gold') ?>"><?= $r['placing'] ? (int) $r['placing'] : '★' ?></span>
              <span><strong><?= e(loc($r, 'title') ?: $r['class_name']) ?></strong><br><small><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?> · <?= e(substr($r['start_date'], 0, 4)) ?></small></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($story = loc($featured, 'story')): ?><p><?= e(mb_strimwidth($story, 0, 320, '…')) ?></p><?php endif; ?>
      <a class="btn btn-line" href="<?= e(site_url('horses/' . $featured['slug'])) ?>"><?= e(__('site.view_profile')) ?></a>
    </div>
  </div>
</section>
<?php break;

case 'horses': if (!$horses) { break; } ?>
<section class="section section-mist" id="our-horses">
  <div class="container">
    <div class="section-head reveal">
      <div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.collection')) ?></span><h2><?= e(__('site.our_horses')) ?></h2></div>
      <div class="chip-filters" data-filter-group="home-horses">
        <button class="chip on" type="button" data-filter="all"><?= e(__('site.all')) ?></button>
        <?php foreach ($cats as $k => $label): if (array_filter($horses, fn ($h) => $h['category'] === $k)): ?>
          <button class="chip" type="button" data-filter="<?= e($k) ?>"><?= e(__($label)) ?></button>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div class="carousel" data-filter-target="home-horses">
      <?php foreach ($horses as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?>
    </div>
    <p style="margin-top:22px"><a class="btn btn-line" href="<?= e(site_url('horses')) ?>"><?= e(__('site.all_horses')) ?></a></p>
  </div>
</section>
<?php break;

case 'achievements': ?>
<section class="section section-navy" id="achievements">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow"><?= e(__('site.achievements_eyebrow')) ?></span><h2><?= e(__('site.achievements')) ?></h2></div></div>
    <div class="counters reveal">
      <?php foreach (['horses' => 'site.c_horses', 'titles' => 'site.c_titles', 'shows' => 'site.c_shows', 'foals' => 'site.c_foals'] as $k => $l): ?>
        <div class="counter"><b data-count="<?= (int) $counters[$k] ?>"><?= (int) $counters[$k] ?></b><span><?= e(__($l)) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php if ($medals): ?>
    <div class="medal-wall reveal">
      <?php foreach (array_slice($medals, 0, 9) as $r): ?>
        <div class="medal-card"><span class="medal <?= e($medalCls[$r['medal']]) ?>"><?= e(mb_strtoupper(mb_substr(__('shows.medal_' . $r['medal']), 0, 1))) ?></span>
          <div><strong><?= e(loc(['name_en' => $r['horse_en'], 'name_ar' => $r['horse_ar']])) ?></strong><small><?= e(loc($r, 'title') ?: __('shows.medal_' . $r['medal'])) ?> · <?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?> <?= e(substr($r['start_date'], 0, 4)) ?></small></div></div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:26px"><a class="btn btn-outline" href="<?= e(site_url('champions')) ?>"><?= e(__('site.all_results')) ?></a></p>
    <?php endif; ?>
  </div>
</section>
<?php break;

case 'foals': if (!$foals) { break; } ?>
<section class="section" id="foals">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.new_generation')) ?></span><h2><?= e(__('site.latest_foals')) ?></h2></div></div>
    <div class="foal-grid">
      <?php foreach ($foals as $f): ?>
        <a class="foal-card reveal" href="<?= e(site_url('horses/' . $f['slug'])) ?>">
          <img src="<?= e(SiteData::img($f['main_photo_id'] ? (int) $f['main_photo_id'] : null, 'thumb')) ?>" alt="<?= e(loc($f)) ?>" loading="lazy">
          <div><h3><?= e(loc($f)) ?></h3><p><?= e(loc(['name_en' => $f['sire_en'], 'name_ar' => $f['sire_ar']])) ?> × <?= e(loc(['name_en' => $f['dam_en'], 'name_ar' => $f['dam_ar']])) ?></p>
          <p><?= e(fmt_date($f['dob'])) ?> · <?= e(__('horse.cat_' . $f['category'])) ?></p><span class="born-tag"><?= e(__('site.born_at_sk')) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'bloodlines': if (!$sires && !$dams) { break; } ?>
<section class="section section-mist" id="bloodlines">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.heritage')) ?></span><h2><?= e(__('site.bloodlines')) ?></h2><p><?= e(__('site.bloodlines_intro')) ?></p></div></div>
    <div class="bloodlines reveal">
      <div>
        <h3 style="font-size:26px"><?= e(__('site.main_sires')) ?></h3>
        <ul class="sire-list">
          <?php foreach ($sires as $s): ?><li><a href="<?= e(site_url('horses/' . $s['slug'])) ?>"><img src="<?= e(SiteData::img($s['main_photo_id'] ? (int) $s['main_photo_id'] : null, 'thumb')) ?>" alt="" loading="lazy"><span><strong><?= e(loc($s)) ?></strong><small><?= e(__('site.n_offspring', ['n' => (int) $s['offspring']])) ?><?= $s['bloodline'] ? ' · ' . e($s['bloodline']) : '' ?></small></span></a></li><?php endforeach; ?>
        </ul>
        <?php if ($dams): ?>
        <h3 style="font-size:26px;margin-top:26px"><?= e(__('site.dam_lines')) ?></h3>
        <ul class="sire-list">
          <?php foreach ($dams as $s): ?><li><a href="<?= e(site_url('horses/' . $s['slug'])) ?>"><img src="<?= e(SiteData::img($s['main_photo_id'] ? (int) $s['main_photo_id'] : null, 'thumb')) ?>" alt="" loading="lazy"><span><strong><?= e(loc($s)) ?></strong><small><?= e(__('site.n_offspring', ['n' => (int) $s['offspring']])) ?><?= $s['bloodline'] ? ' · ' . e($s['bloodline']) : '' ?></small></span></a></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php if ($featured && $pedigree): ?>
      <div>
        <h3 style="font-size:26px"><?= e(__('site.pedigree_of', ['name' => loc($featured)])) ?></h3>
        <?= View::partial('site/partials/pedigree', ['ped' => $pedigree, 'gens' => 2]) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php break;

case 'breeding': ?>
<section class="section" id="breeding">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.opportunities')) ?></span><h2><?= e(__('site.breeding_sales')) ?></h2></div></div>
    <div class="teaser-grid">
      <div class="teaser reveal"><div class="count"><?= (int) $stud ?></div><h3><?= e(__('site.stallions_at_stud')) ?></h3><p><?= e(__('site.stud_teaser')) ?></p><a class="btn btn-gold" href="<?= e(site_url('breeding')) ?>"><?= e(__('site.inquire')) ?></a></div>
      <div class="teaser reveal"><div class="count"><?= (int) $embryos ?></div><h3><?= e(__('site.embryos_available')) ?></h3><p><?= e(__('site.embryo_teaser')) ?></p><a class="btn btn-gold" href="<?= e(site_url('breeding') . '#embryos') ?>"><?= e(__('site.inquire')) ?></a></div>
      <div class="teaser reveal"><div class="count"><?= (int) $forSale ?></div><h3><?= e(__('site.horses_for_sale')) ?></h3><p><?= e(__('site.sale_teaser')) ?></p><a class="btn btn-gold" href="<?= e(site_url('for-sale')) ?>"><?= e(__('site.inquire')) ?></a></div>
    </div>
  </div>
</section>
<?php break;

case 'gallery': if (!$gallery) { break; } ?>
<section class="section section-mist" id="gallery">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.moments')) ?></span><h2><?= e(__('site.gallery')) ?></h2></div>
      <?php if ($ig = setting('company.instagram')): ?><a class="btn btn-line" href="<?= e($ig) ?>" target="_blank" rel="noopener"><?= icon('instagram') ?> Instagram</a><?php endif; ?></div>
    <div class="gallery reveal">
      <?php foreach ($gallery as $i => $g): ?>
        <a class="<?= $i === 0 ? 'big' : '' ?>" href="<?= e($g['video_url'] ?: ($g['slug'] ? site_url('horses/' . $g['slug']) : SiteData::img((int) $g['file_id']))) ?>"<?= $g['video_url'] ? ' target="_blank" rel="noopener"' : '' ?>>
          <img src="<?= e(SiteData::img($g['file_id'] ? (int) $g['file_id'] : null, $i === 0 ? 'full' : 'thumb')) ?>" alt="<?= e(loc($g, 'caption')) ?>" loading="lazy">
          <?php if ($g['video_url']): ?><span class="play">▶</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'story': ?>
<section class="section" id="story">
  <div class="container">
    <div class="story reveal">
      <div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.our_story')) ?></span><h2 style="font-size:clamp(32px,4.4vw,54px)"><?= e(__('site.story_title')) ?></h2><hr class="gold-rule"><p><?= e(setting('site.story_' . lang(), '')) ?></p><a class="btn btn-line" href="<?= e(site_url('about')) ?>"><?= e(__('site.read_more')) ?></a></div>
      <blockquote>“<?= e(__('site.story_quote')) ?>”</blockquote>
    </div>
    <div class="pillars">
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="pillar reveal"><span class="num">0<?= $i ?></span><h3><?= e(setting('site.pillar' . $i . '_title_' . lang(), '')) ?></h3><p><?= e(setting('site.pillar' . $i . '_text_' . lang(), '')) ?></p></div>
      <?php endfor; ?>
    </div>
  </div>
</section>
<?php break;

case 'experts': if (!$experts) { break; } ?>
<section class="section section-mist" id="experts">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow" style="color:var(--gold)"><?= e(__('site.the_team')) ?></span><h2><?= e(__('site.our_experts')) ?></h2></div></div>
    <div class="experts">
      <?php foreach ($experts as $x): ?>
        <div class="expert reveal"><img src="<?= e(SiteData::img($x['photo_id'] ? (int) $x['photo_id'] : null, 'thumb')) ?>" alt="<?= e(loc($x)) ?>" loading="lazy"><h3><?= e(loc($x)) ?></h3><p><?= e(loc($x, 'public_title') ?: loc($x, 'position')) ?></p><small><?= e(loc($x, 'specialties')) ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

case 'contact': ?>
<section class="section" id="contact">
  <div class="container contact">
    <div class="reveal">
      <span class="eyebrow" style="color:var(--gold)"><?= e(__('site.get_in_touch')) ?></span><h2 style="font-size:clamp(32px,4.4vw,54px)"><?= e(__('site.visit_us')) ?></h2>
      <?= View::partial('site/partials/contact_list') ?>
      <iframe class="map" title="<?= e(__('site.map')) ?>" loading="lazy" referrerpolicy="no-referrer" src="https://www.google.com/maps?q=<?= e(rawurlencode((string) setting('company.map_query', 'Doha, Qatar'))) ?>&output=embed"></iframe>
    </div>
    <div class="reveal"><?= View::partial('site/partials/inquiry_form', ['heading' => __('site.send_message')]) ?></div>
  </div>
</section>
<?php break;
endswitch; endforeach; ?>
