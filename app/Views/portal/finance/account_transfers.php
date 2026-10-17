<?php
use App\Core\Auth;
use App\Core\DB;
$rows = DB::all('SELECT x.*, f.name AS from_name, t.name AS to_name FROM account_transfers x JOIN accounts f ON f.id = x.from_account_id JOIN accounts t ON t.id = x.to_account_id
  WHERE (x.from_account_id = ? OR x.to_account_id = ?) AND x.deleted_at IS NULL ORDER BY x.transfer_date DESC, x.id DESC LIMIT 200', [$a['id'], $a['id']]);
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('nav.transfers')) ?></h2>
    <?php if (Auth::can('finance', 'create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/portal/account-transfers/create?from_account_id=' . (int) $a['id'])) ?>">+ <?= e(__('accounts.new_transfer')) ?></a><?php endif; ?></div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('transfers.from')) ?></th><th><?= e(__('transfers.to')) ?></th><th class="num"><?= e(__('payments.amount')) ?></th><th><?= e(__('bills.reference')) ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><a href="<?= e(url('/portal/account-transfers/' . $r['id'])) ?>"><?= e(fmt_date($r['transfer_date'])) ?></a></td><td><?= e($r['from_name']) ?></td><td><?= e($r['to_name']) ?></td>
      <td class="num"><?= money($r['amount_qar']) ?></td><td><?= e($r['reference_no'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
</section>
