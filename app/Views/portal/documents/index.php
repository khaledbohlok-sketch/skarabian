<?php
use App\Core\Auth;
use App\Resources\Cell;

$cols = $res->visibleColumns();
$pages = max(1, (int) ceil($total / $q->perPage));
$sort = $_GET['sort'] ?? '';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$canUpload = Auth::can('documents', 'create');
$filterDefs = $res->filters();
$activeFilters = count(array_intersect_key(array_filter($_GET, fn ($v) => $v !== '' && $v !== null), $filterDefs));
$filtered = $activeFilters || ($_GET['q'] ?? '') !== '';
$uploadUrl = url('/portal/documents/upload-form');
$secondary = [];
if (Auth::can($res->module, 'export')) {
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'xlsx', 'page' => null])) . '">' . icon('download') . ' <span>Excel</span></a>';
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'pdf', 'page' => null])) . '" target="_blank">' . icon('print') . ' <span>PDF</span></a>';
}
foreach ($res->listActions() as [$label, $href, $class]) {
    $secondary[] = '<a class="btn ' . e($class) . '" role="menuitem" href="' . e(url($href)) . '">' . icon('folder') . ' <span>' . e(__($label)) . '</span></a>';
}
$toggle = $rows ? '<div class="view-toggle" data-view-toggle role="group" aria-label="' . e(__('documents.view_label')) . '">'
    . '<button type="button" class="btn btn-sm" data-view="table" aria-pressed="true">' . icon('menu') . ' <span>' . e(__('documents.view_table')) . '</span></button>'
    . '<button type="button" class="btn btn-sm" data-view="grid" aria-pressed="false">' . icon('box') . ' <span>' . e(__('documents.view_grid')) . '</span></button></div>' : '';
$uploadBtn = $canUpload ? '<button type="button" class="btn btn-primary" data-add-new="' . e($uploadUrl) . '">' . icon('upload') . ' ' . e(__('documents.new_upload')) . '</button>' : '';
$fileUrl = fn (array $r) => url('/portal/files/' . (int) $r['thumb']);
$isImage = fn (array $r) => (string) ($r['type'] ?? '') === 'documents.type_image';
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => $title, 'count' => plural((int) $total, 'count.document'), 'search' => (bool) $res->search,
    'filters' => $filterDefs ? $activeFilters : null, 'secondary' => $secondary, 'extra' => $toggle, 'primary' => $uploadBtn,
]) ?>

<?= \App\Core\View::partial('portal/crud/filters', ['res' => $res, 'q' => $q, 'headSearch' => true]) ?>

<?php if ($rows): ?>
<div data-view-panel="table">
<div class="table-wrap">
<table class="table">
  <thead><tr>
    <?php foreach ($cols as $key => $c): $fmt = $c['fmt'] ?? ''; ?>
      <th class="<?= $fmt === 'thumb' ? 'is-thumb' : (in_array($fmt, ['date', 'lang'], true) ? 'nowrap' : '') ?>">
        <?php if ($fmt === 'thumb'): ?><span class="sr-only"><?= e(__($c['label'])) ?></span>
        <?php elseif (!empty($c['sort'])): ?>
          <a href="<?= e(query_url(['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc', 'page' => null])) ?>"><?= e(__($c['label'])) ?><?= $sort === $key ? '<span class="sort-ind" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></a>
        <?php else: ?><?= e(__($c['label'])) ?><?php endif; ?>
      </th>
    <?php endforeach; ?>
    <th><span class="sr-only"><?= e(__('common.actions')) ?></span></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $view = url($res->url((int) $r['id'])); ?>
    <tr class="<?= e($res->rowClass($r)) ?>">
      <?php foreach ($cols as $key => $c): $fmt = $c['fmt'] ?? ''; $html = Cell::render($r[$key] ?? null, $c, $r); ?>
        <?php if ($fmt === 'thumb'): ?>
          <td class="is-title is-thumb" data-label="<?= e(__($c['label'])) ?>">
            <?php if (!empty($r['thumb'])): ?><a href="<?= e($isImage($r) ? $fileUrl($r) : $view) ?>"<?= $isImage($r) ? ' data-lightbox="1" data-title="' . e($r['title']) . '"' : '' ?> aria-label="<?= e($r['title']) ?>"><?= $html ?></a>
            <?php else: ?><?= $html ?><?php endif; ?>
          </td>
        <?php elseif ($key === 'title'): ?>
          <td class="is-title2" data-label="<?= e(__($c['label'])) ?>"><a href="<?= e($view) ?>"><?= $html ?></a></td>
        <?php else: $blank = in_array(trim(strip_tags($html)), ['', '—'], true); ?>
          <td class="<?= in_array($fmt, ['date', 'lang'], true) ? 'nowrap' : '' ?><?= $blank ? ' is-empty' : '' ?>" data-label="<?= e(__($c['label'])) ?>"><?= $html ?></td>
        <?php endif; ?>
      <?php endforeach; ?>
      <td class="doc-row-actions">
        <?php if (!empty($r['thumb'])): ?><a class="btn btn-sm btn-icon" href="<?= e($fileUrl($r) . '?download=1') ?>" aria-label="<?= e(__('common.download') . ': ' . $r['title']) ?>" data-tip="<?= e(__('common.download')) ?>"><?= icon('download') ?></a><?php endif; ?>
        <?php if ($res->canEdit($r)): ?><a class="btn btn-sm btn-icon" href="<?= e(url($res->url((int) $r['id'], '/edit'))) ?>" aria-label="<?= e(__('common.edit') . ': ' . $r['title']) ?>" data-tip="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<div data-view-panel="grid" hidden>
<ul class="file-grid doc-grid">
  <?php foreach ($rows as $r): $img = $isImage($r); ?>
    <li class="file-item">
      <a class="file-thumb" href="<?= e(!empty($r['thumb']) ? $fileUrl($r) : url($res->url((int) $r['id']))) ?>"<?= $img ? ' data-lightbox="1" data-title="' . e($r['title']) . '"' : ' target="_blank" rel="noopener"' ?>>
        <?php if (!empty($r['thumb'])): // a photo's thumbnail, or a picture of the file's type (PDF…) ?>
          <img src="<?= e($fileUrl($r) . '/thumb') ?>" alt="<?= e($r['title']) ?>" loading="lazy">
          <?php if (!$img && ($r['type'] ?? '') !== ''): ?><span class="file-kind"><?= e(__((string) $r['type'])) ?></span><?php endif; ?>
        <?php else: ?>
          <span class="file-icon"><?= icon('doc', 'i-24') ?></span>
        <?php endif; ?>
      </a>
      <div class="file-meta"><a href="<?= e(url($res->url((int) $r['id']))) ?>"><strong><?= e($r['title']) ?></strong></a><small><?= $r['category'] ? e($r['category']) . ' · ' : '' ?><?= e(fmt_date($r['doc_date'] ?: $r['created'])) ?></small></div>
      <div class="file-actions">
        <?php if (!empty($r['thumb'])): ?><a class="btn btn-sm btn-icon" href="<?= e($fileUrl($r) . '?download=1') ?>" aria-label="<?= e(__('common.download') . ': ' . $r['title']) ?>" data-tip="<?= e(__('common.download')) ?>"><?= icon('download') ?></a><?php endif; ?>
        <?php if ($res->canEdit($r)): ?><a class="btn btn-sm btn-icon" href="<?= e(url($res->url((int) $r['id'], '/edit'))) ?>" aria-label="<?= e(__('common.edit') . ': ' . $r['title']) ?>" data-tip="<?= e(__('common.edit')) ?>"><?= icon('edit') ?></a><?php endif; ?>
        <?php if ($res->canDelete($r)): ?>
          <form method="post" action="<?= e(url($res->url((int) $r['id'], '/delete'))) ?>" data-confirm="<?= e(delete_confirm()) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="<?= e(__('common.delete') . ': ' . $r['title']) ?>" data-tip="<?= e(__('common.delete')) ?>"><?= icon('trash') ?></button></form>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
</div>

<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => $pages, 'per' => $q->perPage, 'total' => (int) $total]) ?>
<?php else: ?>
  <div class="empty card">
    <span class="empty-icon" aria-hidden="true"><?= icon($filtered ? 'search' : 'folder') ?></span>
    <?php if (!$filtered): ?><p class="empty-title"><?= e(__('documents.empty_title')) ?></p><?php endif; ?>
    <p><?= e(__($filtered ? 'ui.empty_filtered' : 'documents.empty_text')) ?></p>
    <?php if ($filtered || $canUpload): ?>
    <div class="empty-actions">
      <?php if ($filtered): ?><a class="btn" href="<?= e(url(current_path())) ?>"><?= icon('x') ?> <?= e(__('ui.clear_filters')) ?></a><?php endif; ?>
      <?php if ($canUpload && !$filtered): ?><button type="button" class="btn btn-primary" data-add-new="<?= e($uploadUrl) ?>"><?= icon('upload') ?> <?= e(__('documents.new_upload')) ?></button><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script src="<?= e(asset('js/documents.js')) ?>" defer></script>
