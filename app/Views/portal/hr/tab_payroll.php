<?php
use App\Core\Auth;
use App\Core\DB;
$rows = DB::all('SELECT l.*, r.period, r.status FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id WHERE l.employee_id = ? AND r.deleted_at IS NULL ORDER BY r.period DESC', [$emp['id']]);
$sens = Auth::can('payroll', 'sensitive');
?>
<section class="card"><?php if ($rows): ?><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.month')) ?></th><th class="nowrap"><?= e(__('common.status')) ?></th><?php if ($sens): ?><th class="num"><?= e(__('payroll.net')) ?></th><?php endif; ?><th><span class="sr-only"><?= e(__('payroll.payslip')) ?></span></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <td class="is-title" data-label="<?= e(__('common.month')) ?>"><a href="<?= e(url('/portal/payroll/' . $r['payroll_run_id'])) ?>"><?= e(fmt_month($r['period'] . '-01') ?: $r['period']) ?></a></td>
  <td class="nowrap" data-label="<?= e(__('common.status')) ?>"><?= status_badge($r['status'], 'payroll.status') ?></td>
  <?php if ($sens): ?><td class="num" data-label="<?= e(__('payroll.net')) ?>"><?= money($r['net_qar']) ?></td><?php endif; ?>
  <td class="doc-row-actions<?= $r['payslip_document_id'] ? '' : ' is-empty' ?>"><?php if ($r['payslip_document_id']): ?><a class="btn btn-sm" href="<?= e(url('/portal/studio/' . $r['payslip_document_id'])) ?>"><?= icon('doc') ?> <?= e(__('payroll.payslip')) ?></a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><?= \App\Core\View::partial('portal/partials/empty', ['card' => false, 'icon' => 'payroll', 'message' => __('common.no_records')]) ?><?php endif; ?></section>
