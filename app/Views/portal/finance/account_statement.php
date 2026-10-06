<?php
use App\Core\DB;
// Latest 300 movements of this account, with the running balance
$rows = DB::all("SELECT * FROM (
    SELECT p.payment_date AS d, p.id AS pid, b.id AS bill_id, b.number AS ref, COALESCE(b.description, '') AS descr,
           CASE WHEN b.type = 'income' THEN p.amount_qar ELSE -p.amount_qar END AS amount
      FROM bill_payments p JOIN bills b ON b.id = p.bill_id WHERE p.account_id = :a1 AND p.deleted_at IS NULL
    UNION ALL
    SELECT x.transfer_date, x.id, NULL, CONCAT('→ ', (SELECT name FROM accounts WHERE id = x.to_account_id)), COALESCE(x.notes, ''), -x.amount_qar
      FROM account_transfers x WHERE x.from_account_id = :a2 AND x.deleted_at IS NULL
    UNION ALL
    SELECT x.transfer_date, x.id, NULL, CONCAT('← ', (SELECT name FROM accounts WHERE id = x.from_account_id)), COALESCE(x.notes, ''), x.amount_qar
      FROM account_transfers x WHERE x.to_account_id = :a3 AND x.deleted_at IS NULL
  ) m ORDER BY d ASC, pid ASC", ['a1' => $a['id'], 'a2' => $a['id'], 'a3' => $a['id']]);
$bal = (float) $a['opening_balance_qar'];
foreach ($rows as &$r) { $bal += (float) $r['amount']; $r['balance'] = $bal; }
unset($r);
$rows = array_slice(array_reverse($rows), 0, 300);
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('accounts.statement')) ?></h2><div><?= e(__('accounts.balance')) ?>: <b><?= money($bal) ?></b></div></div>
  <p class="muted small"><?= e(__('accounts.opening_balance')) ?>: <?= money($a['opening_balance_qar']) ?><?= $a['opening_date'] ? ' · ' . e(fmt_date($a['opening_date'])) : '' ?></p>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('bills.reference')) ?></th><th><?= e(__('common.description')) ?></th><th class="num"><?= e(__('accounts.in')) ?></th><th class="num"><?= e(__('accounts.out')) ?></th><th class="num"><?= e(__('accounts.balance')) ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><?= e(fmt_date($r['d'])) ?></td>
      <td><?php if ($r['bill_id']): ?><a href="<?= e(url('/portal/bills/' . $r['bill_id'])) ?>"><?= e($r['ref']) ?></a><?php else: ?><?= e($r['ref']) ?><?php endif; ?></td>
      <td><?= e(mb_strimwidth($r['descr'], 0, 70, '…')) ?></td>
      <td class="num"><?= $r['amount'] > 0 ? money($r['amount']) : '' ?></td>
      <td class="num"><?= $r['amount'] < 0 ? money(-$r['amount']) : '' ?></td>
      <td class="num"><?= money($r['balance']) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
</section>
