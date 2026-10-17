<?php use App\Resources\Attendance;
$past = $date < date('Y-m-d') || ($date === date('Y-m-d') && date('H:i') >= $end);
// "+2:30 after the usual end": a help for typing overtime (the scanner never fills overtime itself)
$after = function (?string $out) use ($end): string {
    if ($out === null || $out === '' || substr($out, 0, 5) <= $end) {
        return '';
    }
    $m = intdiv((int) (strtotime('2000-01-01 ' . substr($out, 0, 5)) - strtotime('2000-01-01 ' . $end)), 60);
    return $m >= 15 ? __('attendance.after_end', ['t' => intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT)]) : '';
};
?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/attendance')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.attendance')) ?></a><h1><?= e(__('attendance.daily_sheet')) ?></h1>
  <?php if ($scans): ?><p class="muted small"><?= e(__('attendance.sheet_legend')) ?></p><?php endif; ?></div>
  <div class="actions">
    <?php if ($canScan): ?><a class="btn" href="<?= e(url('/portal/attendance/scan')) ?>"><?= e(__('attendance.scanner')) ?></a><?php endif; ?>
    <a class="btn" href="<?= e(url('/portal/attendance/check', ['month' => substr($date, 0, 7)])) ?>"><?= e(__('attendance.month_check')) ?></a>
    <?php if ($canSettings): ?><a class="btn" href="<?= e(url('/portal/attendance/settings')) ?>"><?= e(__('attendance.settings')) ?></a><?php endif; ?>
    <form method="get" class="form-inline"><input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"><button class="btn" type="submit"><?= e(__('common.apply')) ?></button></form>
  </div></div>
<form method="post" action="<?= e(url('/portal/attendance/sheet?date=' . $date)) ?>" class="record-form" data-autosave="attendance-sheet:<?= e($date) ?>">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table att-sheet">
    <thead><tr><th><?= e(__('employees.singular')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('attendance.check_in')) ?></th><th><?= e(__('attendance.check_out')) ?></th><th><?= e(__('attendance.overtime')) ?></th><th><?= e(__('common.notes')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($employees as $e):
      $id = (int) $e['id'];
      $st = $e['status'] ?? 'present';
      $in = substr((string) $e['check_in'], 0, 5);
      $out = substr((string) $e['check_out'], 0, 5);
      $ot = (float) ($e['overtime_hours'] ?? 0);
      $src = (string) ($e['source'] ?? 'manual');
      $toCheck = in_array($src, \App\Services\AttendanceScan::TO_CHECK, true);
      $noRecord = $scans && $e['att_id'] === null && $working && $date <= date('Y-m-d');
      $noOut = $scans && $in !== '' && $out === '' && in_array($st, ['present', 'late'], true) && $past;
      $hint = $after($e['check_out']); ?>
      <tr class="<?= $noRecord ? 'att-none' : '' ?><?= $noOut ? ' att-no-out' : '' ?><?= $toCheck ? ' att-check' : '' ?>">
        <td data-label="<?= e(__('employees.singular')) ?>"><div class="att-who"><b><?= e(loc($e)) ?></b><?php if ($src !== 'manual' && $scans): ?> <span class="badge badge-<?= $toCheck ? 'pending' : 'approved' ?> att-src" title="<?= e(__('attendance.src_' . $src)) ?>"><?= e(__('attendance.src_short_' . $src)) ?></span><?php endif; ?>
          <br><small class="muted"><?= e($e['position']) ?><?= $noRecord ? ' · ' . e(__('attendance.no_scan_yet')) : '' ?><?= $noOut ? ' · ' . e(__('attendance.no_check_out')) : '' ?></small></div>
          <input type="hidden" name="att[<?= $id ?>][o_status]" value="<?= e($st) ?>"><input type="hidden" name="att[<?= $id ?>][o_in]" value="<?= e($in) ?>"><input type="hidden" name="att[<?= $id ?>][o_out]" value="<?= e($out) ?>"><input type="hidden" name="att[<?= $id ?>][o_ot]" value="<?= e($ot) ?>"><input type="hidden" name="att[<?= $id ?>][o_notes]" value="<?= e($e['notes']) ?>"></td>
        <td data-label="<?= e(__('common.status')) ?>"><select name="att[<?= $id ?>][status]"><?php foreach (Attendance::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($st, $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></td>
        <td data-label="<?= e(__('attendance.check_in')) ?>"><input type="time" name="att[<?= $id ?>][in]" value="<?= e($in) ?>"></td>
        <td data-label="<?= e(__('attendance.check_out')) ?>"><input type="time" name="att[<?= $id ?>][out]" value="<?= e($out) ?>"></td>
        <td data-label="<?= e(__('attendance.overtime')) ?>"><input type="number" step="0.5" min="0" max="24" name="att[<?= $id ?>][ot]" value="<?= e($ot) ?>" class="ot-input"><?php if ($hint !== ''): ?><small class="muted att-after"><?= e($hint) ?></small><?php endif; ?></td>
        <td data-label="<?= e(__('common.notes')) ?>"><input type="text" name="att[<?= $id ?>][notes]" maxlength="255" value="<?= e($e['notes']) ?>"></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="form-actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
