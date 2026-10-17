<?php
/**
 * Champions (version 3): the medal count in the band, the champion horses (most decorated first, with their best
 * titles), then every public result by season. The season tabs are links (?year=…); site.js switches in place.
 */
use App\Core\View;
use App\Services\SiteData;

$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
$horseName = fn (array $r) => loc(['name_en' => $r['horse_en'], 'name_ar' => $r['horse_ar']]);
$horseLink = fn (array $r) => $r['show_on_website'] ? '<a href="' . e(site_url('horses/' . $r['slug'])) . '">' . e($horseName($r)) . '</a>' : e($horseName($r));
$showName = fn (array $r) => loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']]);
$where = function (array $r): string {
    $country = loc(['name_en' => $r['country_en'], 'name_ar' => $r['country_ar']]);
    $city = trim((string) $r['city']);
    $place = implode(', ', array_filter([$city !== '' && strcasecmp($city, (string) $r['country_en']) !== 0 ? $city : '', $country]));
    return implode(' · ', array_filter([$place, preg_match('/^\d{4}$/', (string) $r['start_date']) ? '' : fmt_date($r['start_date'])]));
};
$badge = function (array $r) use ($medalCls): string {
    if (isset($medalCls[$r['medal']])) {
        return '<span class="medal ' . $medalCls[$r['medal']] . '" title="' . e(__('shows.medal_' . $r['medal'])) . '">★</span>';
    }
    return '<span class="medal medal-plain"' . ($r['placing'] ? ' title="' . e(__('shows.placing')) . ': ' . (int) $r['placing'] . '">' . (int) $r['placing'] : '>·') . '</span>';
};
$titleLine = function (array $r) use ($showName): string {
    $year = substr((string) $r['start_date'], 0, 4);
    return SiteData::showClass()
        ? '<strong>' . e(SiteData::resultText($r)) . '</strong><small>' . e($showName($r)) . ' · ' . e($year) . '</small>'
        : '<strong>' . e($showName($r)) . '</strong><small>' . e(SiteData::resultText($r)) . ' · ' . e($year) . '</small>';
};
$total = array_sum(array_map('count', $byYear));
$base = site_url('champions');
$tallyHtml = '';
if ($total) {
    $tallyHtml = '<div class="tally">';
    foreach ($medalCls as $m => $cls) {
        if ($tally[$m]) { $tallyHtml .= '<div class="tally-item"><span class="medal ' . e($cls) . '" aria-hidden="true">★</span><b data-count="' . (int) $tally[$m] . '">' . (int) $tally[$m] . '</b><span class="tally-label">' . e(__('shows.medal_' . $m)) . '</span></div>'; }
    }
    $tallyHtml .= '</div>';
}
echo View::partial('site/partials/page_hero', ['crumbs' => [[__('site.nav_champions'), null]], 'cls' => 'champions-hero', 'eyebrow' => e(__('site.champions_eyebrow')),
    'title' => __('site.champions_title'), 'lead' => __('site.champions_intro'), 'extra' => $tallyHtml]); ?>

<?php if (!$total): ?>
<section class="chapter"><div class="container"><div class="empty-note"><?= e(__('site.no_results')) ?></div></div></section>
<?php else: ?>

<?php if ($champions): ?>
<section class="chapter" id="champions">
  <div class="container">
    <div class="chapter-head split-head reveal">
      <div><span class="chapter-index"><b>01</b> <?= e(__('site.champions_eyebrow')) ?></span><h2 class="split"><?= split_title(__('site.champions_hall'), false) ?></h2></div>
      <p class="lead"><?= e(__('site.champions_hall_sub')) ?></p>
    </div>
    <div class="champ-grid stagger reveal">
      <?php foreach ($champions as $c): $more = count($c['titles']) - 3; ?>
        <article class="champ-card">
          <div class="champ-head">
            <h3><?= $horseLink($c) ?></h3>
            <span class="champ-meta"><?= e(__('horse.cat_' . $c['category'])) ?><?= $c['dob'] && SiteData::show('profile_age') ? ' · ' . e(horse_age($c['dob'])) : '' ?></span>
          </div>
          <div class="medal-summary">
            <?php foreach ($c['medals'] as $m => $cnt): if (!$cnt) { continue; } ?><span class="medal-pill"><span class="medal <?= e($medalCls[$m]) ?>" aria-hidden="true">★</span> <b><?= (int) $cnt ?></b> <?= e(__('shows.medal_' . $m)) ?></span><?php endforeach; ?>
          </div>
          <ul class="champ-titles">
            <?php foreach (array_slice($c['titles'], 0, 3) as $r): ?>
              <li><span class="medal sm <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span><span><?= $titleLine($r) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="champ-foot">
            <span class="more"><?= $more > 0 ? e(__('site.more_titles', ['n' => $more])) : '' ?></span>
            <?php if ($c['show_on_website']): ?>
              <a href="<?= e(site_url('horses/' . $c['slug'])) ?><?= SiteData::show('profile_results') ? '#results' : '' ?>"><?= e(SiteData::show('profile_results') ? __('site.record_link', ['n' => $c['results']]) : __('site.view_profile')) ?> <span aria-hidden="true"><?= arrow('to') ?></span></a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="chapter night" id="results">
  <div class="container">
    <div class="chapter-head split-head reveal">
      <div><span class="chapter-index"><b>02</b> <?= e(__('site.results_eyebrow')) ?></span><h2 class="split"><?= split_title(__('site.results_by_season'), false) ?></h2></div>
      <p class="lead"><?= e(__(SiteData::medalsOnly() ? 'site.results_sub_medals' : 'site.results_sub')) ?></p>
    </div>
    <div class="tabs" role="tablist" aria-label="<?= e(__('site.results_by_season')) ?>" data-cat-tabs data-param="year" data-default="<?= e($latest) ?>">
      <a class="tab<?= $active === 'all' ? ' on' : '' ?>" href="<?= e($base . '?year=all') ?>#results" data-cat="all" role="tab" aria-selected="<?= $active === 'all' ? 'true' : 'false' ?>"><?= e(__('site.all')) ?> <span class="n"><?= (int) $total ?></span></a>
      <?php foreach ($byYear as $y => $rows): $y = (string) $y; ?>
        <a class="tab<?= $active === $y ? ' on' : '' ?>" href="<?= e($y === $latest ? $base : $base . '?year=' . $y) ?>#results" data-cat="<?= e($y) ?>" role="tab" aria-selected="<?= $active === $y ? 'true' : 'false' ?>"><?= e($y) ?> <span class="n"><?= count($rows) ?></span></a>
      <?php endforeach; ?>
    </div>
    <?php foreach ($byYear as $y => $rows): $y = (string) $y; ?>
      <div class="group result-year" data-group="<?= e($y) ?>"<?= $active !== 'all' && $active !== $y ? ' hidden' : '' ?>>
        <div class="group-head"><h3><?= e($y) ?></h3><span class="group-n"><?= count($rows) ?></span><span class="group-rule" aria-hidden="true"></span></div>
        <ol class="result-list">
          <?php foreach ($rows as $r): ?>
            <li class="result-row">
              <?= $badge($r) ?>
              <div class="rr-main"><span class="rr-horse"><?= $horseLink($r) ?></span><span class="rr-what"><?= e(SiteData::resultText($r)) ?></span></div>
              <div class="rr-show"><span class="rr-show-name"><?= e($showName($r)) ?></span><?php if ($w = $where($r)): ?><small><?= e($w) ?></small><?php endif; ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?= View::partial('site/partials/cta_band') ?>
