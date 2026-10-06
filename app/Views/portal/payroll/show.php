<?php
use App\Core\Auth;
use App\Core\DB;
$draft = $run['status'] === 'draft';
$editable = $draft && $canEdit && $sens;
$payable = in_array($run['status'], ['approved', 'paid'], true) && $canEdit && (Auth::can('finance', 'create') || Auth::isOwner());
$unpaid = array_filter($lines, fn ($l) => !$l['paid_at'] && $l['bill_id']);
$sum = fn (string $k) => array_sum(array_map(fn ($l) => (float) $l[$k], $lines));
$numIn = fn ($l, $k) => '<input type="text" inputmode="decimal" dir="ltr" class="num-input" name="lines[' . (int) $l['id'] . '][' . $k . ']" value="' . e(number_format((float) $l[$k], 2, '.', '')) . '">';
?>
<div class="page-head">
  <div><a class="crumb" href="<?= e(url('/portal/payroll')) ?>">← <?= e(__('nav.payroll')) ?></a><h1><?= e($title) ?> <?= status_badge($run['status'], 'payroll.status') ?></h1>
    <p class="muted"><?= e(__('payroll.rules')) ?></p></div>
  <div class="actions">
    <?php if ($canDecide): ?>
      <form method="post" action="<?= e(url('/portal/approvals/' . $approval['id'] . '/approve')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('common.approve')) ?></button></form>
      <form method="post" action="<?= e(url('/portal/approvals/' . $approval['id'] . '/reject')) ?>" class="inline" data-confirm="<?= e(__('approvals.confirm_reject')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= e(__('common.reject')) ?></button></form>
    <?php endif; ?>
    <?php if ($draft && $canEdit && $lines): ?>
      <form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . '/submit')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('payroll.submit')) ?></button></form>
    <?php endif; ?>
    <?php if (in_array($run['status'], ['draft', 'pending'], true) && Auth::can('payroll', 'delete')): ?>
      <form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(__('payroll.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?> <?= e(__('common.delete')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($sens): ?>
<div class="stat-grid">
  <div class="stat dark"><div class="k"><?= e(__('payroll.total_net')) ?></div><div class="v"><?= money($run['total_net_qar']) ?></div><div class="s"><?= count($lines) ?> <?= e(__('nav.employees')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.gross')) ?></div><div class="v"><?= money($sum('basic_qar') + $sum('allowances_qar') + $sum('overtime_qar')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.deductions_all')) ?></div><div class="v"><?= money($sum('deductions_qar') + $sum('advances_qar')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.paid_count')) ?></div><div class="v"><?= count($lines) - count(array_filter($lines, fn ($l) => !$l['paid_at'])) ?> / <?= count($lines) ?></div></div>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . ($editable ? '/lines' : '/pay'))) ?>" id="payroll-form">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table payroll-table">
    <thead><tr>
      <?php if ($payable && $unpaid): ?><th><input type="checkbox" data-check-all="line_ids[]" aria-label="<?= e(__('common.select_all')) ?>"></th><?php endif; ?>
      <th><?= e(__('employees.singular')) ?></th>
      <?php if ($sens): ?><th class="num"><?= e(__('employees.basic')) ?> <small>(QAR)</small></th><th class="num"><?= e(__('payroll.allowances')) ?></th><th class="num"><?= e(__('payroll.overtime')) ?></th><th class="num"><?= e(__('payroll.deductions')) ?></th><th class="num"><?= e(__('payroll.advances')) ?></th><th class="num"><?= e(__('payroll.net')) ?></th><?php endif; ?>
      <th><?= e(__('common.notes')) ?></th><th><?= e(__('common.status')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($lines as $l): ?><tr>
      <?php if ($payable && $unpaid): ?><td><?php if (!$l['paid_at'] && $l['bill_id']): ?><input type="checkbox" name="line_ids[]" value="<?= (int) $l['id'] ?>" aria-label="<?= e($l['name_en']) ?>"><?php endif; ?></td><?php endif; ?>
      <td data-label="<?= e(__('employees.singular')) ?>"><a href="<?= e(url('/portal/employees/' . $l['employee_id'] . '?tab=payroll')) ?>"><b><?= e($l['name_en']) ?></b></a><br><small class="muted"><?= e(trim(($l['emp_no'] ?? '') . ' · ' . ($l['position'] ?? ''), ' ·')) ?></small></td>
      <?php if ($sens): ?>
        <td class="num" data-label="<?= e(__('employees.basic')) ?>"><?= e(number_format((float) $l['basic_qar'], 2)) ?></td>
        <td class="num" data-label="<?= e(__('payroll.allowances')) ?>"><?= e(number_format((float) $l['allowances_qar'], 2)) ?></td>
        <td class="num" data-label="<?= e(__('payroll.overtime')) ?>"><?= $editable ? $numIn($l, 'overtime_qar') : e(number_format((float) $l['overtime_qar'], 2)) ?></td>
        <td class="num" data-label="<?= e(__('payroll.deductions')) ?>"><?= $editable ? $numIn($l, 'deductions_qar') : e(number_format((float) $l['deductions_qar'], 2)) ?></td>
        <td class="num" data-label="<?= e(__('payroll.advances')) ?>"><?= $editable ? $numIn($l, 'advances_qar') : e(number_format((float) $l['advances_qar'], 2)) ?></td>
        <td class="num" data-label="<?= e(__('payroll.net')) ?>"><b><?= e(number_format((float) $l['net_qar'], 2)) ?></b></td>
      <?php endif; ?>
      <td data-label="<?= e(__('common.notes')) ?>"><?php if ($editable): ?><input type="text" maxlength="255" name="lines[<?= (int) $l['id'] ?>][notes]" value="<?= e($l['notes'] ?? '') ?>"><?php else: ?><small><?= e($l['notes'] ?? '') ?></small><?php endif; ?></td>
      <td data-label="<?= e(__('common.status')) ?>">
        <?php if ($l['paid_at']): ?><span class="badge badge-ok"><?= e(__('payroll.paid_on', ['date' => fmt_date(substr($l['paid_at'], 0, 10))])) ?></span>
        <?php elseif ($l['bill_id']): ?><span class="badge badge-warn"><?= e(__('payroll.unpaid')) ?></span><?php endif; ?>
        <?php if ($l['bill_id'] && Auth::can('finance')): ?> <a class="btn btn-xs" href="<?= e(url('/portal/bills/' . $l['bill_id'])) ?>"><?= e(__('bills.singular')) ?></a><?php endif; ?>
        <?php if ($l['payslip_document_id']): ?> <a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $l['payslip_document_id'])) ?>"><?= e(__('payroll.payslip')) ?></a><?php endif; ?>
      </td>
    </tr><?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($editable): ?>
    <div class="form-actions sticky-actions">
      <button class="btn btn-primary" type="submit" name="action" value="save"><?= e(__('common.save')) ?></button>
      <button class="btn" type="submit" name="action" value="recalc" data-confirm-click="<?= e(__('payroll.confirm_recalc')) ?>"><?= e(__('payroll.recalc')) ?></button>
    </div>
  <?php elseif ($payable && $unpaid): ?>
    <section class="card pay-box">
      <h2><?= e(__('payroll.pay_title')) ?></h2>
      <div class="grid-fields">
        <div class="field col-4"><label for="f_account_id"><?= e(__('bills.account')) ?> <span class="req">*</span></label>
          <div class="picker-row"><select id="f_account_id" name="account_id" data-picker="accounts" required><option value=""><?= e(__('common.search_choose')) ?></option></select></div></div>
        <div class="field col-4"><label for="f_method"><?= e(__('bills.method')) ?></label>
          <select id="f_method" name="payment_method_id"><option value=""><?= e(__('common.choose')) ?></option>
            <?php foreach (DB::all("SELECT id, value_en, value_ar FROM lookups WHERE type = 'payment_method' AND active = 1 ORDER BY sort") as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e(loc($m, 'value')) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field col-4"><label for="f_date"><?= e(__('common.date')) ?></label><input id="f_date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit" name="scope" value="all" data-confirm-click="<?= e(__('payroll.confirm_pay_all', ['n' => count($unpaid)])) ?>"><?= e(__('payroll.pay_all', ['n' => count($unpaid)])) ?></button>
        <button class="btn" type="submit" name="scope" value="selected"><?= e(__('payroll.pay_selected')) ?></button>
      </div>
    </section>
  <?php endif; ?>
</form>
<?php if ($run['status'] === 'pending'): ?><div class="alert alert-info"><?= e(__('payroll.pending_note')) ?></div><?php endif; ?>
