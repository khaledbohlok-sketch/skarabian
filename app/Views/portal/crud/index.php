<?php
use App\Core\Auth;
use App\Resources\Cell;

$cols = $res->visibleColumns();
$pages = max(1, (int) ceil($total / $q->perPage));
$sort = $_GET['sort'] ?? '';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
?>
<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e(__('common.n_records', ['n' => number_format($total)])) ?></p>
  </div>
  <div class="actions">
    <?php if (Auth::can($res->module, 'export')): ?>
      <a class="btn" href="<?= e(query_url(['export' => 'xlsx', 'page' => null])) ?>"><?= icon('download') ?> Excel</a>
      <a class="btn" href="<?= e(query_url(['export' => 'pdf', 'page' => null])) ?>" target="_blank"><?= icon('print') ?> PDF</a>
    <?php endif; ?>
    <?php foreach ($res->listActions() as [$label, $href, $class]): ?>
      <a class="btn <?= e($class) ?>" href="<?= e(url($href)) ?>"><?= e(__($label)) ?></a>
    <?php endforeach; ?>
    <?php if ($res->canCreate()): ?>
      <a class="btn btn-primary" href="<?= e(url($res->url(null, '/create')) . ($res->parentField && !empty($_GET[$res->parentField]) ? '?' . $res->parentField . '=' . (int) $_GET[$res->parentField] : '')) ?>"><?= icon('plus') ?> <?= e(__('common.new')) ?></a>
    <?php endif; ?>
  </div>
</div>

<?= \App\Core\View::partial('portal/crud/filters', ['res' => $res, 'q' => $q]) ?>

<?php if ($rows): ?>
<div class="table-wrap">
<table class="table">
  <thead><tr>
    <?php foreach ($cols as $key => $c): ?>
      <th class="<?= in_array($c['fmt'] ?? '', ['money', 'num'], true) ? 'num' : '' ?>">
        <?php if (!empty($c['sort'])): ?>
          <a href="<?= e(query_url(['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc', 'page' => null])) ?>"><?= e(__($c['label'])) ?><?= $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' ?></a>
        <?php else: ?><?= e(__($c['label'])) ?><?php endif; ?>
      </th>
    <?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $first = true; ?>
    <tr class="<?= e($res->rowClass($r)) ?>">
      <?php foreach ($cols as $key => $c): ?>
        <td class="<?= in_array($c['fmt'] ?? '', ['money', 'num'], true) ? 'num' : '' ?>" data-label="<?= e(__($c['label'])) ?>">
          <?php if ($first || !empty($c['link'])): $first = false; ?><a href="<?= e(url($res->url((int) $r['id']))) ?>"><?= Cell::render($r[$key] ?? null, $c, $r) ?></a>
          <?php else: ?><?= Cell::render($r[$key] ?? null, $c, $r) ?><?php endif; ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($totals = $res->listTotals($q)): ?>
  <tfoot><tr>
    <?php foreach ($cols as $key => $c): ?><th class="num"><?= isset($totals[$key]) ? money($totals[$key]) : '' ?></th><?php endforeach; ?>
  </tr></tfoot>
  <?php endif; ?>
</table>
</div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $q->page, 'pages' => $pages]) ?>
<?php else: ?>
  <div class="empty card"><?= icon('search') ?><p><?= e(__('common.no_records')) ?></p></div>
<?php endif; ?>
