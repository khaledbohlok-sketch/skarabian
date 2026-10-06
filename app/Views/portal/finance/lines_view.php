<?php
use App\Core\DB;
$isPo = $kind === 'po';
$lines = $isPo
  ? DB::all('SELECT l.*, i.name_en, i.unit FROM purchase_order_items l JOIN inventory_items i ON i.id = l.item_id WHERE l.purchase_order_id = ? ORDER BY l.id', [$row['id']])
  : DB::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id', [$row['id']]);
$cur = $row['currency'];
$qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
?>
<section class="card">
  <div class="card-head"><h2><?= e(__($isPo ? 'po.lines' : 'invoices.lines')) ?></h2>
    <?php if ($row['status'] === 'draft' && $res->canEdit($row)): ?><a class="btn btn-sm" href="<?= e(url('/portal/' . ($isPo ? 'purchase-orders' : 'invoices') . '/' . (int) $row['id'] . '/lines')) ?>"><?= icon('edit') ?> <?= e(__($isPo ? 'po.edit_lines' : 'invoices.edit_lines')) ?></a><?php endif; ?></div>
  <?php if ($lines): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__($isPo ? 'nav.items' : 'common.description')) ?></th><th class="num"><?= e(__('common.quantity')) ?></th><th class="num"><?= e(__('lines.unit_price')) ?> (<?= e($cur) ?>)</th><th class="num"><?= e(__('lines.line_total')) ?> (<?= e($cur) ?>)</th><?php if ($cur !== 'QAR'): ?><th class="num">QAR</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($lines as $l): $tot = round((float) $l['quantity'] * (float) $l['unit_price'], 2); ?><tr>
      <td><?php if ($isPo): ?><a href="<?= e(url('/portal/items/' . $l['item_id'])) ?>"><?= e($l['name_en']) ?></a><?php else: ?><?= e($l['description']) ?><?php endif; ?></td>
      <td class="num"><?= e($qty($l['quantity'])) ?><?= $isPo ? ' ' . e($l['unit']) : '' ?></td>
      <td class="num"><?= e(number_format((float) $l['unit_price'], 2)) ?></td>
      <td class="num"><?= e(number_format($tot, 2)) ?></td>
      <?php if ($cur !== 'QAR'): ?><td class="num"><?= money($isPo ? $l['line_total_qar'] : round($tot * (float) $row['exchange_rate'], 2)) ?></td><?php endif; ?>
    </tr><?php endforeach; ?></tbody>
    <tfoot><tr><th colspan="3"><?= e(__('lines.total')) ?></th><th class="num"><?= e(number_format($isPo ? array_sum(array_map(fn ($l) => round((float) $l['quantity'] * (float) $l['unit_price'], 2), $lines)) : (float) $row['total_original'], 2)) ?> <?= e($cur) ?></th><?php if ($cur !== 'QAR'): ?><th class="num"><?= money($row['total_qar']) ?></th><?php endif; ?></tr></tfoot>
  </table></div>
  <?php if ($cur !== 'QAR'): ?><p class="muted small"><?= e(__('lines.rate_note', ['rate' => rtrim(rtrim((string) $row['exchange_rate'], '0'), '.'), 'cur' => $cur])) ?></p><?php endif; ?>
  <?php else: ?><p class="muted"><?= e(__('lines.none')) ?></p><?php endif; ?>
</section>
