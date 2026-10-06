<?php $q = rtrim(rtrim(number_format((float) $item['quantity'], 3, '.', ','), '0'), '.'); $dir = old('direction') ?? ($_GET['direction'] ?? 'in'); ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/items/' . (int) $item['id'])) ?>">← <?= e($item['name_en']) ?></a><h1><?= e(__('items.stock_move')) ?></h1>
  <p class="muted"><?= e(__('items.in_stock')) ?>: <b><?= e($q . ' ' . $item['unit']) ?></b> · <?= e(__('items.unit_price')) ?>: <?= money($item['unit_price_qar']) ?></p></div></div>
<form method="post" class="card form-stack" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="grid-fields">
    <div class="field col-12"><label><?= e(__('movements.direction')) ?></label>
      <div class="segmented">
        <?php foreach (['in' => 'items.dir_in_long', 'out' => 'items.dir_out_long', 'adjust' => 'items.dir_adjust_long'] as $k => $l): ?>
          <label><input type="radio" name="direction" value="<?= e($k) ?>"<?= checked($dir === $k) ?>> <?= e(__($l)) ?></label>
        <?php endforeach; ?>
      </div><?= field_error('direction') ?></div>
    <div class="field col-4"><label for="f_qty"><?= e(__('common.quantity')) ?> (<?= e($item['unit']) ?>) <span class="req">*</span></label>
      <input id="f_qty" type="text" inputmode="decimal" dir="ltr" name="quantity" value="<?= e(old('quantity')) ?>" required>
      <small class="help"><?= e(__('items.qty_help')) ?></small><?= field_error('quantity') ?></div>
    <div class="field col-4"><label for="f_price"><?= e(__('items.unit_price')) ?> (QAR)</label>
      <input id="f_price" type="text" inputmode="decimal" dir="ltr" name="unit_price" value="<?= e(old('unit_price')) ?>" placeholder="<?= e(number_format((float) $item['unit_price_qar'], 2, '.', '')) ?>">
      <small class="help"><?= e(__('items.price_help')) ?></small><?= field_error('unit_price') ?></div>
    <div class="field col-4"><label for="f_date"><?= e(__('common.date')) ?></label>
      <input id="f_date" type="date" name="date" value="<?= e(old('date') ?? date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"><?= field_error('date') ?></div>
    <div class="field col-12"><label for="f_horse_id"><?= e(__('items.used_on_horse')) ?></label>
      <div class="picker-row"><select id="f_horse_id" name="horse_id" data-picker="horses"><option value=""><?= e(__('common.search_choose')) ?></option></select></div>
      <small class="help"><?= e(__('items.horse_help')) ?></small><?= field_error('horse_id') ?></div>
    <div class="field col-12"><label for="f_note"><?= e(__('common.notes')) ?></label><input id="f_note" type="text" name="note" maxlength="255" value="<?= e(old('note')) ?>"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button><a class="btn ghost" href="<?= e(url('/portal/items/' . (int) $item['id'])) ?>"><?= e(__('common.cancel')) ?></a></div>
</form>
