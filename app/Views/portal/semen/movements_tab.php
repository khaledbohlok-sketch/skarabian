<?php
use App\Core\DB;
$items = DB::all('SELECT m.*, u.name AS by_name FROM semen_movements m LEFT JOIN users u ON u.id = m.created_by WHERE m.stock_id = ? ORDER BY m.id DESC', [(int) $row['id']]);
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('semen_stock.movements')) ?></h2></div>
  <?php if ($items): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('common.type')) ?></th><th class="num"><?= e(__('semen_stock.straws')) ?></th><th><?= e(__('semen_stock.recipient')) ?> / <?= e(__('semen_stock.reason')) ?></th><th><?= e(__('common.notes')) ?></th><th><?= e(__('common.by')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($items as $m): ?>
      <tr>
        <td><?= e(fmt_date($m['movement_date'])) ?></td>
        <td><?= status_badge($m['type'], 'semen_stock.type') ?></td>
        <td class="num"><?= $m['straws_delta'] > 0 ? '+' : '' ?><?= (int) $m['straws_delta'] ?></td>
        <td><?= e($m['recipient'] ?: $m['reason'] ?: '—') ?></td>
        <td class="muted small"><?= e($m['notes'] ?: '—') ?></td>
        <td class="muted small"><?= e($m['by_name'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="muted"><?= e(__('semen_stock.no_movements')) ?></p><?php endif; ?>
</section>
