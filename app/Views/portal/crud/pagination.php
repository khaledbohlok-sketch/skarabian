<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="<?= e(__('common.pages')) ?>">
  <?php if ($page > 1): ?><a href="<?= e(query_url(['page' => $page - 1])) ?>" rel="prev">‹</a><?php endif; ?>
  <?php
  $window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], fn ($p) => $p >= 1 && $p <= $pages));
  sort($window);
  $prev = 0;
  foreach ($window as $p):
      if ($p - $prev > 1): ?><span class="gap">…</span><?php endif; ?>
      <?php if ($p === $page): ?><span class="current" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(query_url(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
  <?php $prev = $p; endforeach; ?>
  <?php if ($page < $pages): ?><a href="<?= e(query_url(['page' => $page + 1])) ?>" rel="next">›</a><?php endif; ?>
  <span class="per">
    <?php foreach ([25, 50, 100] as $n): ?><a class="<?= (int) ($_GET['per'] ?? 25) === $n ? 'on' : '' ?>" href="<?= e(query_url(['per' => $n, 'page' => null])) ?>"><?= $n ?></a><?php endforeach; ?>
  </span>
</nav>
<?php endif; ?>
