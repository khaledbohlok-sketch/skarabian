<?php use App\Resources\Attendance; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/attendance')) ?>">← <?= e(__('nav.attendance')) ?></a><h1><?= e(__('attendance.daily_sheet')) ?></h1></div>
  <form method="get" class="form-inline"><input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"><button class="btn" type="submit"><?= e(__('common.apply')) ?></button></form></div>
<form method="post" action="<?= e(url('/portal/attendance/sheet?date=' . $date)) ?>">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('employees.singular')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('attendance.check_in')) ?></th><th><?= e(__('attendance.check_out')) ?></th><th><?= e(__('attendance.overtime')) ?></th><th><?= e(__('common.notes')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($employees as $e): $st = $e['status'] ?? 'present'; ?>
      <tr><td data-label="<?= e(__('employees.singular')) ?>"><b><?= e($e['name_en']) ?></b><br><small class="muted"><?= e($e['position']) ?></small></td>
        <td data-label="<?= e(__('common.status')) ?>"><select name="att[<?= (int) $e['id'] ?>][status]"><?php foreach (Attendance::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($st, $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></td>
        <td data-label="<?= e(__('attendance.check_in')) ?>"><input type="time" name="att[<?= (int) $e['id'] ?>][in]" value="<?= e(substr((string) $e['check_in'], 0, 5)) ?>"></td>
        <td data-label="<?= e(__('attendance.check_out')) ?>"><input type="time" name="att[<?= (int) $e['id'] ?>][out]" value="<?= e(substr((string) $e['check_out'], 0, 5)) ?>"></td>
        <td data-label="<?= e(__('attendance.overtime')) ?>"><input type="number" step="0.5" min="0" max="24" name="att[<?= (int) $e['id'] ?>][ot]" value="<?= e((float) ($e['overtime_hours'] ?? 0)) ?>" style="max-width:90px"></td>
        <td data-label="<?= e(__('common.notes')) ?>"><input type="text" name="att[<?= (int) $e['id'] ?>][notes]" maxlength="255" value="<?= e($e['notes']) ?>"></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="form-actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
