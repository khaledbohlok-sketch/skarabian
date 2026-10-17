<?php
use App\Services\HorseService;
$sent = old('date') !== null; // the form came back with errors: keep what was ticked
$oType = old('owner_type', ($current['owner_type'] ?? 'sk') === 'sk' ? 'client' : 'sk');
?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/horses/' . $h['id'] . '?tab=ownership')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('common.back')) ?></a><h1><?= e($title) ?></h1></div></div>
<form method="post" class="card form-stack record-form form-md" data-confirm="<?= e(__('ownership.confirm')) ?>">
  <?= csrf_field() ?>
  <p class="muted"><?= e(__('ownership.intro')) ?></p>
  <?php if ($current): ?>
    <div class="alert alert-info"><?= e(__('ownership.current_owner')) ?>: <b><?= e(HorseService::ownerLabel($current)) ?></b> · <?= e(__('ownership.since')) ?> <?= e(fmt_date($current['from_date'])) ?> · <?= status_badge($h['status'], 'horse.status') ?></div>
  <?php endif; ?>
  <div class="grid-fields">
    <div class="field col-4"><label><?= e(__('ownership.new_owner')) ?> <span class="req">*</span></label>
      <select name="owner_type" required>
        <?php foreach (['client' => 'ownership.owner_client', 'other' => 'ownership.owner_other', 'sk' => 'horses.owner_sk'] as $v => $k): ?><option value="<?= $v ?>"<?= selected($oType, $v) ?>><?= e(__($k)) ?></option><?php endforeach; ?>
      </select><?= field_error('owner_type') ?></div>
    <div class="field col-8"><label><?= e(__('horses.owner_party')) ?></label><select name="owner_party_id" data-picker="clients"><option value=""><?= e(__('common.search_choose')) ?></option></select><?= field_error('owner_party_id') ?></div>
    <div class="field col-12"><label><?= e(__('horses.owner_name')) ?></label><input type="text" name="owner_name" maxlength="150" value="<?= e(old('owner_name')) ?>"><small class="help"><?= e(__('ownership.owner_name_help')) ?></small><?= field_error('owner_name') ?></div>
    <div class="field col-4"><label><?= e(__('ownership.date')) ?> <span class="req">*</span></label><input type="date" name="date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('date', date('Y-m-d'))) ?>"><?= field_error('date') ?></div>
    <div class="field col-4"><label><?= e(__('ownership.transfer_type')) ?> <span class="req">*</span></label>
      <select name="transfer_type" required><?php foreach (HorseService::TRANSFER_TYPES as $t): ?><option value="<?= $t ?>"<?= selected(old('transfer_type', 'sale'), $t) ?>><?= e(__('ownership.type_' . $t)) ?></option><?php endforeach; ?></select><?= field_error('transfer_type') ?></div>
    <div class="field col-4"><label><?= e(__('ownership.new_status')) ?></label>
      <select name="status"><option value=""><?= e(__('ownership.status_auto')) ?></option><?php foreach (HorseService::TRANSFER_STATUSES as $s): ?><option value="<?= $s ?>"<?= selected(old('status', ''), $s) ?>><?= e(__('horse.status_' . $s)) ?></option><?php endforeach; ?></select>
      <small class="help"><?= e(__('ownership.status_help')) ?></small><?= field_error('status') ?></div>
    <?php if ($canPrice): ?>
    <div class="field col-4"><label><?= e(__('ownership.price')) ?></label><input type="text" name="price" inputmode="decimal" value="<?= e(old('price')) ?>"><?= field_error('price') ?></div>
    <div class="field col-4"><label><?= e(__('finance.currency')) ?></label><select name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c['code']) ?>"<?= selected(old('currency', 'QAR'), $c['code']) ?>><?= e($c['code']) ?> — <?= e(loc($c)) ?></option><?php endforeach; ?></select><?= field_error('currency') ?></div>
    <?php if ($canBill): ?>
    <div class="field field-check col-12"><label><input type="checkbox" name="create_bill" value="1"<?= (!$sent || old('create_bill')) ? ' checked' : '' ?>> <?= e(__('ownership.create_bill')) ?></label><small class="help"><?= e(__('ownership.create_bill_help')) ?></small></div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="field col-12"><label><?= e(__('common.notes')) ?></label><textarea name="notes" rows="3"><?= e(old('notes')) ?></textarea></div>
  </div>
  <div><button class="btn btn-primary btn-lg" type="submit"><?= e(__('ownership.save')) ?></button></div>
</form>
