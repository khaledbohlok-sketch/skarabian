<?php
use App\Resources\Cell;
use App\Resources\ListQuery;
use App\Resources\Registry;

$rel = Registry::get($relKey);
if (!$rel || !$rel->canList()) { echo '<div class="card muted">' . e(__('common.access_denied')) . '</div>'; return; }
$input = $_GET;
unset($input['tab']);
$q = new ListQuery($rel, $input);
foreach ($filter as $col => $val) {
    $q->where[] = "t.$col = :rel_$col";
    $q->params["rel_$col"] = $val;
}
$total = $q->count();
$rows = $q->rows();
$cols = array_filter($rel->visibleColumns(), fn ($c) => empty($c['hide_in_parent']));
$prefill = http_build_query($filter);
?>
<section class="card">
  <div class="card-head">
    <h2><?= e(__($rel->title)) ?> <span class="muted">(<?= (int) $total ?>)</span></h2>
    <div class="actions">
      <?php if ($rel->canCreate()): ?><a class="btn btn-primary btn-sm" href="<?= e(url($rel->url(null, '/create')) . '?' . $prefill) ?>">+ <?= e(__('common.add')) ?></a><?php endif; ?>
      <a class="btn btn-sm ghost" href="<?= e(url($rel->url()) . '?' . $prefill) ?>"><?= e(__('common.open_list')) ?></a>
    </div>
  </div>
  <?php if ($note): ?><p class="muted small"><?= e(__($note)) ?></p><?php endif; ?>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><?php foreach ($cols as $c): ?><th class="<?= in_array($c['fmt'] ?? '', ['money', 'num'], true) ? 'num' : '' ?>"><?= e(__($c['label'])) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rows as $r): $first = true; ?><tr>
      <?php foreach ($cols as $key => $c): ?><td class="<?= in_array($c['fmt'] ?? '', ['money', 'num'], true) ? 'num' : '' ?>" data-label="<?= e(__($c['label'])) ?>"><?php if ($first): $first = false; ?><a href="<?= e(url($rel->url((int) $r['id']))) ?>"><?= Cell::render($r[$key] ?? null, $c, $r) ?></a><?php else: ?><?= Cell::render($r[$key] ?? null, $c, $r) ?><?php endif; ?></td><?php endforeach; ?>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => max(1, (int) ceil($total / $q->perPage))]) ?>
  <?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
</section>
