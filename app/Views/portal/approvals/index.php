<?php use App\Core\View; ?>
<div class="page-head"><h1><?= e(__('nav.approvals')) ?></h1></div>
<section class="card">
  <h2><?= e(__('dashboard.waiting_approval')) ?></h2>
  <?php if ($pending): foreach ($pending as $a): ?><?= View::partial('portal/approvals/card', ['a' => $a]) ?><?php endforeach; else: ?><p class="muted"><?= e(__('approvals.none')) ?></p><?php endif; ?>
  <p class="small muted"><?= e(__('approvals.rule')) ?></p>
</section>
<section class="card">
  <div class="card-head"><h2><?= e(__($status === 'all' ? 'approvals.history' : 'approvals.my_requests')) ?></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/approvals' . ($status === 'all' ? '' : '?status=all'))) ?>"><?= e(__($status === 'all' ? 'approvals.my_requests' : 'approvals.history')) ?></a></div>
  <?php if ($mine): ?><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('common.description')) ?></th><th class="num"><?= e(__('common.amount')) ?></th><th><?= e(__('common.status')) ?></th></tr></thead><tbody>
  <?php foreach ($mine as $a): ?><tr><td><?= e(fmt_date($a['requested_at'], true)) ?></td><td><?= e($a['title']) ?><?= $a['decision_note'] ? '<br><small class="muted">' . e($a['decision_note']) . '</small>' : '' ?></td><td class="num"><?= money($a['amount_qar']) ?></td><td><?= status_badge($a['status'], 'approvals.status') ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
</section>
