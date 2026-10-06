<?php use App\Core\Auth; use App\Services\Money; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/horses/' . $h['id'])) ?>">← <?= e($h['name_en']) ?></a><h1><?= e($title) ?></h1></div></div>
<form method="post" class="card form-stack" style="max-width:720px">
  <?= csrf_field() ?>
  <p class="muted"><?= e(__('horses.transfer_intro')) ?></p>
  <div class="grid-fields">
    <div class="field col-6"><label><?= e(__('common.type')) ?></label><select name="event_type"><option value="transfer"><?= e(__('ownership.type_transfer')) ?></option><option value="sale"<?= selected(old('event_type'), 'sale') ?>><?= e(__('ownership.type_sale')) ?></option></select></div>
    <div class="field col-6"><label><?= e(__('common.date')) ?> <span class="req">*</span></label><input type="date" name="date" required value="<?= e(old('date', date('Y-m-d'))) ?>"><?= field_error('date') ?></div>
    <div class="field col-12"><label><?= e(__('horses.new_owner')) ?> <span class="req">*</span></label>
      <div class="picker-row"><select id="f_party" name="party_id" data-picker="clients" required><option value=""><?= e(__('common.search_choose')) ?></option></select>
      <?php if (Auth::can('finance', 'create')): ?><button type="button" class="btn btn-sm" data-add-new="<?= e(url('/portal/parties/create?type=client')) ?>" data-target="f_party">+ <?= e(__('common.add_new')) ?></button><?php endif; ?></div><?= field_error('party_id') ?></div>
    <?php if (Auth::can('horses', 'sensitive')): ?>
    <div class="field col-4 field-sensitive"><label><?= e(__('horses.price')) ?> 🔒</label><input type="text" inputmode="decimal" name="price" value="<?= e(old('price')) ?>" dir="ltr"><?= field_error('price') ?></div>
    <div class="field col-4"><label><?= e(__('finance.currency')) ?></label><select name="currency" data-currency><?php foreach (Money::currencies() as $c): ?><option value="<?= e($c['code']) ?>" data-rate="<?= e($c['rate_to_qar']) ?>"<?= selected(old('currency', 'QAR'), $c['code']) ?>><?= e($c['code']) ?></option><?php endforeach; ?></select></div>
    <div class="field col-4"><label><?= e(__('finance.exchange_rate')) ?></label><input type="text" name="exchange_rate" value="<?= e(old('exchange_rate', '1')) ?>" dir="ltr"></div>
    <?php endif; ?>
    <div class="field col-12"><label><?= e(__('common.notes')) ?></label><input type="text" name="notes" maxlength="255" value="<?= e(old('notes')) ?>"></div>
  </div>
  <div class="alert alert-info"><?= e(__('horses.transfer_approval_note')) ?></div>
  <div><button class="btn btn-primary" type="submit"><?= e(__('common.submit_approval')) ?></button></div>
</form>
