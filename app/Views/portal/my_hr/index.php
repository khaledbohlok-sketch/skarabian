<?php use App\Core\Session; use App\Resources\LeaveRequests;
$left = max(0, $balance - $pending); $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
$pill = ['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'bad', 'cancelled' => '']; ?>
<div class="page-head"><div><h1><?= e(__('nav.my_hr')) ?></h1><p class="muted"><?= e(loc($e, 'name')) ?><?= $e['position_en'] ? ' · ' . e(lang() === 'ar' && $e['position_ar'] ? $e['position_ar'] : $e['position_en']) : '' ?><?= $e['emp_no'] ? ' · ' . e($e['emp_no']) : '' ?></p></div></div>
<div class="stat-grid">
  <div class="stat"><div class="k"><?= e(__('my_hr.leave_left')) ?></div><div class="v"><?= e($num($left)) ?></div><div class="muted small"><?= e(__('my_hr.leave_of', ['n' => $num($e['annual_leave_days'])])) ?><?= $pending ? ' · ' . e(__('my_hr.pending_days', ['n' => $num($pending)])) : '' ?></div></div>
  <div class="stat"><div class="k"><?= e(__('my_hr.this_month')) ?></div><div class="v"><?= (int) (($att['present'] ?? 0) + ($att['late'] ?? 0)) ?></div><div class="muted small"><?= e(__('my_hr.days_present')) ?><?= ($att['absent'] ?? 0) ? ' · ' . e(__('my_hr.absent_n', ['n' => (int) $att['absent']])) : '' ?></div></div>
  <div class="stat"><div class="k"><?= e(__('my_hr.overtime')) ?></div><div class="v"><?= e($num($ot)) ?></div><div class="muted small"><?= e(__('my_hr.hours_month')) ?></div></div>
  <?php foreach ($docs as $d): ?><div class="stat<?= $d['days'] < 0 ? ' alert-bad' : ($d['days'] <= 60 ? ' alert-warn' : '') ?>"><div class="k"><?= e(__($d['label'])) ?></div><div class="v" style="font-size:18px"><?= e(fmt_date($d['date'])) ?></div>
    <div class="muted small"><?= e($d['days'] < 0 ? __('my_hr.expired') : __('my_hr.in_days', ['n' => $d['days']])) ?></div></div><?php endforeach; ?>
</div>
<div class="grid-2">
  <section class="card" id="leave"><h2><?= e(__('my_hr.request_leave')) ?></h2>
    <form method="post" action="<?= e(url('/portal/my-hr/leave')) ?>"><?= csrf_field() ?>
      <div class="grid-fields">
        <div class="field col-12"><label for="leave_type"><?= e(__('common.type')) ?></label><select id="leave_type" name="leave_type" required>
          <?php foreach (LeaveRequests::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= selected(Session::old('leave_type', 'annual'), $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select><?= field_error('leave_type') ?></div>
        <div class="field col-6"><label for="start_date"><?= e(__('common.from')) ?></label><input type="date" id="start_date" name="start_date" value="<?= e(Session::old('start_date', '')) ?>" required><?= field_error('start_date') ?></div>
        <div class="field col-6"><label for="end_date"><?= e(__('common.to')) ?></label><input type="date" id="end_date" name="end_date" value="<?= e(Session::old('end_date', '')) ?>" required><?= field_error('end_date') ?></div>
        <div class="field col-12"><label for="reason"><?= e(__('leave.reason')) ?></label><input type="text" id="reason" name="reason" maxlength="255" value="<?= e(Session::old('reason', '')) ?>"><?= field_error('reason') ?><?= field_error('days') ?></div>
      </div>
      <p class="muted small"><?= e(__('my_hr.leave_help')) ?></p>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('my_hr.send_request')) ?></button></div>
    </form>
  </section>
  <section class="card"><h2><?= e(__('my_hr.my_leave')) ?></h2>
    <?php if (!$leave): ?><p class="muted"><?= e(__('my_hr.no_leave')) ?></p><?php else: ?>
    <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.type')) ?></th><th><?= e(__('common.from')) ?></th><th><?= e(__('common.to')) ?></th><th><?= e(__('leave.days')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead><tbody>
    <?php foreach ($leave as $l): ?><tr><td><?= e(__(LeaveRequests::TYPES[$l['leave_type']])) ?></td><td class="nowrap"><?= e(fmt_date($l['start_date'])) ?></td><td class="nowrap"><?= e(fmt_date($l['end_date'])) ?></td><td><?= e($num($l['days'])) ?></td>
      <td><span class="pill <?= $pill[$l['status']] ?>"><?= e(__('my_hr.st_' . $l['status'])) ?></span></td>
      <td><?php if ($l['status'] === 'pending'): ?><form method="post" action="<?= e(url('/portal/my-hr/leave/' . $l['id'] . '/cancel')) ?>" class="inline" data-confirm="<?= e(__('my_hr.confirm_cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('common.cancel')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
</div>
<section class="card" id="payslips"><h2><?= e(__('my_hr.payslips')) ?></h2>
  <?php if (!$payslips): ?><p class="muted"><?= e(__('my_hr.no_payslips')) ?></p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th><?= e(__('my_hr.month')) ?></th><th class="num"><?= e(__('my_hr.basic')) ?></th><th class="num"><?= e(__('payroll.allowances')) ?></th><th class="num"><?= e(__('payroll.overtime')) ?></th><th class="num"><?= e(__('payroll.deductions')) ?></th><th class="num"><?= e(__('payroll.net')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($payslips as $p): ?><tr><td class="nowrap"><strong><?= e(date('M Y', strtotime($p['period'] . '-01'))) ?></strong></td>
    <td class="num"><?= money($p['basic_qar']) ?></td><td class="num"><?= money($p['allowances_qar']) ?></td><td class="num"><?= money($p['overtime_qar']) ?></td><td class="num"><?= money((float) $p['deductions_qar'] + (float) $p['advances_qar']) ?></td><td class="num"><strong><?= money($p['net_qar']) ?></strong></td>
    <td><span class="pill <?= $p['paid_at'] ? 'ok' : 'warn' ?>"><?= e(__($p['paid_at'] ? 'my_hr.paid_on' : 'my_hr.not_paid_yet', ['date' => fmt_date($p['paid_at'])])) ?></span></td>
    <td><?php if ($p['doc_id']): ?><a class="btn btn-sm" href="<?= e(url('/portal/studio/' . $p['doc_id'] . '/print')) ?>" target="_blank" rel="noopener"><?= icon('doc') ?> <?= e(__('my_hr.payslip')) ?></a><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<div class="grid-2">
  <section class="card"><h2><?= e(__('my_hr.letters')) ?></h2>
    <?php if (!$letters): ?><p class="muted"><?= e(__('my_hr.no_letters')) ?></p><?php else: ?><ul class="alert-list">
    <?php foreach ($letters as $d): ?><li><a href="<?= e(url('/portal/studio/' . $d['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($d['title']) ?></a><span class="muted small"><?= e($d['ref_no']) ?> · <?= e(fmt_date($d['doc_date'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <p class="muted small"><?= e(__('my_hr.letters_help')) ?></p>
  </section>
  <?php if ($loans): ?><section class="card"><h2><?= e(__('my_hr.loans')) ?></h2>
    <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.type')) ?></th><th><?= e(__('common.date')) ?></th><th class="num"><?= e(__('common.amount')) ?></th><th class="num"><?= e(__('my_hr.monthly')) ?></th><th class="num"><?= e(__('my_hr.remaining')) ?></th></tr></thead><tbody>
    <?php foreach ($loans as $l): ?><tr><td><?= e(__('loans.type_' . $l['type'])) ?></td><td class="nowrap"><?= e(fmt_date($l['issue_date'])) ?></td><td class="num"><?= money($l['amount_qar']) ?></td><td class="num"><?= money($l['monthly_deduction']) ?></td><td class="num"><strong><?= money($l['balance_qar']) ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div></section><?php endif; ?>
</div>
