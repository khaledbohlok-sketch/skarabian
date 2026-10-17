<?php
// shown when there is more than one page, and also after a page size was picked (so another can be chosen again).
// $total (optional): "1–25 of 312" before the page links.
$per = (int) ($per ?? ($_GET['per'] ?? 25));
if ($pages > 1 || !empty($_GET['per'])): ?>
<nav class="pagination" aria-label="<?= e(__('common.pages')) ?>">
  <?php if (isset($total) && $total > 0): $from = ($page - 1) * $per + 1; $to = min($total, $page * $per); ?>
    <span class="range"><?= e(__('ui.range_of', ['from' => number_format($from), 'to' => number_format($to), 'total' => number_format((int) $total)])) ?></span>
  <?php endif; ?>
  <?php if ($page > 1): ?><a class="pg-arrow" href="<?= e(query_url(['page' => $page - 1])) ?>" rel="prev" aria-label="<?= e(__('ui.prev_page')) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
  <?php
  $window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], fn ($p) => $p >= 1 && $p <= $pages));
  sort($window);
  $prev = 0;
  foreach ($window as $p):
      if ($p - $prev > 1): ?><span class="gap" aria-hidden="true">…</span><?php endif; ?>
      <?php if ($p === $page): ?><span class="current" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(query_url(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
  <?php $prev = $p; endforeach; ?>
  <?php if ($page < $pages): ?><a class="pg-arrow" href="<?= e(query_url(['page' => $page + 1])) ?>" rel="next" aria-label="<?= e(__('ui.next_page')) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
  <span class="per">
    <?php foreach ([25, 50, 100] as $n): ?><a class="<?= $per === $n ? 'on' : '' ?>" href="<?= e(query_url(['per' => $n, 'page' => null])) ?>"<?= $per === $n ? ' aria-current="true"' : '' ?>><?= $n ?></a><?php endforeach; ?>
  </span>
</nav>
<?php endif; ?>
