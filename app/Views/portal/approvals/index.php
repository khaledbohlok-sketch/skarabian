<?php
use App\Core\View;

$history = $status === 'all';
$switch = '<a class="btn" href="' . e(url('/portal/approvals' . ($history ? '' : '?status=all'))) . '">' . icon($history ? 'list' : 'history') . ' ' . e(__($history ? 'approvals.my_requests' : 'approvals.history')) . '</a>';
?>
<?= View::partial('portal/partials/list_head', [
    'title' => __('nav.approvals'), 'count' => $pending ? plural(count($pending), 'count.request') . ' · ' . __('approvals.waiting_short') : '',
    'extra' => $switch,
]) ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('dashboard.waiting_approval')) ?></h2><?php if ($pending): ?><span class="pill warn"><?= number_format(count($pending)) ?></span><?php endif; ?></div>
  <?php if ($pending): foreach ($pending as $a): ?><?= View::partial('portal/approvals/card', ['a' => $a]) ?><?php endforeach; else: ?>
    <div class="all-clear"><?= icon('check-circle') ?> <span><?= e(__('approvals.none')) ?></span></div>
  <?php endif; ?>
  <p class="small muted mt"><?= e(__('approvals.rule')) ?></p>
</section>
<section class="card">
  <div class="card-head"><h2><?= e(__($history ? 'approvals.history' : 'approvals.my_requests')) ?></h2><?php if ($mine): ?><span class="muted small"><?= e(plural(count($mine), 'count.request')) ?></span><?php endif; ?></div>
  <?php if ($mine): ?><div class="table-wrap"><table class="table compact"><thead><tr><th class="nowrap"><?= e(__('common.date')) ?></th><th><?= e(__('common.description')) ?></th><th class="num"><?= e(__('common.amount')) ?></th><th class="nowrap"><?= e(__('common.status')) ?></th></tr></thead><tbody>
  <?php foreach ($mine as $a): ?><tr>
    <td class="nowrap" data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($a['requested_at'])) ?></td>
    <td class="is-title c-text" data-label="<?= e(__('common.description')) ?>"><a href="<?= e(url('/portal/approvals/' . $a['id'])) ?>"><?= e($a['title']) ?></a><?= $a['decision_note'] ? '<br><small class="muted">' . e($a['decision_note']) . '</small>' : '' ?></td>
    <td class="num<?= $a['amount_qar'] === null ? ' is-empty' : '' ?>" data-label="<?= e(__('common.amount')) ?>"><?= money($a['amount_qar']) ?></td>
    <td class="nowrap" data-label="<?= e(__('common.status')) ?>"><?= status_badge($a['status'], 'approvals.status') ?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?>
    <?= View::partial('portal/partials/empty', ['card' => false, 'icon' => 'check-circle', 'message' => __('common.no_records')]) ?>
  <?php endif; ?>
</section>
