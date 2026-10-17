<?php
/**
 * All horses on the website by category (stallions, mares, fillies, colts, foals), with tabs to show one category.
 * $groups = [category => horses]; $counts = [category => n]; $active = category or ''; $rows = true (homepage): on a
 * phone each category is one row to swipe, until a tab picks one category. The tabs are links (?category=…), so they
 * work without JavaScript; site.js filters in place instead.
 */
use App\Core\View;

$labels = ['stallion' => 'site.f_stallions', 'mare' => 'site.f_mares', 'filly' => 'site.f_fillies', 'colt' => 'site.f_colts', 'foal' => 'site.f_foals'];
$counts = $counts ?? array_map('count', $groups);
$active = $active ?? '';
$base = $base ?? site_url('horses');
$rows = $rows ?? false;
$editorial = !empty($editorial) || !$rows; // the first horse of each category as a plate, twice the width (computers)
?>
<div class="tabs" role="tablist" aria-label="<?= e(__('site.our_horses')) ?>" data-cat-tabs>
  <a class="tab<?= $active === '' ? ' on' : '' ?>" href="<?= e($base) ?>" data-cat="all" role="tab" aria-selected="<?= $active === '' ? 'true' : 'false' ?>"><?= e(__('site.all')) ?> <span class="n"><?= (int) array_sum($counts) ?></span></a>
  <?php foreach ($counts as $cat => $n): ?>
    <a class="tab<?= $active === $cat ? ' on' : '' ?>" href="<?= e($base . '?category=' . $cat) ?>" data-cat="<?= e($cat) ?>" role="tab" aria-selected="<?= $active === $cat ? 'true' : 'false' ?>"><?= e(__($labels[$cat])) ?> <span class="n"><?= (int) $n ?></span></a>
  <?php endforeach; ?>
</div>
<div class="horse-groups<?= $rows ? ' rows-on-phone' : '' ?><?= $active !== '' ? ' filtered' : '' ?>">
<?php foreach ($groups as $cat => $list): ?>
  <div class="group" data-group="<?= e($cat) ?>"<?= $active !== '' && $active !== $cat ? ' hidden' : '' ?>>
    <div class="group-head"><h3><?= e(__($labels[$cat])) ?></h3><span class="group-n"><?= count($list) ?></span><span class="group-rule" aria-hidden="true"></span></div>
    <div class="hgrid stagger reveal<?= $editorial ? ' editorial' : '' ?>" data-parade>
      <?php foreach ($list as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?>
    </div>
    <?php if ($rows): ?><div class="row-foot" aria-hidden="true"><span class="row-progress"><i></i></span><span class="row-n" dir="ltr"><b>1</b> / <?= count($list) ?></span></div><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
