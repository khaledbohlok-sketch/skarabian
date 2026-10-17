<?php
use App\Core\DB;
// Under the page head (photo, names, status badge): position, number, login and the papers' expiry dates
$pos = $emp['position_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$emp['position_id']]) : null;
$user = DB::row('SELECT id, username, status FROM users WHERE employee_id = ? AND deleted_at IS NULL', [$emp['id']]);
$docs = ['qid_expiry' => 'hr.qid', 'passport_expiry' => 'hr.passport', 'visa_expiry' => 'hr.visa', 'health_card_expiry' => 'hr.health_card'];
$docChips = '';
foreach ($docs as $col => $label) {
    if ($emp[$col]) {
        $cls = expiry_class($emp[$col]);
        $docChips .= '<span class="chip' . (in_array($cls, ['exp-expired', 'exp-soon'], true) ? ' ' . $cls : '') . '">' . e(__($label)) . ': ' . e(fmt_date($emp[$col])) . ($cls === 'exp-expired' ? ' · ' . e(__('dashboard.expired')) : '') . '</span>';
    }
}
if (!$pos && !$emp['emp_no'] && !$user && $docChips === '' && !\App\Services\FileStore::canUpload('employee')) {
    return;
}
?>
<section class="card horse-facts">
  <div class="chips">
    <?php if ($pos): ?><span class="chip"><?= e(loc($pos, 'value')) ?></span><?php endif; ?>
    <?php if ($emp['emp_no']): ?><span class="chip" dir="ltr"><?= e($emp['emp_no']) ?></span><?php endif; ?>
    <?php if ($user): ?><span class="chip"><?= icon('shield', 'i-16') ?> <span dir="ltr"><?= e($user['username']) ?></span> · <?= e(__('status.' . $user['status'])) ?></span><?php endif; ?>
    <?= $docChips ?>
  </div>
  <?php if (\App\Services\FileStore::canUpload('employee')): ?>
  <div class="quick-add">
    <form method="post" action="<?= e(url('/portal/files/upload')) ?>" enctype="multipart/form-data" class="inline"><?= csrf_field() ?><input type="hidden" name="owner_type" value="employee"><input type="hidden" name="owner_id" value="<?= (int) $emp['id'] ?>"><input type="hidden" name="category" value="photo">
      <label class="btn btn-sm file-btn"><?= icon('camera') ?> <?= e(__('horses.add_photo')) ?><input type="file" name="files[]" accept="image/*" data-auto-submit></label></form>
  </div>
  <?php endif; ?>
</section>
