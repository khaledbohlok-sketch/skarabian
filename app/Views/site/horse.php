<?php
/**
 * A horse's public profile (version 3): the horse full-bleed under its name, then the gallery beside a sticky facts
 * panel, the video, the show record, the pedigree, more horses of its kind, the pager, and the enquiry. What it shows
 * is switched in Website → Homepage & texts → "Horse profiles"; never breeding information, prices, owners or medical
 * records.
 */
use App\Core\View;
use App\Services\SiteData;

$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
$imgs = array_values(array_filter($photos, fn ($p) => str_starts_with($p['mime'], 'image/')));
$videos = array_values(array_filter($photos, fn ($p) => $p['mime'] === 'video/mp4'));
$yt = null;
if (SiteData::show('profile_video') && $h['video_url'] && preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $h['video_url'], $m)) { $yt = $m[1]; }
$showVideo = SiteData::show('profile_video') && ($yt || $videos);
$name = loc($h);
$other = lang() === 'ar' ? (string) $h['name_en'] : (string) $h['name_ar'];
$person = fn (?string $en, ?string $ar, ?string $slug, $public) => $en === null ? '—'
    : ($slug && $public ? '<a href="' . e(site_url('horses/' . $slug)) . '">' . e(loc(['name_en' => $en, 'name_ar' => $ar])) . '</a>' : e(loc(['name_en' => $en, 'name_ar' => $ar])));
$facts = [['horse.sire', $person($h['sire_en'], $h['sire_ar'], $h['sire_slug'], $h['sire_public'])], ['horse.dam', $person($h['dam_en'], $h['dam_ar'], $h['dam_slug'], $h['dam_public'])]];
$facts[] = ['horse.sex', e(__('horse.sex_' . $h['sex']))];
if (SiteData::show('profile_age') && $h['dob']) { $facts[] = ['site.age', e(horse_age($h['dob'])) . ' <span class="dim">(' . e(substr($h['dob'], 0, 4)) . ')</span>']; }
if (SiteData::show('profile_colour') && $h['color_en']) { $facts[] = ['horse.color', e(loc(['value_en' => $h['color_en'], 'value_ar' => $h['color_ar']], 'value'))]; }
if ($h['breed_en']) { $facts[] = ['horse.breed', e(loc(['value_en' => $h['breed_en'], 'value_ar' => $h['breed_ar']], 'value'))]; }
if (SiteData::show('profile_breeder') && $h['breeder']) { $facts[] = ['horse.breeder', e($h['breeder'])]; }
if (SiteData::show('profile_country') && $h['country_en']) { $facts[] = ['site.country_of_birth', e(loc(['name_en' => $h['country_en'], 'name_ar' => $h['country_ar']]))]; }
if ($h['bloodline']) { $facts[] = ['horse.bloodline', e(bloodline_label($h['bloodline']))]; }
[$prev, $next] = $neighbours;
$titles = array_values(array_filter($results, fn ($r) => $r['medal'] !== 'none'));
$inquiry = SiteData::show('profile_inquiry');
$catPlural = ['stallion' => 'stallions', 'mare' => 'mares', 'filly' => 'fillies', 'colt' => 'colts', 'foal' => 'foals'][$h['category']];
$medalPills = '';
if (array_sum($medals)) {
    $medalPills = '<div class="medal-summary">';
    foreach ($medals as $k => $cnt) {
        if ($cnt) { $medalPills .= '<span class="medal-pill"><span class="medal ' . e($medalCls[$k]) . '" aria-hidden="true">★</span> <b>' . (int) $cnt . '</b> ' . e(__('shows.medal_' . $k)) . '</span>'; }
    }
    $medalPills .= '</div>';
}
echo View::partial('site/partials/page_hero', [
    'crumbs' => [[__('site.nav_horses'), site_url('horses')], [$name, null]], 'cls' => 'profile-hero',
    'bg' => $imgs ? SiteData::img((int) $imgs[0]['id']) : null,
    'eyebrow' => e(__('horse.cat_' . $h['category'])) . ($h['dob'] && SiteData::show('profile_age') ? ' · ' . e(horse_age($h['dob'])) : ''), 'title' => $name,
    'alt' => $other !== '' && $other !== $name ? [$other, lang() === 'ar' ? 'en' : 'ar'] : null,
    'extra' => $medalPills,
]); ?>

<section class="chapter profile-section">
  <div class="container">
    <div class="profile">
      <div class="profile-media" data-gallery>
        <div class="pgallery-main">
          <?php if ($imgs): ?><a href="<?= e(SiteData::img((int) $imgs[0]['id'])) ?>" data-gallery-open aria-label="<?= e(__('site.open_photo')) ?>"><img src="<?= e(SiteData::img((int) $imgs[0]['id'])) ?>" alt="<?= e($name . ' – ' . horse_kind($h)) ?>" data-gallery-main fetchpriority="high" decoding="async" width="1200" height="900"></a>
          <?php else: ?><span class="hcard-ph big" aria-hidden="true"><span class="ph-mark"></span><span class="ph-initials"><?= e(SiteData::initials(loc($h))) ?></span></span><?php endif; ?>
        </div>
        <?php if ($imgs): ?><div class="pgallery-thumbs"<?= count($imgs) < 2 ? ' hidden' : '' ?>><?php foreach ($imgs as $i => $p): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e(SiteData::img((int) $p['id'])) ?>" data-caption="<?= e($name . ' — ' . __('site.photo_of', ['n' => $i + 1, 'total' => count($imgs)])) ?>" aria-label="<?= e(__('site.photo_n', ['n' => $i + 1])) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><img src="<?= e(SiteData::img((int) $p['id'], 'thumb')) ?>" alt="" loading="lazy" decoding="async" width="96" height="72"></button><?php endforeach; ?></div><?php endif; ?>
      </div>
      <div class="profile-info">
        <dl class="facts">
          <?php foreach ($facts as [$label, $value]): ?><div><dt><?= e(__($label)) ?></dt><dd><?= $value ?></dd></div><?php endforeach; ?>
        </dl>
        <?php if (SiteData::show('profile_story') && ($story = loc($h, 'story'))): ?><div class="prose"><?php foreach (preg_split('/\R{2,}/', $story) as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?></div><?php endif; ?>
        <?php $shareUrl = absolute_url('/' . lang() . '/horses/' . $h['slug']);
          $shareText = $name . ' — SK Arabians';
          $shareWa = 'https://wa.me/?text=' . rawurlencode($shareText . "\n" . $shareUrl); ?>
        <div class="profile-actions">
          <?php if ($inquiry): ?><a class="btn btn-gold" href="#inquire"><?= e(__('site.enquire')) ?></a><?php endif; ?>
          <a class="btn btn-line" href="<?= e(site_url('horses')) ?>"><?= e(__('site.all_horses')) ?></a>
          <div class="share" data-share>
            <a class="btn btn-line share-btn" href="<?= e($shareWa) ?>" target="_blank" rel="noopener" data-share-btn data-url="<?= e($shareUrl) ?>" data-title="<?= e($shareText) ?>" aria-haspopup="true" aria-expanded="false"><?= icon('share') ?> <?= e(__('site.share')) ?></a>
            <div class="share-menu" data-share-menu hidden>
              <a href="<?= e($shareWa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> <?= e(__('site.share_whatsapp')) ?></a>
              <button type="button" data-share-copy data-done="<?= e(__('site.link_copied')) ?>"><?= icon('link') ?> <span><?= e(__('site.copy_link')) ?></span></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($showVideo): ?>
    <div class="profile-block reveal"><h2><?= e(__('site.video')) ?></h2>
      <div class="video-embed"><?php if ($yt): ?><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="<?= e($name) ?>" allowfullscreen loading="lazy"></iframe><?php else: ?><video controls preload="metadata" src="<?= e(url('/media/' . $videos[0]['id'] . '/full')) ?>"></video><?php endif; ?></div>
    </div>
    <?php endif; ?>

    <?php if ($results): ?>
    <div class="profile-block reveal" id="results"><h2><?= e(__('site.show_results')) ?></h2>
      <?php if ($titles): ?>
        <ol class="medal-line stagger">
          <?php foreach (array_slice($titles, 0, 6) as $r): $show = loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']]); $year = substr((string) $r['start_date'], 0, 4); ?>
            <li><span class="medal <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span><span class="ml-year"><?= e($year) ?></span><span><?php if (SiteData::showClass()): ?><strong><?= e(SiteData::resultText($r)) ?></strong><small><?= e($show) ?></small><?php else: ?><strong><?= e($show) ?></strong><small><?= e(SiteData::resultText($r)) ?></small><?php endif; ?></span></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <?php if (SiteData::showClass()): ?>
      <div class="table-scroll"><table class="results-table"><thead><tr><th scope="col"><?= e(__('site.year')) ?></th><th scope="col"><?= e(__('shows.show')) ?></th><th scope="col"><?= e(__('shows.class')) ?></th><th scope="col"><?= e(__('shows.placing')) ?></th><th scope="col"><?= e(__('shows.result')) ?></th></tr></thead><tbody>
      <?php foreach ($results as $r): ?><tr><td data-label="<?= e(__('site.year')) ?>"><?= e(fmt_date($r['start_date'])) ?></td><td data-label="<?= e(__('shows.show')) ?>"><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?></td><td data-label="<?= e(__('shows.class')) ?>"><?= e((string) $r['class_name']) ?></td>
        <td data-label="<?= e(__('shows.placing')) ?>"><?= $r['placing'] ? (int) $r['placing'] : '—' ?><?= $r['score'] !== null ? ' <small class="score">' . e(rtrim(rtrim(number_format((float) $r['score'], 2), '0'), '.')) . '</small>' : '' ?></td>
        <td data-label="<?= e(__('shows.result')) ?>"><?php if ($r['medal'] !== 'none'): ?><span class="medal sm <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span> <?php endif; ?><?= e(loc($r, 'title')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php else: ?>
      <div class="table-scroll"><table class="results-table"><thead><tr><th scope="col"><?= e(__('site.year')) ?></th><th scope="col"><?= e(__('shows.show')) ?></th><th scope="col"><?= e(__('shows.medal')) ?></th></tr></thead><tbody>
      <?php foreach ($results as $r): ?><tr><td data-label="<?= e(__('site.year')) ?>"><?= e(fmt_date($r['start_date'])) ?></td><td data-label="<?= e(__('shows.show')) ?>"><?= e(loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']])) ?></td>
        <td data-label="<?= e(__('shows.medal')) ?>"><?php if (isset($medalCls[$r['medal']])): ?><span class="medal sm <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span> <?= e(__('shows.medal_' . $r['medal'])) ?><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($pedigree && ($pedigree['sire'] || $pedigree['dam'])): ?>
    <div class="profile-block reveal" id="pedigree"><h2><?= e(__('site.pedigree')) ?></h2><?= View::partial('site/partials/pedigree', ['ped' => $pedigree, 'gens' => $gens, 'name' => $name]) ?></div>
    <?php endif; ?>

    <?php if ($related): ?>
    <div class="profile-block reveal"><h2><?= e(__('site.more_horses', ['cat' => __('site.f_' . $catPlural)])) ?></h2>
      <div class="hgrid stagger"><?php foreach ($related as $o): ?><?= View::partial('site/partials/horse_card', ['h' => $o]) ?><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <nav class="profile-pager" aria-label="<?= e(__('site.our_horses')) ?>">
      <?php if ($prev): ?><a class="pager-prev" href="<?= e(site_url('horses/' . $prev['slug'])) ?>" rel="prev"><span><?= e(__('site.previous')) ?></span><b><?= e(loc($prev)) ?></b></a><?php else: ?><span></span><?php endif; ?>
      <a class="pager-all" href="<?= e(site_url('horses')) ?>"><?= e(__('site.all_horses')) ?></a>
      <?php if ($next): ?><a class="pager-next" href="<?= e(site_url('horses/' . $next['slug'])) ?>" rel="next"><span><?= e(__('site.next')) ?></span><b><?= e(loc($next)) ?></b></a><?php else: ?><span></span><?php endif; ?>
    </nav>
  </div>
</section>

<?php if ($inquiry): ?>
<section class="chapter night" id="inquire" aria-labelledby="h-inquire">
  <div class="container contact">
    <div class="contact-info reveal">
      <span class="eyebrow eyebrow-line"><?= e(__('site.get_in_touch')) ?></span>
      <h2 id="h-inquire"><?= e(__('site.inquire_horse')) ?></h2>
      <p class="lead"><?= e(__('site.inquire_horse_text', ['name' => $name])) ?></p>
      <?= View::partial('site/partials/contact_list') ?>
    </div>
    <div class="reveal"><?= View::partial('site/partials/inquiry_form', ['horseId' => $h['id'], 'type' => 'horse', 'prefill' => __('site.inquiry_prefill', ['name' => $name])]) ?></div>
  </div>
</section>
<?php else: ?>
<?= View::partial('site/partials/cta_band') ?>
<?php endif; ?>
