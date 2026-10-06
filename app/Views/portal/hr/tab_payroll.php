<?php
use App\Core\Auth;
use App\Core\DB;
$rows = DB::all('SELECT l.*, r.period, r.status FROM payroll_lines l JOIN payroll_runs r ON r.id = l.payroll_run_id WHERE l.employee_id = ? AND r.deleted_at IS NULL ORDER BY r.period DESC', [$emp['id']]);
$sens = Auth::can('payroll', 'sensitive');
?>
<section class="card"><?php if ($rows): ?><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.month')) ?></th><th><?= e(__('common.status')) ?></th><?php if ($sens): ?><th class="num"><?= e(__('payroll.net')) ?></th><?php endif; ?><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= e(url('/portal/payroll/' . $r['payroll_run_id'])) ?>"><?= e($r['period']) ?></a></td><td><?= status_badge($r['status'], 'payroll.status') ?></td><?php if ($sens): ?><td class="num"><?= money($r['net_qar']) ?></td><?php endif; ?>
<td><?php if ($r['payslip_document_id']): ?><a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $r['payslip_document_id'])) ?>"><?= e(__('payroll.payslip')) ?></a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?></section>
