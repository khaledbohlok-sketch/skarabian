<?php
use App\Core\Auth;
use App\Resources\Cell;

$cols = $res->visibleColumns();
$pages = max(1, (int) ceil($total / $q->perPage));
$sort = $_GET['sort'] ?? '';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$trashedView = ($_GET['trashed'] ?? '') === '1';
$filterDefs = $res->filters();
$activeFilters = count(array_intersect_key(array_filter($_GET, fn ($v) => $v !== '' && $v !== null), $filterDefs));
$filtered = $activeFilters || ($_GET['q'] ?? '') !== '';
$secondary = [];
if (Auth::can($res->module, 'export')) {
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'xlsx', 'page' => null])) . '">' . icon('download') . ' <span>Excel</span></a>';
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'pdf', 'page' => null])) . '" target="_blank">' . icon('print') . ' <span>PDF</span></a>';
}
foreach ($res->listActions() as [$label, $href, $class]) {
    $secondary[] = '<a class="btn ' . e($class) . '" role="menuitem" href="' . e(url($href)) . '"><span>' . e(__($label)) . '</span></a>';
}
$canNew = !$trashedView && $res->canCreate();
$cls = static function (string $key, array $c): string {
    $fmt = $c['fmt'] ?? '';
    return in_array($fmt, ['money', 'num'], true) ? 'num' : (in_array($fmt, ['badge', 'enum', 'datetime', 'date', 'bool'], true) || $key === 'user' ? 'nowrap' : '');
};
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => $title, 'count' => plural((int) $total, 'count.user'), 'search' => (bool) $res->search,
    'filters' => $filterDefs ? $activeFilters : null, 'secondary' => $secondary,
    'primary' => $canNew ? '<a class="btn btn-primary" href="' . e(url($res->url(null, '/create'))) . '">' . icon('user-plus') . ' ' . e(__('common.new')) . '</a>' : '',
]) ?>

<?= \App\Core\View::partial('portal/crud/filters', ['res' => $res, 'q' => $q, 'headSearch' => true]) ?>

<?php if ($rows): ?>
<div class="table-wrap">
<table class="table">
  <thead><tr>
    <?php foreach ($cols as $key => $c): ?>
      <th class="<?= e($cls($key, $c)) ?>">
        <?php if (!empty($c['sort'])): ?>
          <a href="<?= e(query_url(['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc', 'page' => null])) ?>"><?= e(__($c['label'])) ?><?= $sort === $key ? '<span class="sort-ind" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></a>
        <?php else: ?><?= e(__($c['label'])) ?><?php endif; ?>
      </th>
    <?php endforeach; ?>
    <th><span class="sr-only"><?= e(__('common.actions')) ?></span></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $first = true; ?>
    <tr class="<?= e($res->rowClass($r)) ?>">
      <?php foreach ($cols as $key => $c): $html = Cell::render($r[$key] ?? null, $c, $r); $blank = in_array(trim(strip_tags($html)), ['', '—'], true); ?>
        <?php if ($first): $first = false; $who = '<span class="avatar sm" aria-hidden="true">' . e(mb_substr((string) ($r[$key] ?? '?'), 0, 1)) . '</span> ' . $html; ?>
          <td class="is-title" data-label="<?= e(__($c['label'])) ?>"><span class="user-cell"><?= $trashedView ? $who : '<a href="' . e(url($res->url((int) $r['id']))) . '">' . $who . '</a>' ?></span></td>
        <?php else: ?>
          <td class="<?= e($cls($key, $c)) ?><?= $blank ? ' is-empty' : '' ?>" data-label="<?= e(__($c['label'])) ?>"<?= $key === 'user' ? ' dir="ltr"' : '' ?>><?= $html ?></td>
        <?php endif; ?>
      <?php endforeach; ?>
      <td class="doc-row-actions">
        <?php if ($trashedView): ?>
          <?php if (Auth::isOwner()): ?>
            <form method="post" action="<?= e(url('/portal/users/' . (int) $r['id'] . '/restore')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('refresh') ?> <?= e(__('users.restore')) ?></button></form>
            <form method="post" action="<?= e(url('/portal/users/' . (int) $r['id'] . '/purge')) ?>" class="inline" data-confirm="<?= e(__('trash.confirm_purge')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('users.purge')) ?></button></form>
          <?php endif; ?>
        <?php elseif ($res->canDelete($r)): ?>
          <form method="post" action="<?= e(url('/portal/users/' . (int) $r['id'] . '/delete')) ?>" class="inline" data-confirm-type="<?= e($r['user'] ?? '') ?>" data-confirm-type-message="<?= e(__('common.confirm_delete_type', ['value' => $r['user'] ?? ''])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="<?= e(__('common.delete') . ': ' . ($r['user'] ?? '')) ?>" data-tip="<?= e(__('common.delete')) ?>"><?= icon('trash') ?></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => $pages, 'per' => $q->perPage, 'total' => (int) $total]) ?>
<?php else: ?>
  <?= \App\Core\View::partial('portal/partials/empty', [
      'icon' => $filtered ? 'search' : 'users',
      'message' => __($filtered ? 'ui.empty_filtered' : 'common.no_records'),
      'clear' => $filtered ? current_path() : null,
      'cta' => $canNew && !$filtered ? ['href' => $res->url(null, '/create'), 'label' => __('common.new')] : null,
  ]) ?>
<?php endif; ?>
