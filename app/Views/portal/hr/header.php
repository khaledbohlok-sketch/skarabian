<?php
use App\Core\Auth;
use App\Core\DB;
$pos = $emp['position_id'] ? DB::row('SELECT value_en, value_ar FROM lookups WHERE id = ?', [$emp['position_id']]) : null;
$user = DB::row('SELECT id, username, status FROM users WHERE employee_id = ? AND deleted_at IS NULL', [$emp['id']]);
$docs = ['qid_expiry' => 'hr.qid', 'passport_expiry' => 'hr.passport', 'visa_expiry' => 'hr.visa', 'health_card_expiry' => 'hr.health_card'];
?>
<section class="card horse-head" style="grid-template-columns:120px 1fr">
  <img class="horse-photo" style="aspect-ratio:1;border-radius:50%" src="<?= e($emp['photo_id'] ? url('/portal/files/' . $emp['photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt="">
  <div>
    <?php if ($emp['name_ar']): ?><p class="muted" style="margin:0 0 6px;font-size:16px" dir="rtl"><?= e($emp['name_ar']) ?></p><?php endif; ?>
    <div class="chips">
      <?php if ($pos): ?><span class="chip"><?= e(loc($pos, 'value')) ?></span><?php endif; ?>
      <?php if ($emp['emp_no']): ?><span class="chip" dir="ltr"><?= e($emp['emp_no']) ?></span><?php endif; ?>
      <?= status_badge($emp['status']) ?>
      <?php if ($user): ?><span class="chip"><?= icon('shield') ?> <?= e($user['username']) ?> · <?= e(__('status.' . $user['status'])) ?></span><?php endif; ?>
    </div>
    <div class="chips" style="margin-top:10px">
      <?php foreach ($docs as $col => $label): if ($emp[$col]): $cls = expiry_class($emp[$col]); ?>
        <span class="chip <?= $cls === 'exp-expired' ? 'exp-expired' : ($cls === 'exp-soon' ? 'exp-soon' : '') ?>" style="<?= $cls === 'exp-expired' ? 'background:var(--bad-bg)' : ($cls === 'exp-soon' ? 'background:var(--warn-bg)' : '') ?>"><?= e(__($label)) ?>: <?= e(fmt_date($emp[$col])) ?><?= $cls === 'exp-expired' ? ' · ' . e(__('dashboard.expired')) : '' ?></span>
      <?php endif; endforeach; ?>
    </div>
    <?php if (\App\Services\FileStore::canUpload('employee')): ?>
    <form method="post" action="<?= e(url('/portal/files/upload')) ?>" enctype="multipart/form-data" class="mt"><?= csrf_field() ?><input type="hidden" name="owner_type" value="employee"><input type="hidden" name="owner_id" value="<?= (int) $emp['id'] ?>"><input type="hidden" name="category" value="photo">
      <label class="btn btn-sm file-btn"><?= icon('camera') ?> <?= e(__('horses.add_photo')) ?><input type="file" name="files[]" accept="image/*" data-auto-submit></label></form>
    <?php endif; ?>
  </div>
</section>
