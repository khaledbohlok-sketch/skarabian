<?php
use App\Core\Auth;
use App\Core\DB;

if (!Auth::can('hr') && !Auth::can('horses', 'edit')) { return; }
$staff = DB::all('SELECT e.id, e.name_en, a.role, p.value_en AS position FROM horse_assignments a JOIN employees e ON e.id = a.employee_id LEFT JOIN lookups p ON p.id = e.position_id WHERE a.horse_id = ? ORDER BY e.name_en', [$h['id']]);
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('horses.assigned_staff')) ?></h2></div>
  <?php if ($staff): ?><ul class="alert-list">
    <?php foreach ($staff as $s): ?><li><span><?= Auth::can('hr') ? '<a href="' . e(url('/portal/employees/' . $s['id'])) . '">' . e($s['name_en']) . '</a>' : e($s['name_en']) ?> <small class="muted">· <?= e($s['position']) ?></small></span>
      <?php if (Auth::can('horses', 'edit')): ?><form method="post" action="<?= e(url('/portal/horses/' . $h['id'] . '/unassign/' . $s['id'])) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-danger" type="submit">✕</button></form><?php endif; ?></li>
    <?php endforeach; ?></ul>
  <?php else: ?><p class="muted"><?= e(__('horses.no_staff')) ?></p><?php endif; ?>
  <?php if (Auth::can('horses', 'edit')): ?>
  <form method="post" action="<?= e(url('/portal/horses/' . $h['id'] . '/assign')) ?>" class="form-inline mt"><?= csrf_field() ?>
    <div style="flex:1;min-width:220px"><select name="employee_id" data-picker="employees" required><option value=""><?= e(__('common.search_choose')) ?></option></select></div>
    <button class="btn" type="submit"><?= e(__('horses.assign')) ?></button>
  </form>
  <?php endif; ?>
</section>
