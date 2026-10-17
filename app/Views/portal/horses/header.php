<?php
use App\Core\Auth;
use App\Core\DB;

// Under the page head (photo, names, status badge, favourite star): what the horse is, where it is, its numbers and the quick actions
$loc = $h['location_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$h['location_id']]) : null;
$preg = $h['sex'] === 'female' ? DB::row("SELECT expected_foaling_date FROM breeding_records WHERE mare_id = ? AND status = 'pregnant' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1", [$h['id']]) : null;
$ids = array_filter(['horses.registration_no' => $h['registration_no'], 'horses.microchip' => $h['microchip'], 'horses.ueln' => $h['ueln'] ?? null]);
?>
<section class="card horse-facts">
  <div class="chips">
    <span class="chip"><?= e(__('horse.cat_' . $h['category'])) ?></span>
    <?php if ($h['dob']): ?><span class="chip"><?= icon('calendar', 'i-16') ?> <?= e(horse_age($h['dob'])) ?> · <?= e(fmt_date($h['dob'])) ?></span><?php endif; ?>
    <?php if ($loc): ?><span class="chip"><?= icon('pin', 'i-16') ?> <?= e(loc($loc, 'value')) ?></span><?php endif; ?>
    <?php if ($h['show_on_website']): ?><span class="chip gold"><?= icon('globe', 'i-16') ?> <?= e(__('common.on_website')) ?></span><?php endif; ?>
    <?php if ($h['is_external']): ?><span class="chip"><?= e(__('horses.is_external_short')) ?></span><?php endif; ?>
    <?php if ($preg): ?><span class="chip gold"><?= e(__('breeding.status_pregnant')) ?> · <?= e(__('breeding.expected_foaling')) ?> <?= e(fmt_date($preg['expected_foaling_date'])) ?></span><?php endif; ?>
  </div>
  <?php if ($ids): ?><p class="small muted horse-ids"><?= implode(' · ', array_map(fn ($k, $v) => e(__($k)) . ': <b dir="ltr">' . e($v) . '</b>', array_keys($ids), $ids)) ?></p><?php endif; ?>
  <div class="quick-add">
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
</section>
<script src="<?= e(asset('js/documents.js')) ?>" defer></script>
