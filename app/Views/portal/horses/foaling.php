<?php use App\Core\DB; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url($embryo ? '/portal/embryos/' . $embryo['id'] : '/portal/horses/' . $mare['id'])) ?>">← <?= e(__('common.back')) ?></a><h1><?= e($title) ?></h1></div></div>
<form method="post" class="card form-stack" style="max-width:720px">
  <?= csrf_field() ?>
  <p class="muted"><?= e(__('horses.foaling_intro')) ?></p>
  <?php if ($embryo): ?>
    <div class="alert alert-info"><?= e(__('horses.foaling_embryo', ['code' => $embryo['code']])) ?></div>
  <?php elseif ($records): ?>
    <label><?= e(__('nav.breeding')) ?>
      <select name="breeding_record_id"><?php foreach ($records as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($_GET['breeding_record_id'] ?? '', $r['id']) ?>><?= e(fmt_date($r['start_date'])) ?> · <?= e(__('breeding.method_' . $r['method'])) ?> · <?= e($r['stallion'] ?? $r['code']) ?> (<?= e(__('breeding.status_' . $r['status'])) ?>)</option><?php endforeach; ?><option value=""><?= e(__('horses.no_breeding_record')) ?></option></select>
    </label>
  <?php else: ?>
    <label><?= e(__('horse.sire')) ?><select name="stallion_id" data-picker="stallions"><option value=""><?= e(__('common.search_choose')) ?></option></select></label>
  <?php endif; ?>
  <div class="grid-fields">
    <div class="field col-6"><label><?= e(__('horses.foal_name')) ?></label><input type="text" name="name_en" maxlength="120" value="<?= e(old('name_en')) ?>"></div>
    <div class="field col-6"><label><?= e(__('common.name_ar')) ?></label><input type="text" name="name_ar" maxlength="120" dir="rtl" value="<?= e(old('name_ar')) ?>"></div>
    <div class="field col-4"><label><?= e(__('horses.foaling_date')) ?> <span class="req">*</span></label><input type="date" name="dob" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('dob', date('Y-m-d'))) ?>"><?= field_error('dob') ?></div>
    <div class="field col-4"><label><?= e(__('horse.sex')) ?> <span class="req">*</span></label><select name="sex" required><option value=""><?= e(__('common.choose')) ?></option><option value="male"<?= selected(old('sex'), 'male') ?>><?= e(__('horses.colt_foal')) ?></option><option value="female"<?= selected(old('sex'), 'female') ?>><?= e(__('horses.filly_foal')) ?></option></select><?= field_error('sex') ?></div>
    <div class="field col-4"><label><?= e(__('horse.color')) ?></label><select name="color_id"><option value=""><?= e(__('common.choose')) ?></option><?php foreach (DB::all("SELECT id, value_en, value_ar FROM lookups WHERE type='color' AND active=1 ORDER BY sort") as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(loc($c, 'value')) ?></option><?php endforeach; ?></select></div>
    <div class="field col-6"><label><?= e(__('horses.location')) ?></label><select name="location_id"><option value=""><?= e(__('common.choose')) ?></option><?php foreach (DB::all("SELECT id, value_en, value_ar FROM lookups WHERE type='location' AND active=1 ORDER BY sort") as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($mare['location_id'] ?? '', $c['id']) ?>><?= e(loc($c, 'value')) ?></option><?php endforeach; ?></select></div>
    <div class="field col-12"><label><?= e(__('common.notes')) ?></label><textarea name="notes" rows="3"><?= e(old('notes')) ?></textarea></div>
    <div class="field field-check col-12"><label><input type="checkbox" name="website" value="1"> <?= e(__('horses.foal_website')) ?></label></div>
  </div>
  <div><button class="btn btn-primary btn-lg" type="submit"><?= e(__('horses.create_foal')) ?></button></div>
</form>
