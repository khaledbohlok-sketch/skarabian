<?php
use App\Core\Auth;
use App\Resources\Cell;

$cols = array_filter($res->visibleColumns(), fn ($c) => empty($c['list_hidden'])); // 'list_hidden': exported and selected, but shown inside another cell (e.g. a horse's Arabic name under its English one)
$pages = max(1, (int) ceil($total / $q->perPage));
$sort = $_GET['sort'] ?? '';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
// Row checkboxes only when this user has a bulk action to use them for
$bulkBar = $res->bulkSelect ? $res->bulkBarHtml() : '';
$bulk = $bulkBar !== '';
// Phones show each row as a compact card: the title column, the status, the first amount and date and the
// next most telling column; the rest stay one tap away on the record page ('mobile' => true|false overrides).
$mobileKeys = [];
$thumbFirst = false;
$titleKey2 = null;
$seen = [];
foreach ($cols as $key => $c) {
    $fmt = $c['fmt'] ?? '';
    if (isset($c['mobile'])) { if ($c['mobile']) { $mobileKeys[$key] = true; } continue; }
    if (!$mobileKeys) { $mobileKeys[$key] = true; $thumbFirst = $fmt === 'thumb'; continue; }
    if (!empty($thumbFirst) && count($mobileKeys) === 1) { $mobileKeys[$key] = true; $titleKey2 = $key; continue; }
    if (in_array($fmt, ['badge', 'money', 'date'], true) && empty($seen[$fmt])) { $seen[$fmt] = true; $mobileKeys[$key] = true; continue; }
    if (empty($seen['text']) && !in_array($fmt, ['badge', 'money', 'date', 'num', 'enum', 'bool'], true)) { $seen['text'] = true; $mobileKeys[$key] = true; }
}
$cellClass = static function (string $key, array $c) use ($mobileKeys): string {
    $fmt = $c['fmt'] ?? '';
    $cls = [];
    if (in_array($fmt, ['money', 'num'], true)) { $cls[] = 'num'; }
    // short values (amounts, dates, ages, statuses, codes) stay on one line; free text wraps at a readable width
    if (in_array($fmt, ['money', 'date', 'datetime', 'num', 'age', 'expiry', 'badge', 'enum', 'lookup', 'bool', 'lang', 'swatch', 'thumb'], true) || $key === 'number' || $key === 'code' || $key === 'ref') { $cls[] = 'nowrap'; }
    if ($fmt === '' && preg_match('/(description|notes?|message|summary|reason|details|comment|body|text)$/', $key)) { $cls[] = 'c-text'; }
    if (!isset($mobileKeys[$key])) { $cls[] = 'm-hide'; }
    if (!empty($c['hide_below'])) { $cls[] = 'hide-' . $c['hide_below']; } // a less telling column leaves narrower screens (e.g. 'xl': below 1440px)
    return implode(' ', $cls);
};
?>
<?php
// the count with the list's own noun ("12 horses"), else "12 records"
$noun = ['horses' => 'horse', 'embryos' => 'embryo', 'employees' => 'employee', 'bills' => 'bill', 'bill-payments' => 'payment', 'items' => 'item',
    'inventories' => 'store', 'users' => 'user', 'documents' => 'document', 'leave-requests' => 'request'][$res->key] ?? 'record';
$countText = plural((int) $total, \App\Core\Lang::has('count.' . $noun . '_other') ? 'count.' . $noun : 'count.record');
$createUrl = url($res->url(null, '/create')) . ($res->parentField && !empty($_GET[$res->parentField]) ? '?' . $res->parentField . '=' . (int) $_GET[$res->parentField] : '');
$filterDefs = $res->filters();
$activeFilters = count(array_intersect_key(array_filter($_GET, fn ($v) => $v !== '' && $v !== null), $filterDefs));
$searching = ($_GET['q'] ?? '') !== '';
// secondary actions: shown as buttons when there are one or two, otherwise under "More"
$secondary = [];
if (Auth::can($res->module, 'export')) {
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'xlsx', 'page' => null])) . '">' . icon('download') . ' <span>Excel</span></a>';
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'pdf', 'page' => null])) . '" target="_blank">' . icon('print') . ' <span>PDF</span></a>';
}
foreach ($res->listActions() as [$label, $href, $class]) {
    $secondary[] = '<a class="btn ' . e($class) . '" role="menuitem" href="' . e(url($href)) . '"><span>' . e(__($label)) . '</span></a>';
}
if ($res->canCreate() && $res->quickFields()) {
    $secondary[] = '<button type="button" class="btn" role="menuitem" data-add-new="' . e(url($res->url(null, '/create')) . '?quick=1') . '" data-reload-on-create="1">' . icon('plus') . ' <span>' . e(__('common.quick_add')) . '</span></button>';
}
// the filters in use, as chips above the list: "Status: Active ×" — the × link drops that one filter (same address
// rules as the filter form; nothing new is read except the label of the chosen value)
$chips = [];
foreach ($filterDefs as $fk => $f) {
    $fv = $_GET[$fk] ?? '';
    if ($fv === '' || $fv === null || !is_scalar($fv)) { continue; }
    $shown = match ($f['type']) {
        'date_from', 'date_to' => fmt_date((string) $fv),
        'bool', 'archived', 'trashed' => __($fv === '1' ? 'common.yes' : 'common.no'),
        'lookup' => (string) (loc(\App\Core\DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [(int) $fv]) ?? [], 'value') ?: $fv),
        'picker' => (string) (\App\Services\Pickers::label($f['source'], $fv) ?? '#' . $fv),
        'text' => (string) $fv,
        default => (string) (($o = (is_callable($f['options'] ?? null) ? ($f['options'])() : ($f['options'] ?? []))[$fv] ?? null) !== null ? opt_label($o) : $fv),
    };
    $chips[] = [__($f['label']), $shown, query_url([$fk => null, 'page' => null])];
}
// row density: comfortable or compact, remembered per browser (sk_density cookie, switched by portal-motion.js)
$compact = ($_COOKIE['sk_density'] ?? '') === 'compact';
?>
<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p class="muted head-count"><?= e($countText) ?></p>
  </div>
  <div class="actions">
    <?php if ($res->search): ?>
    <form class="head-search" method="get" action="<?= e(url(current_path())) ?>" role="search">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('common.search')) ?>…" aria-label="<?= e(__('common.search')) ?>">
      <?php foreach ($_GET as $gk => $gv): if (in_array($gk, ['q', 'page'], true) || !is_scalar($gv) || $gv === '') { continue; } ?><input type="hidden" name="<?= e($gk) ?>" value="<?= e((string) $gv) ?>"><?php endforeach; ?>
    </form>
    <?php endif; ?>
    <?php if ($filterDefs): ?>
      <button class="btn" type="button" data-toggle-filters aria-controls="list-filters" aria-expanded="<?= $activeFilters ? 'true' : 'false' ?>"><?= icon('filter') ?> <?= e(__('common.filters')) ?><?= $activeFilters ? ' <span class="filter-count">' . $activeFilters . '</span>' : '' ?></button>
    <?php endif; ?>
    <?php if (count($secondary) > 2): ?>
      <div class="dd">
        <button class="btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="list-more" data-dd="list-more"><?= icon('more-horizontal') ?> <?= e(__('ui.more')) ?></button>
        <div class="dd-menu" id="list-more" role="menu" hidden data-align="end"><?= implode('', array_map(fn ($b) => preg_replace('/class="btn(?: [^"]*)?"/', 'class="dd-item"', $b), $secondary)) ?></div>
      </div>
    <?php else: ?>
      <?= implode('', array_map(fn ($b) => str_replace(' role="menuitem"', '', $b), $secondary)) ?>
    <?php endif; ?>
    <?php if ($rows): ?>
      <button class="btn btn-icon density-btn" type="button" data-density aria-pressed="<?= $compact ? 'true' : 'false' ?>" aria-label="<?= e(__('ui.density')) ?>"
        data-tip="<?= e(__($compact ? 'ui.rows_comfortable' : 'ui.rows_compact')) ?>" data-tip-on="<?= e(__('ui.rows_comfortable')) ?>" data-tip-off="<?= e(__('ui.rows_compact')) ?>"><?= icon('list') ?></button>
    <?php endif; ?>
    <?php if ($res->canCreate()): ?>
      <a class="btn btn-primary" href="<?= e($createUrl) ?>"><?= icon('plus') ?> <?= e(__('common.new')) ?></a>
    <?php endif; ?>
  </div>
</div>

<?= \App\Core\View::partial('portal/crud/filters', ['res' => $res, 'q' => $q, 'headSearch' => true]) ?>
<?php if ($chips): ?>
<ul class="filter-chips" aria-label="<?= e(__('common.filters')) ?>">
  <?php foreach ($chips as $n => [$cl, $cv, $drop]): ?>
    <li style="--i:<?= $n ?>"><span class="fc-k"><?= e($cl) ?></span><b><?= e($cv) ?></b><a href="<?= e($drop) ?>" aria-label="<?= e(__('ui.remove_filter', ['name' => $cl])) ?>"><?= icon('x', 'i-16') ?></a></li>
  <?php endforeach; ?>
  <?php if (count($chips) > 1): ?><li class="fc-clear"><a href="<?= e(url(current_path())) ?>"><?= e(__('ui.clear_filters')) ?></a></li><?php endif; ?>
</ul>
<?php endif; ?>
<?php if ($bulk): ?>
<div class="bulk-bar" id="bulk-bar" hidden data-count-tpl="<?= e(__('bills.n_selected')) ?>" data-need-selection="<?= e(__($res->bulkNoneKey)) ?>">
  <span id="bulk-count-text"></span>
  <?= $bulkBar ?>
</div>
<?php endif; ?>

<?php if ($rows): ?>
<div class="table-wrap">
<table class="table<?= $compact ? ' compact' : '' ?>" data-rows-in>
  <thead><tr>
    <?php if ($bulk): ?><th class="bulk-col"><input type="checkbox" id="bulk-all" aria-label="<?= e(__('common.select_all')) ?>"></th><?php endif; ?>
    <?php foreach ($cols as $key => $c): ?>
      <th class="<?= e($cellClass($key, $c)) ?>"<?= !empty($c['sort']) && $sort === $key ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
        <?php if (!empty($c['sort'])): ?>
          <a class="sortable<?= $sort === $key ? ' is-sorted' : '' ?>" href="<?= e(query_url(['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc', 'page' => null])) ?>"><?= e(__($c['label'])) ?><?= $sort === $key ? '<span class="sort-ind" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></a>
        <?php else: ?><?= e(__($c['label'])) ?><?php endif; ?>
      </th>
    <?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $first = true; ?>
    <tr class="<?= e($res->rowClass($r)) ?>" data-row-id="<?= (int) $r['id'] ?>">
      <?php if ($bulk): ?><td class="bulk-col"><input type="checkbox" class="bulk-chk" value="<?= (int) $r['id'] ?>" aria-label="<?= e(__('common.select_all')) ?>"></td><?php endif; ?>
      <?php foreach ($cols as $key => $c): $html = Cell::render($r[$key] ?? null, $c, $r); $blank = in_array(trim(strip_tags($html)), ['', '—', '-'], true); ?>
        <td class="<?= e($cellClass($key, $c) . ($blank && !$first ? ' is-empty' : '') . ($first ? ' is-title' . ($thumbFirst ? ' is-thumb' : '') : '') . ($key === $titleKey2 ? ' is-title2' : '')) ?>" data-label="<?= e(__($c['label'])) ?>">
          <?php if ($first || !empty($c['link'])): $first = false; ?><a href="<?= e(url($res->url((int) $r['id']))) ?>"><?= $html ?></a><?= $res->rowBadge($r) ?>
          <?php else: ?><?= $html ?><?php endif; ?><?= $res->afterCell($key, $r) ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($totals = $res->listTotals($q)): ?>
  <tfoot><tr data-label="<?= e(__('common.total')) ?>">
    <?php if ($bulk): ?><th></th><?php endif; ?>
    <?php foreach ($cols as $key => $c): ?><th class="num<?= !empty($c['hide_below']) ? ' hide-' . e($c['hide_below']) : '' ?>" data-label="<?= e(__($c['label'])) ?>"><?= isset($totals[$key]) ? money($totals[$key]) : '' ?></th><?php endforeach; ?>
  </tr></tfoot>
  <?php endif; ?>
</table>
</div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => $pages, 'per' => $q->perPage, 'total' => (int) $total]) ?>
<?php else: $filtered = $activeFilters || $searching; ?>
  <?= \App\Core\View::partial('portal/partials/empty', [
      'icon' => $filtered ? 'search' : 'list',
      'message' => __($filtered ? 'ui.empty_filtered' : 'common.no_records'),
      'clear' => $filtered ? current_path() : null,
      'cta' => $res->canCreate() && !$filtered ? ['href' => $createUrl, 'label' => __('common.new')] : null,
  ]) ?>
<?php endif; ?>
