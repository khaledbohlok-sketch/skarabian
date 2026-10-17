<?php
use App\Core\Auth;
use App\Core\DB;
use App\Services\PayrollService;
$methodsOn ??= false; $methods ??= []; $groups ??= []; $quickPay ??= false; $payAccount ??= null;
$draft = $run['status'] === 'draft';
$started = \App\Services\PayrollService::started($run['period']); // a later month stays a draft until it starts
$editable = $draft && $canEdit && $sens;
$payable = $started && in_array($run['status'], ['approved', 'paid'], true) && $canEdit && (Auth::can('finance', 'create') || Auth::isOwner());
$unpaid = array_filter($lines, fn ($l) => !$l['paid_at'] && (float) $l['net_qar'] > 0); // a salary's bill is made when it is paid
$paidLines = array_filter($lines, fn ($l) => $l['paid_at']);
// deleting a run with paid salaries also removes those payments: typed confirmation (the month), not a plain "OK"
$deleteConfirm = $paidLines
    ? ' data-confirm-type="' . e($run['period']) . '" data-confirm-type-message="' . e(__('payroll.confirm_delete_paid', ['n' => count($paidLines),
        'amount' => money(array_sum(array_map(fn ($l) => (float) $l['net_qar'], $paidLines)), 'QAR', false), 'period' => $run['period']])) . '"'
    : ' data-confirm="' . e(delete_confirm(in_array($run['status'], ['draft', 'pending'], true) ? 'payroll.confirm_delete' : 'payroll.confirm_delete_approved')) . '"';
// who can be ticked as paid: the unpaid salaries of an approved run, or — the Owner paying straight from a draft or a run
// waiting for approval — every salary above zero (paying approves the run first)
$quick = $quickPay && $canEdit;
$toPay = $quick ? array_filter($lines, fn ($l) => !$l['paid_at'] && (float) $l['net_qar'] > 0) : ($payable ? $unpaid : []);
$ticks = (bool) $toPay || array_filter($lines, fn ($l) => $l['paid_at']);
$sum = fn (string $k) => array_sum(array_map(fn ($l) => (float) $l[$k], $lines));
$numIn = fn ($l, $k) => '<input type="text" inputmode="decimal" dir="ltr" class="num-input" name="lines[' . (int) $l['id'] . '][' . $k . ']" value="' . e(number_format((float) $l[$k], 2, '.', '')) . '" aria-label="' . e(__('payroll.' . ['overtime_qar' => 'overtime', 'deductions_qar' => 'deductions', 'advances_qar' => 'advances'][$k]) . ': ' . loc($l)) . '">';
$payUrl = url('/portal/payroll/' . $run['id'] . '/pay');
?>
<div class="page-head">
  <div><a class="crumb" href="<?= e(url('/portal/payroll')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.payroll')) ?></a><h1><?= e($title) ?> <?= status_badge($run['status'], 'payroll.status') ?></h1>
    <p class="muted"><?= e(__('payroll.rules')) ?></p></div>
  <div class="actions">
    <?php if ($draft && \App\Core\Auth::can('hr')): ?><a class="btn" href="<?= e(url('/portal/attendance/check', ['month' => $run['period']])) ?>"><?= e(__('attendance.month_check')) ?></a><?php endif; ?>
    <?php if ($canDecide): ?>
      <?php if ($started): ?><form method="post" action="<?= e(url('/portal/approvals/' . $approval['id'] . '/approve')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('common.approve')) ?></button></form><?php endif; ?>
      <form method="post" action="<?= e(url('/portal/approvals/' . $approval['id'] . '/reject')) ?>" class="inline" data-confirm="<?= e(__('approvals.confirm_reject')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= e(__('common.reject')) ?></button></form>
    <?php endif; ?>
    <?php if ($draft && $canEdit && $lines && $started): ?>
      <form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . '/submit')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('payroll.submit')) ?></button></form>
    <?php endif; ?>
    <?php if (in_array($run['status'], ['approved', 'paid'], true) && $sens && \App\Services\Studio::canCreate('pay')): ?>
      <a class="btn" href="<?= e(url('/portal/studio/new/pay?run_id=' . $run['id'])) ?>"><?= icon('print') ?> <?= e(__('payroll.print_all')) ?></a>
    <?php endif; ?>
    <?php if (Auth::can('payroll', 'delete')): // paid salaries too: their payments are removed with the run ?>
      <form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . '/delete')) ?>" class="inline"<?= $deleteConfirm ?>><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?> <?= e(__('common.delete')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<?php if (!$started): ?><div class="alert alert-info"><?= e(__('payroll.draft_until', ['date' => fmt_date($run['period'] . '-01')])) ?></div><?php endif; ?>
<?php if ($sens): ?>
<div class="stat-grid">
  <div class="stat dark"><div class="k"><?= e(__('payroll.total_net')) ?></div><div class="v"><?= money($run['total_net_qar']) ?></div><div class="s"><?= e(plural(count($lines), 'count.employee')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.gross')) ?></div><div class="v"><?= money($sum('basic_qar') + $sum('allowances_qar') + $sum('overtime_qar')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.deductions_all')) ?></div><div class="v"><?= money($sum('deductions_qar') + $sum('advances_qar')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('payroll.paid_count')) ?></div><div class="v"><?= count($lines) - count(array_filter($lines, fn ($l) => !$l['paid_at'])) ?> / <?= count($lines) ?></div></div>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/portal/payroll/' . $run['id'] . ($editable ? '/lines' : '/pay'))) ?>" id="payroll-form" class="record-form"<?= $editable ? ' data-autosave="payroll:' . (int) $run['id'] . '"' : '' ?>>
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table payroll-table">
    <thead><tr>
      <?php if ($ticks): ?><th class="tick"><?php if ($toPay): ?><input type="checkbox" data-check-all="line_ids[]" aria-label="<?= e(__('payroll.tick_all')) ?>" title="<?= e(__('payroll.tick_all')) ?>"><?php endif; ?></th><?php endif; ?>
      <th><?= e(__('employees.singular')) ?></th>
      <?php if ($methodsOn): ?><th><?= e(__('payroll.paid_by')) ?></th><?php endif; ?>
      <?php if ($sens): ?><th class="num"><?= e(__('employees.basic')) ?></th><th class="num"><?= e(__('payroll.allowances')) ?></th><th class="num"><?= e(__('payroll.overtime')) ?></th><th class="num"><?= e(__('payroll.deductions')) ?></th><th class="num"><?= e(__('payroll.advances')) ?></th><th class="num"><?= e(__('payroll.net')) ?></th><?php endif; ?>
      <?php if (!$draft): ?><th><?= e(__('common.status')) ?></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($lines as $i => $l): ?><tr<?= $l['paid_at'] ? ' class="is-paid"' : '' ?>>
      <?php if ($ticks): ?><td class="tick" data-label="<?= e(__('payroll.paid_count')) ?>"><?php if ($l['paid_at']): ?><span class="paid-tick" title="<?= e(__('payroll.paid_on', ['date' => fmt_date(substr($l['paid_at'], 0, 10))])) ?>"><?= icon('check') ?><span class="sr-only"><?= e(__('payroll.paid_on', ['date' => fmt_date(substr($l['paid_at'], 0, 10))])) ?></span></span>
        <?php elseif (isset($toPay[$i])): ?><input type="checkbox" name="line_ids[]" value="<?= (int) $l['id'] ?>" aria-label="<?= e(__('payroll.tick_one', ['name' => loc($l)])) ?>"><?php endif; ?></td><?php endif; ?>
      <td class="emp" data-label="<?= e(__('employees.singular')) ?>"><div class="emp-row"><div class="emp-name"><a href="<?= e(url('/portal/employees/' . $l['employee_id'] . '?tab=payroll')) ?>"><b><?= e(loc($l)) ?></b></a><br><small class="muted"><?= e(trim(($l['emp_no'] ?? '') . ' · ' . ($l['position'] ?? ''), ' ·')) ?></small></div>
        <?php if ($editable): ?><input type="text" class="line-notes" maxlength="255" name="lines[<?= (int) $l['id'] ?>][notes]" value="<?= e($l['notes'] ?? '') ?>" placeholder="<?= e(__('common.notes')) ?>" aria-label="<?= e(__('common.notes') . ': ' . loc($l)) ?>">
        <?php elseif (!empty($l['notes'])): ?><small class="line-note"><?= e($l['notes']) ?></small><?php endif; ?></div></td>
      <?php if ($methodsOn): $way = (int) ($l['payment_method_id'] ?? 0) ?: ($editable ? (int) ($l['salary_method_id'] ?? 0) : 0); ?>
        <td data-label="<?= e(__('payroll.paid_by')) ?>"><?php if ($editable): ?>
          <select name="lines[<?= (int) $l['id'] ?>][payment_method_id]" aria-label="<?= e(__('payroll.paid_by') . ': ' . loc($l)) ?>"><option value=""><?= e(__('payroll.method_not_set')) ?></option>
            <?php foreach ($methods as $mid => $m): ?><option value="<?= (int) $mid ?>"<?= $mid === $way ? ' selected' : '' ?>><?= e(loc($m, 'value')) ?></option><?php endforeach; ?>
            <?php if ($way && !isset($methods[$way])): ?><option value="<?= $way ?>" selected><?= e(PayrollService::methodLabel($way)) ?></option><?php endif; ?>
          </select>
        <?php else: ?><?= e(PayrollService::methodLabel($l['paid_at'] && !empty($l['paid_method_id']) ? (int) $l['paid_method_id'] : ($way ?: null))) ?><?php endif; ?></td>
      <?php endif; ?>
      <?php if ($sens): ?>
        <td class="num" data-label="<?= e(__('employees.basic')) ?>"><?= money($l['basic_qar']) ?></td>
        <td class="num" data-label="<?= e(__('payroll.allowances')) ?>"><?= money($l['allowances_qar']) ?></td>
        <td class="num" data-label="<?= e(__('payroll.overtime')) ?>"><?= $editable ? $numIn($l, 'overtime_qar') : money($l['overtime_qar']) ?></td>
        <td class="num" data-label="<?= e(__('payroll.deductions')) ?>"><?= $editable ? $numIn($l, 'deductions_qar') : money($l['deductions_qar']) ?></td>
        <td class="num" data-label="<?= e(__('payroll.advances')) ?>"><?= $editable ? $numIn($l, 'advances_qar') : money($l['advances_qar']) ?></td>
        <td class="num" data-label="<?= e(__('payroll.net')) ?>"><b><?= money($l['net_qar']) ?></b></td>
      <?php endif; ?>
      <?php if (!$draft): ?>
      <td class="status" data-label="<?= e(__('common.status')) ?>">
        <?php if ($l['paid_at']): ?><span class="badge badge-ok"><?= e(__('payroll.paid_on', ['date' => fmt_date(substr($l['paid_at'], 0, 10))])) ?></span>
        <?php elseif ($run['status'] !== 'pending' && (float) $l['net_qar'] > 0): ?><span class="badge badge-warn"><?= e(__('payroll.unpaid')) ?></span><?php endif; ?>
        <?php if (($l['bill_id'] && Auth::can('finance')) || $l['payslip_document_id']): ?><span class="line-links">
          <?php if ($l['bill_id'] && Auth::can('finance')): ?><a class="btn btn-xs" href="<?= e(url('/portal/bills/' . $l['bill_id'])) ?>"><?= e(__('bills.singular')) ?></a><?php endif; ?>
          <?php if ($l['payslip_document_id']): ?><a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $l['payslip_document_id'])) ?>"><?= e(__('payroll.payslip')) ?></a><?php endif; ?>
        </span><?php endif; ?>
      </td>
      <?php endif; ?>
    </tr><?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($toPay): // tick who got paid (or all), the account the money left from and the date ?>
    <section class="card pay-box">
      <h2><?= e(__('payroll.pay_title')) ?></h2>
      <?php if ($quick): ?><p class="muted small"><?= e(__('payroll.quick_pay_hint')) ?></p><?php endif; ?>
      <?php if ($methodsOn && $groups && !$quick): // each salary is paid the way chosen on its line: one account per way ?>
      <p class="muted small"><?= e(__('payroll.pay_by_way_hint')) ?></p>
      <div class="pay-ways">
        <?php foreach ($groups as $k => $g): ?>
        <div class="pay-way">
          <p class="pay-way-head"><b><?= e($g['label']) ?></b> <span class="muted small"><?= e(__('payroll.way_count', ['n' => $g['n']])) ?><?php if ($sens): ?> · <?= money($g['amount']) ?><?php endif; ?></span></p>
          <div class="grid-fields">
            <div class="field col-6"><label for="f_acc_<?= (int) $k ?>"><?= e(__('payroll.pay_from')) ?></label>
              <div class="picker-row"><select id="f_acc_<?= (int) $k ?>" name="accounts[<?= (int) $k ?>]" data-picker="accounts"><option value=""><?= e(__('common.search_choose')) ?></option><?php if ($g['account']): ?><option value="<?= (int) $g['account'] ?>" selected><?= e($g['account_name']) ?></option><?php endif; ?></select></div></div>
            <?php if ($k === 0): // salaries without a way: one may be given for the payment, as before ?>
            <div class="field col-6"><label for="f_method"><?= e(__('bills.method')) ?></label>
              <select id="f_method" name="payment_method_id"><option value=""><?= e(__('common.choose')) ?></option>
                <?php foreach ($methods as $mid => $m): ?><option value="<?= (int) $mid ?>"><?= e(loc($m, 'value')) ?></option><?php endforeach; ?>
              </select></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="grid-fields">
        <div class="field col-4"><label for="f_date"><?= e(__('common.date')) ?></label><input id="f_date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>"></div>
      </div>
      <?php else: ?>
      <div class="grid-fields">
        <div class="field col-4"><label for="f_account_id"><?= e(__('payroll.pay_from')) ?> <span class="req">*</span></label>
          <div class="picker-row"><select id="f_account_id" name="account_id" data-picker="accounts"<?= $quick ? '' : ' required' ?>><option value=""><?= e(__('common.search_choose')) ?></option><?php if ($payAccount): ?><option value="<?= (int) $payAccount['id'] ?>" selected><?= e($payAccount['name']) ?></option><?php endif; ?></select></div></div>
        <div class="field col-4"><label for="f_method"><?= e(__('bills.method')) ?></label>
          <select id="f_method" name="payment_method_id"><option value=""><?= e(__('common.choose')) ?></option>
            <?php foreach (DB::all("SELECT id, value_en, value_ar FROM lookups WHERE type = 'payment_method' AND active = 1 ORDER BY sort") as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e(loc($m, 'value')) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field col-4"><label for="f_date"><?= e(__('common.date')) ?></label><input id="f_date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>"></div>
      </div>
      <?php endif; ?>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit" name="scope" value="all"<?= $quick ? ' formaction="' . e($payUrl) . '"' : '' ?> data-confirm-click="<?= e(__($quick ? 'payroll.confirm_quick_pay_all' : 'payroll.confirm_pay_all', ['n' => count($toPay)])) ?>"><?= e(__('payroll.pay_all', ['n' => count($toPay)])) ?></button>
        <button class="btn" type="submit" name="scope" value="selected"<?= $quick ? ' formaction="' . e($payUrl) . '"' : '' ?> data-confirm-click="<?= e(__($quick ? 'payroll.confirm_quick_pay_selected' : 'payroll.confirm_pay_selected')) ?>"><?= e(__('payroll.pay_selected')) ?></button>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($editable): ?>
    <?php if ($methodsOn): ?><p class="remember-ways"><label class="field-check"><input type="checkbox" name="remember_methods" value="1" checked> <?= e(__('payroll.remember_methods')) ?></label></p><?php endif; ?>
    <div class="form-actions sticky-actions">
      <button class="btn<?= $toPay ? '' : ' btn-primary' ?>" type="submit" name="action" value="save"><?= e(__('common.save')) ?></button>
      <button class="btn" type="submit" name="action" value="recalc" data-confirm-click="<?= e(__('payroll.confirm_recalc')) ?>"><?= e(__('payroll.recalc')) ?></button>
    </div>
  <?php endif; ?>
</form>
<?php if ($run['status'] === 'pending'): ?><div class="alert alert-info"><?= e(__('payroll.pending_note')) ?></div><?php endif; ?>
