<?php
/** Website → Visitors (CmsController::visitors): visits counted without cookies, see App\Services\SiteVisits. */
use App\Services\Chart;

$num = fn ($n) => number_format((int) $n);
$share = fn (int $n, int $all) => $all > 0 ? (int) round($n * 100 / $all) . '%' : '—';
// up or down against the same number of days before ("" when there is nothing to compare with)
$trend = function (int $now, int $before): string {
    if ($before === 0) {
        return '';
    }
    $p = (int) round(($now - $before) * 100 / $before);
    return ($p >= 0 ? '▲ ' : '▼ ') . abs($p) . '%';
};
?>
<div class="page-head">
  <div><h1><?= e(__('visits.title')) ?></h1><p class="muted"><?= e(__('visits.intro')) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/' . lang())) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('cms.view_site')) ?></a></div>
</div>
<nav class="tabs" aria-label="<?= e(__('visits.period')) ?>"><?php foreach ([7, 30, 90] as $d): ?><a href="<?= e(url('/portal/cms/visitors?days=' . $d)) ?>" class="<?= $days === $d ? 'on' : '' ?>"<?= $days === $d ? ' aria-current="page"' : '' ?>><?= e(__('visits.last_' . $d)) ?></a><?php endforeach; ?></nav>

<?php if (!$on): ?>
<div class="alert alert-warning"><?= e(__('visits.not_ready')) ?></div>
<?php else:
  $t = $totals;
  $devAll = array_sum(array_map('intval', $devices));
  $langAll = array_sum(array_map('intval', $langs)); ?>
<div class="stat-grid">
  <div class="stat dark"><div class="k"><?= e(__('visits.visits')) ?></div><div class="v"><?= $num($t['visits']) ?></div>
    <div class="s"><?= e(__('visits.prev', ['n' => number_format($t['prev_visits'])])) ?><?= ($tr = $trend($t['visits'], $t['prev_visits'])) !== '' ? ' · ' . e($tr) : '' ?></div></div>
  <div class="stat"><div class="k"><?= e(__('visits.views')) ?></div><div class="v"><?= $num($t['views']) ?></div>
    <div class="s"><?= e(__('visits.per_visit')) ?>: <?= $t['visits'] ? e(number_format($t['views'] / $t['visits'], 1)) : '—' ?></div></div>
  <div class="stat"><div class="k"><?= e(__('visits.on_phones')) ?></div><div class="v"><?= e($share((int) ($devices['phone'] ?? 0), $devAll)) ?></div>
    <div class="s"><?= e(__('visits.on_phones_help')) ?></div></div>
  <?php if (can('inbox')): ?><a class="stat" href="<?= e(url('/portal/inbox')) ?>"><div class="k"><?= e(__('visits.messages')) ?></div><div class="v"><?= $num($t['inquiries']) ?></div>
    <div class="s"><?= e(__('visits.messages_help')) ?></div></a><?php endif; ?>
</div>

<?php if (!$t['views']): ?>
<p class="muted"><?= e(__('visits.none')) ?></p>
<?php else: ?>
<section class="card chart">
  <h2><?= e(__('visits.per_day')) ?></h2>
  <?php $labels = array_map(fn ($d) => date('j', strtotime($d)) . ' ' . fmt_month($d, true, false), array_keys($daily)); ?>
  <?= Chart::bars($labels, [[__('visits.visits'), 'gold', array_values($daily)]], 720, 220, $days === 7 ? 1 : ($days === 30 ? 5 : 15), true) ?>
</section>

<div class="grid-2">
  <section class="card">
    <h2><?= e(__('visits.top_horses')) ?></h2>
    <?php if ($horses): ?>
    <div class="table-wrap"><table class="table compact">
      <thead><tr><th><?= e(__('nav.horses')) ?></th><th class="num"><?= e(__('visits.visits')) ?></th><th class="num"><?= e(__('visits.views')) ?></th></tr></thead>
      <tbody><?php foreach ($horses as $h): ?>
        <tr><td data-label="<?= e(__('nav.horses')) ?>"><?php if (can('horses')): ?><a href="<?= e(url('/portal/horses/' . $h['id'])) ?>"><?= e(loc($h)) ?></a><?php else: ?><?= e(loc($h)) ?><?php endif; ?>
          <div class="muted small"><?php if ((int) $h['show_on_website']): ?><a href="<?= e(url('/' . lang() . '/horses/' . $h['slug'])) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('visits.view_page')) ?></a><?php else: ?><?= e(__('visits.hidden_horse')) ?><?php endif; ?></div></td>
          <td class="num" data-label="<?= e(__('visits.visits')) ?>"><?= $num($h['visits']) ?></td><td class="num" data-label="<?= e(__('visits.views')) ?>"><?= $num($h['views']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php else: ?><p class="muted"><?= e(__('visits.no_horse_views')) ?></p><?php endif; ?>
  </section>

  <section class="card">
    <h2><?= e(__('visits.sources')) ?></h2>
    <?php $srcAll = array_sum(array_map(fn ($r) => (int) $r['visits'], $sources)); ?>
    <ul class="alert-list">
      <?php foreach ($sources as $r): ?><li><span><?= e(__('visits.src_' . $r['source'])) ?></span><span class="pill nowrap"><?= $num($r['visits']) ?> · <?= e($share((int) $r['visits'], $srcAll)) ?></span></li><?php endforeach; ?>
    </ul>
    <p class="muted small"><?= e(__('visits.sources_help')) ?></p>
  </section>

  <section class="card">
    <h2><?= e(__('visits.pages')) ?></h2>
    <ul class="alert-list">
      <?php foreach ($pages as $r): ?><li><span><?= e(__('visits.page_' . $r['page'])) ?></span><span class="pill nowrap"><?= $num($r['views']) ?></span></li><?php endforeach; ?>
    </ul>
    <p class="muted small"><?= e(__('visits.pages_help')) ?></p>
  </section>

  <section class="card">
    <h2><?= e(__('visits.countries')) ?></h2>
    <ul class="alert-list">
      <?php foreach ($countries as $c): ?>
        <li><span><?= e($c['country'] ? (loc($c) ?: $c['country']) : __('security.country_unknown')) ?></span><span class="pill nowrap"><?= $num($c['visits']) ?> · <?= e($share((int) $c['visits'], $t['visits'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <h3 class="mt"><?= e(__('visits.devices')) ?></h3>
    <ul class="alert-list">
      <?php foreach ($devices as $dev => $n): ?><li><span><?= e(__('visits.dev_' . $dev)) ?></span><span class="pill nowrap"><?= e($share((int) $n, $devAll)) ?></span></li><?php endforeach; ?>
    </ul>
    <h3 class="mt"><?= e(__('visits.languages')) ?></h3>
    <ul class="alert-list">
      <?php foreach ($langs as $l => $n): ?><li><span><?= e(__('visits.lang_' . $l)) ?></span><span class="pill nowrap"><?= e($share((int) $n, $langAll)) ?></span></li><?php endforeach; ?>
    </ul>
  </section>
</div>
<?php endif; ?>
<?php endif; ?>
