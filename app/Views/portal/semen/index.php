<?php
use App\Core\Auth;
use App\Services\SemenStockService;

$pages = max(1, (int) ceil($total / $q->perPage));
$sort = $_GET['sort'] ?? '';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$summary = SemenStockService::stallionSummary();
$totals = $res->listTotals($q);
$low = (int) setting('semen_stock.low_threshold', 2);
$filterDefs = $res->filters();
$activeFilters = count(array_intersect_key(array_filter($_GET, fn ($v) => $v !== '' && $v !== null), $filterDefs));
$filtered = $activeFilters || ($_GET['q'] ?? '') !== '';
$createUrl = url($res->url(null, '/create'));
// header: one primary (New); every other way in (report, count, import, movements, tanks, colours, Excel) under "More"
$secondary = [];
if (Auth::can('semen_stock', 'print')) { $secondary[] = '<a class="btn" role="menuitem" href="' . e(url('/portal/semen-stock/report')) . '" target="_blank">' . icon('print') . ' <span>' . e(__('semen_stock.report_title')) . '</span></a>'; }
if (Auth::can('semen_stock', 'approve')) { $secondary[] = '<a class="btn" role="menuitem" href="' . e(url('/portal/semen-stock/count')) . '">' . icon('check') . ' <span>' . e(__('semen_stock.count_title')) . '</span></a>'; }
if (Auth::can('semen_stock', 'create')) { $secondary[] = '<a class="btn" role="menuitem" href="' . e(url('/portal/semen-stock/import')) . '">' . icon('upload') . ' <span>' . e(__('semen_stock.import_title')) . '</span></a>'; }
foreach ($res->listActions() as [$label, $href, $class]) {
    $secondary[] = '<a class="btn ' . e($class) . '" role="menuitem" href="' . e(url($href)) . '">' . icon(str_contains($href, 'movements') ? 'history' : (str_contains($href, 'tanks') ? 'flask' : 'list')) . ' <span>' . e(__($label)) . '</span></a>';
}
if (Auth::can('semen_stock', 'export')) { $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'xlsx', 'page' => null])) . '">' . icon('download') . ' <span>Excel</span></a>'; }
// "Low stock" / "Out of stock" as words, not only a red row
$stockBadge = function (int $straws, int $dosesMin) use ($low): string {
    if ($straws <= 0) { return '<span class="badge badge-bad">' . e(__('semen_stock.out_badge')) . '</span>'; }
    return $dosesMin <= $low ? '<span class="badge badge-warn">' . e(__('items.low_stock')) . '</span>' : '';
};
$sortTh = function (string $key, string $label, string $cls = '') use ($sort, $dir): string {
    return '<th class="' . $cls . '"><a href="' . e(query_url(['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc', 'page' => null])) . '">' . e(__($label))
        . ($sort === $key ? '<span class="sort-ind" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '') . '</a></th>';
};
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => $title, 'count' => plural((int) $total, 'count.batch'), 'search' => (bool) $res->search,
    'filters' => $filterDefs ? $activeFilters : null, 'secondary' => $secondary,
    'primary' => $res->canCreate() ? '<a class="btn btn-primary" href="' . e($createUrl) . '">' . icon('plus') . ' ' . e(__('common.new')) . '</a>' : '',
]) ?>

<?php if ($summary): ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('semen_stock.by_stallion')) ?></h2><span class="muted small"><?= e(__('semen_stock.low_rule', ['n' => $low])) ?></span></div>
  <ul class="stock-chips">
    <?php foreach ($summary as $s): $isLow = (int) $s['doses_min'] <= $low; ?>
      <li class="chip<?= $isLow ? ' gold' : '' ?>">
        <b><?= e($s['name'] ?: __('common.unknown')) ?></b>
        <span><?= e(plural((int) $s['straws'], 'count.straw')) ?> · <?= e(plural((int) $s['doses_min'], 'count.dose')) ?></span>
        <?php if ($isLow): ?><span class="badge badge-warn"><?= e(__('items.low_stock')) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?= \App\Core\View::partial('portal/crud/filters', ['res' => $res, 'q' => $q, 'headSearch' => true]) ?>

<?php if ($rows): ?>
<div class="table-wrap">
<table class="table semen-table">
  <thead><tr>
    <?= $sortTh('stallion', 'semen_stock.stallion') ?>
    <th><?= e(__('semen_stock.location')) ?></th>
    <?= $sortTh('straws', 'semen_stock.straws', 'num') ?>
    <th class="num"><?= e(__('semen_stock.per_dose')) ?></th>
    <th class="num"><?= e(__('semen_stock.doses')) ?></th>
    <th class="nowrap"><?= e(__('common.status')) ?></th>
    <?= $sortTh('report_date', 'semen_stock.report_date', 'nowrap') ?>
    <th class="m-hide"><?= e(__('semen_stock.straw_size')) ?></th>
    <th class="m-hide"><?= e(__('semen_stock.color')) ?></th>
    <th class="m-hide"><?= e(__('horses.owner_type')) ?></th>
    <th><span class="sr-only"><?= e(__('common.actions')) ?></span></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $badge = $stockBadge((int) $r['straws'], (int) $r['doses_min']); ?>
    <tr class="<?= e($res->rowClass($r)) ?>">
      <td class="is-title" data-label="<?= e(__('semen_stock.stallion')) ?>"><a href="<?= e(url($res->url((int) $r['id']))) ?>"><?= e($r['stallion'] ?: '—') ?></a></td>
      <td data-label="<?= e(__('semen_stock.location')) ?>"><span class="loc-cell"><?= e($r['tank']) ?> <span class="muted">·</span> <?= e($r['canister']) ?></span></td>
      <td class="num" data-label="<?= e(__('semen_stock.straws')) ?>"><b><?= number_format((int) $r['straws']) ?></b></td>
      <td class="num" data-label="<?= e(__('semen_stock.per_dose')) ?>"><?= e($r['per_dose']) ?></td>
      <td class="num" data-label="<?= e(__('semen_stock.doses')) ?>"><?= e($r['doses']) ?></td>
      <td class="nowrap<?= $badge === '' ? ' is-empty' : '' ?>" data-label="<?= e(__('common.status')) ?>"><?= $badge ?></td>
      <td class="nowrap<?= $r['report_date'] ? '' : ' is-empty' ?>" data-label="<?= e(__('semen_stock.report_date')) ?>"><?= $r['report_date'] ? e(fmt_date($r['report_date'])) : '<span class="muted">—</span>' ?></td>
      <td class="m-hide" data-label="<?= e(__('semen_stock.straw_size')) ?>"><?= $r['size'] ? e($r['size']) : '<span class="muted">—</span>' ?></td>
      <td class="m-hide" data-label="<?= e(__('semen_stock.color')) ?>"><?php if ($r['color']): ?><span class="swatch sm" style="background:<?= e(preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r['color_hex']) ? $r['color_hex'] : '#8a8f98') ?>"></span> <?= e($r['color']) ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td class="m-hide" data-label="<?= e(__('horses.owner_type')) ?>"><?= e($r['owner'] ?? __('horses.owner_sk')) ?></td>
      <td class="doc-row-actions">
        <?php if (Auth::can('semen_stock', 'edit')): ?><a class="btn btn-sm btn-icon" href="<?= e(url('/portal/semen-stock/' . $r['id'] . '/movement')) ?>" aria-label="<?= e(__('semen_stock.record_movement')) ?>" data-tip="<?= e(__('semen_stock.record_movement')) ?>"><?= icon('plus') ?></a><?php endif; ?>
        <?php if ($res->canEdit($r)): ?><a class="btn btn-sm btn-icon" href="<?= e(url($res->url((int) $r['id'], '/edit'))) ?>" aria-label="<?= e(__('common.edit')) ?>" data-tip="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr>
    <th colspan="2"><?= e(__('common.total')) ?></th>
    <th class="num" data-label="<?= e(__('semen_stock.straws')) ?>"><?= number_format((int) $totals['straws']) ?></th>
    <th></th>
    <th class="num" data-label="<?= e(__('semen_stock.doses_min')) ?>"><?= number_format((int) $totals['doses_min']) ?></th>
    <th colspan="6"></th>
  </tr></tfoot>
</table>
</div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => $pages, 'per' => $q->perPage, 'total' => (int) $total]) ?>
<?php else: ?>
  <?= \App\Core\View::partial('portal/partials/empty', [
      'icon' => $filtered ? 'search' : 'flask',
      'message' => __($filtered ? 'ui.empty_filtered' : 'common.no_records'),
      'clear' => $filtered ? current_path() : null,
      'cta' => $res->canCreate() && !$filtered ? ['href' => $res->url(null, '/create'), 'label' => __('common.new')] : null,
  ]) ?>
<?php endif; ?>
