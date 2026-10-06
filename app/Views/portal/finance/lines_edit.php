<?php
$isPo = $kind === 'po';
$back = '/portal/' . ($isPo ? 'purchase-orders' : 'invoices') . '/' . (int) $row['id'];
$rows = $lines;
$target = max(6, count($rows) + 3);
while (count($rows) < $target) { $rows[] = []; }
?>
<div class="page-head"><div><a class="crumb" href="<?= e(url($back)) ?>">← <?= e($row['number']) ?></a><h1><?= e(__($isPo ? 'po.edit_lines' : 'invoices.edit_lines')) ?></h1>
  <p class="muted"><?= e(__('lines.currency_note', ['cur' => $row['currency']])) ?></p></div></div>
<form method="post" class="card" action="<?= e(url($back . '/lines')) ?>">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table lines-table">
    <thead><tr><th><?= e(__($isPo ? 'nav.items' : 'common.description')) ?></th><th style="width:130px"><?= e(__('common.quantity')) ?></th><th style="width:160px"><?= e(__('lines.unit_price')) ?> (<?= e($row['currency']) ?>)</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $l): ?>
      <tr>
        <td data-label="<?= e(__($isPo ? 'nav.items' : 'common.description')) ?>"><?php if ($isPo): ?>
          <select name="lines[<?= $i ?>][item_id]" data-picker="items">
            <option value=""><?= e(__('common.search_choose')) ?></option>
            <?php if (!empty($l['item_id'])): ?><option value="<?= (int) $l['item_id'] ?>" selected><?= e(\App\Services\Pickers::label('items', $l['item_id']) ?? '#' . $l['item_id']) ?></option><?php endif; ?>
          </select>
        <?php else: ?>
          <input type="text" name="lines[<?= $i ?>][description]" maxlength="255" value="<?= e($l['description'] ?? '') ?>">
        <?php endif; ?></td>
        <td data-label="<?= e(__('common.quantity')) ?>"><input type="text" inputmode="decimal" dir="ltr" name="lines[<?= $i ?>][quantity]" value="<?= e(isset($l['quantity']) ? rtrim(rtrim((string) $l['quantity'], '0'), '.') : '') ?>"></td>
        <td data-label="<?= e(__('lines.unit_price')) ?>"><input type="text" inputmode="decimal" dir="ltr" name="lines[<?= $i ?>][unit_price]" value="<?= e($l['unit_price'] ?? '') ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small"><?= e(__('lines.help')) ?></p>
  <?= field_error('lines') ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button><a class="btn ghost" href="<?= e(url($back)) ?>"><?= e(__('common.cancel')) ?></a></div>
</form>
