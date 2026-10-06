<?php
use App\Core\Auth;
use App\Core\DB;

$loc = $h['location_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$h['location_id']]) : null;
$photo = $h['main_photo_id'] ? url('/portal/files/' . $h['main_photo_id'] . '/thumb') : asset('img/horse-placeholder.svg');
$preg = $h['sex'] === 'female' ? DB::row("SELECT expected_foaling_date FROM breeding_records WHERE mare_id = ? AND status = 'pregnant' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1", [$h['id']]) : null;
?>
<section class="card horse-head">
  <img class="horse-photo" src="<?= e($photo) ?>" alt="<?= e($h['name_en']) ?>">
  <div>
    <?php if ($h['name_ar']): ?><p class="muted" style="margin:0 0 6px;font-size:16px" dir="rtl"><?= e($h['name_ar']) ?></p><?php endif; ?>
    <div class="chips">
      <span class="chip"><?= e(__('horse.cat_' . $h['category'])) ?></span>
      <?php if ($h['dob']): ?><span class="chip"><?= e(horse_age($h['dob'])) ?> · <?= e(fmt_date($h['dob'])) ?></span><?php endif; ?>
      <?= status_badge($h['status'], 'horse.status') ?>
      <?php if ($loc): ?><span class="chip"><?= icon('pin') ?> <?= e(loc($loc, 'value')) ?></span><?php endif; ?>
      <?php if ($h['show_on_website']): ?><span class="chip gold"><?= icon('globe') ?> <?= e(__('common.on_website')) ?></span><?php endif; ?>
      <?php if ($h['is_external']): ?><span class="chip"><?= e(__('horses.is_external_short')) ?></span><?php endif; ?>
      <?php if ($preg): ?><span class="chip gold"><?= e(__('breeding.status_pregnant')) ?> · <?= e(__('breeding.expected_foaling')) ?> <?= e(fmt_date($preg['expected_foaling_date'])) ?></span><?php endif; ?>
    </div>
    <?php if ($h['microchip'] || $h['registration_no']): ?><p class="small muted" style="margin:10px 0 0"><?= $h['registration_no'] ? e(__('horses.registration_no')) . ': <b dir="ltr">' . e($h['registration_no']) . '</b> · ' : '' ?><?= $h['microchip'] ? e(__('horses.microchip')) . ': <b dir="ltr">' . e($h['microchip']) . '</b>' : '' ?></p><?php endif; ?>
    <div class="quick-add" style="margin-top:12px">
      <?php if (\App\Services\FileStore::canUpload('horse')): ?>
        <form method="post" action="<?= e(url('/portal/files/upload')) ?>" enctype="multipart/form-data" class="inline">
          <?= csrf_field() ?><input type="hidden" name="owner_type" value="horse"><input type="hidden" name="owner_id" value="<?= (int) $h['id'] ?>"><input type="hidden" name="category" value="photo">
          <label class="btn btn-sm file-btn"><?= icon('camera') ?> <?= e(__('horses.add_photo')) ?><input type="file" name="files[]" accept="image/*" capture="environment" multiple data-auto-submit></label>
        </form>
      <?php endif; ?>
      <?php if (Auth::can('horse_diet', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/diet-logs/create?horse_id=' . $h['id'])) ?>"><?= icon('feed') ?> <?= e(__('horses.log_feeding')) ?></a><?php endif; ?>
      <?php if (Auth::can('horse_health', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/health-records/create?horse_id=' . $h['id'])) ?>"><?= icon('plus') ?> <?= e(__('horses.add_health')) ?></a><?php endif; ?>
      <?php if (Auth::can('horse_notes', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/horse-notes/create?horse_id=' . $h['id'])) ?>"><?= icon('edit') ?> <?= e(__('horses.add_note')) ?></a><?php endif; ?>
      <?php if (Auth::can('horse_training', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/training-logs/create?horse_id=' . $h['id'])) ?>"><?= icon('plus') ?> <?= e(__('horses.add_training')) ?></a><?php endif; ?>
    </div>
  </div>
</section>
