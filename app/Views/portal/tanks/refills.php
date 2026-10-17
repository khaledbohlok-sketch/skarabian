<?php
use App\Core\Auth;
use App\Core\DB;
$tank = $tank ?? $r;
$refills = DB::all('SELECT f.*, u.name AS by_name FROM tank_refills f LEFT JOIN users u ON u.id = f.created_by WHERE f.tank_id = ? ORDER BY f.refill_date DESC', [(int) $tank['id']]);
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('tanks.refill_log')) ?></h2></div>
  <?php if (Auth::can('semen_stock', 'edit')): ?>
  <form method="post" action="<?= e(url('/portal/tanks/' . $tank['id'] . '/refill')) ?>" class="upload-form">
    <?= csrf_field() ?>
    <input type="date" name="refill_date" value="<?= e(date('Y-m-d')) ?>" required>
    <input type="text" name="notes" placeholder="<?= e(__('common.notes')) ?>" maxlength="255">
    <button class="btn btn-sm btn-primary" type="submit"><?= e(__('tanks.log_refill')) ?></button>
  </form>
  <?php endif; ?>
  <?php if ($refills): ?>
  <ul class="alert-list">
    <?php foreach ($refills as $f): ?><li><span><?= e(fmt_date($f['refill_date'])) ?><?= $f['notes'] ? ' — ' . e($f['notes']) : '' ?></span><span class="muted small"><?= e($f['by_name'] ?: '') ?></span></li><?php endforeach; ?>
  </ul>
  <?php else: ?><p class="muted"><?= e(__('tanks.no_refills')) ?></p><?php endif; ?>
</section>
