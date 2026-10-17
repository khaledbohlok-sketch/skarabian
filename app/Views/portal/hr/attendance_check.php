<?php
$day = function (string $d) use ($canSheet): string {
    $ts = strtotime($d);
    $label = date('j', $ts) . ' ' . (is_rtl() ? ar_month((int) date('n', $ts)) : date('M', $ts));
    return $canSheet ? '<a href="' . e(url('/portal/attendance/sheet', ['date' => $d])) . '">' . e($label) . '</a>' : e($label);
};
$days = function (array $list) use ($day): string {
    if (!$list) {
        return '<span class="muted">—</span>';
    }
    $shown = array_slice($list, 0, 8);
    // one wrapper: on phones a table cell lays its children out side by side
    return '<span class="att-days"><b>' . count($list) . '</b> · ' . implode(is_rtl() ? '، ' : ', ', array_map($day, $shown))
        . (count($list) > 8 ? ' ' . e(__('attendance.and_more', ['n' => count($list) - 8])) : '') . '</span>';
};
?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/attendance')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.attendance')) ?></a>
  <h1><?= e(__('attendance.month_check')) ?> — <?= e(fmt_month($month . '-01')) ?></h1>
  <p class="muted"><?= e(__('attendance.month_check_intro')) ?></p></div>
  <form method="get" class="form-inline"><input type="month" name="month" value="<?= e($month) ?>" max="<?= e(date('Y-m')) ?>" aria-label="<?= e(__('common.month')) ?>"><button class="btn" type="submit"><?= e(__('common.apply')) ?></button></form>
</div>
<?php if (!$rows): ?>
<div class="card"><p><?= e(__('attendance.month_check_none')) ?></p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('employees.singular')) ?></th><th><?= e(__('attendance.chk_no_record')) ?></th><th><?= e(__('attendance.chk_no_check_out')) ?></th>
    <th><?= e(__('attendance.chk_late')) ?></th><th><?= e(__('attendance.chk_absent_on_leave')) ?></th><?php if ($scans): ?><th><?= e(__('attendance.chk_to_check')) ?></th><?php endif; ?><th class="num"><?= e(__('attendance.overtime')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $emp = $r['employee']; ?>
    <tr>
      <td data-label="<?= e(__('employees.singular')) ?>"><span class="att-days"><a href="<?= e(url('/portal/employees/' . $emp['id'])) ?>"><b><?= e(loc($emp)) ?></b></a><?php if ($emp['emp_no']): ?><br><small class="muted"><?= e($emp['emp_no']) ?></small><?php endif; ?></span></td>
      <td data-label="<?= e(__('attendance.chk_no_record')) ?>"><?= $days($r['no_record']) ?></td>
      <td data-label="<?= e(__('attendance.chk_no_check_out')) ?>"><?= $days($r['no_check_out']) ?></td>
      <td data-label="<?= e(__('attendance.chk_late')) ?>"><?= $days($r['late']) ?></td>
      <td data-label="<?= e(__('attendance.chk_absent_on_leave')) ?>"><?= $days($r['absent_on_leave']) ?></td>
      <?php if ($scans): ?><td data-label="<?= e(__('attendance.chk_to_check')) ?>"><?= $days($r['to_check']) ?></td><?php endif; ?>
      <td class="num" data-label="<?= e(__('attendance.overtime')) ?>"><span class="att-days"><?= e(__('attendance.hours_n', ['n' => num($r['overtime'], 1)])) ?><?php if ($r['overtime_up']): ?> <span class="badge badge-pending" title="<?= e(__('attendance.overtime_up_help')) ?>">↑</span><?php endif; ?><br><small class="muted"><?= e(__('attendance.overtime_before', ['n' => num($r['overtime_before'], 1)])) ?></small></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
