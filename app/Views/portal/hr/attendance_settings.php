<?php $v = fn (string $field) => (string) old($field, $values[$field]); ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/attendance')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.attendance')) ?></a><h1><?= e($title) ?></h1>
  <p class="muted"><?= e(__('attendance.settings_intro')) ?></p></div></div>
<form method="post" class="card record-form">
  <?= csrf_field() ?>
  <div class="grid-fields">
    <div class="field col-4"><label for="sIn"><?= e(__('attendance.usual_start')) ?></label><input id="sIn" type="time" name="default_in" value="<?= e($v('default_in')) ?>" required></div>
    <div class="field col-4"><label for="sOut"><?= e(__('attendance.usual_end')) ?></label><input id="sOut" type="time" name="default_out" value="<?= e($v('default_out')) ?>" required><?= field_error('default_out') ?></div>
    <div class="field col-4"><label for="sGrace"><?= e(__('attendance.grace')) ?></label><input id="sGrace" type="number" min="0" max="240" step="1" name="grace_minutes" value="<?= e($v('grace_minutes')) ?>" required><?= field_error('grace_minutes') ?><small class="help"><?= e(__('attendance.grace_help')) ?></small></div>
    <div class="field col-4"><label for="sShift"><?= e(__('attendance.max_shift')) ?></label><input id="sShift" type="number" min="8" max="24" step="1" name="max_shift_hours" value="<?= e($v('max_shift_hours')) ?>" required><?= field_error('max_shift_hours') ?><small class="help"><?= e(__('attendance.max_shift_help')) ?></small></div>
    <div class="field col-4"><label for="sBrief"><?= e(__('attendance.brief_time')) ?></label><input id="sBrief" type="time" name="brief_time" value="<?= e($v('brief_time')) ?>" required><?= field_error('brief_time') ?><small class="help"><?= e(__('attendance.brief_time_help')) ?></small></div>
    <div class="field col-12"><label for="sIps"><?= e(__('attendance.scan_ips')) ?></label><input id="sIps" type="text" name="scan_ips" maxlength="500" dir="ltr" value="<?= e($v('scan_ips')) ?>" placeholder="37.210.12.34"><?= field_error('scan_ips') ?>
      <small class="help"><?= e(__('attendance.scan_ips_help', ['ip' => \App\Core\Request::ip()])) ?></small></div>
    <div class="field col-6"><label for="sPin"><?= e(__('attendance.manager_pin')) ?> <span class="pill <?= $pin ? 'ok' : 'warn' ?>"><?= e(__($pin ? 'attendance.pin_is_set' : 'attendance.pin_not_set')) ?></span></label>
      <input id="sPin" type="password" name="pin" inputmode="numeric" pattern="\d{4,8}" maxlength="8" autocomplete="new-password" dir="ltr"><?= field_error('pin') ?><small class="help"><?= e(__('attendance.pin_help')) ?></small></div>
    <?php if ($pin): ?><div class="field col-6 field-check"><label><input type="checkbox" name="pin_clear" value="1"> <?= e(__('attendance.pin_clear')) ?></label></div><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
<section class="card"><h2><?= e(__('attendance.setup_title')) ?></h2>
  <ol class="muted small">
    <?php foreach (['setup_1', 'setup_2', 'setup_3', 'setup_4', 'setup_5'] as $k): ?><li><?= e(__('attendance.' . $k)) ?></li><?php endforeach; ?>
  </ol>
</section>
