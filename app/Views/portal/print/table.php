<h1 class="doc-title"><?= e($title) ?></h1>
<p class="doc-meta"><?= e(__('common.printed_on')) ?>: <?= e(date('d M Y H:i')) ?><?php if (\App\Core\Auth::user()): ?> · <?= e(\App\Core\Auth::user()['name']) ?><?php endif; ?></p>
<?php if (!empty($filters)): ?><p class="doc-meta"><?= e(__('common.filters')) ?>: <?php foreach ($filters as $k => $v): ?><span class="chip"><?= e($k) ?>: <?= e(is_scalar($v) ? $v : '') ?></span> <?php endforeach; ?></p><?php endif; ?>
<table class="doc-table">
  <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td<?= is_float($v) ? ' class="num' . ($v < 0 ? ' neg' : '') . '"' : '' ?>><?= is_float($v) ? e(number_format($v, 2)) : e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($headers) ?>"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
  </tbody>
  <?php if (!empty($totals)): ?><tfoot><tr><?php foreach ($totals as $v): ?><th<?= is_float($v) ? ' class="num' . ($v < 0 ? ' neg' : '') . '"' : '' ?>><?= is_float($v) ? e(number_format($v, 2)) : e($v) ?></th><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table>
