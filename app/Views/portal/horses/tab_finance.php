<?php
use App\Core\Audit;
use App\Core\Auth;
use App\Services\HorseService;

$f = HorseService::finance((int) $h['id']);
$sensitive = Auth::can('finance', 'sensitive');
if ($sensitive) { Audit::log('view_sensitive', 'finance', 'horse', $h['id'], null, ['fields' => ['profit_loss']], 'Horse profit/loss'); }
?>
<div class="stat-grid">
  <div class="stat"><div class="k"><?= e(__('horses.total_cost')) ?></div><div class="v"><?= money($f['expenses']) ?></div><div class="s"><?= e(__('horses.incl_inventory')) ?> <?= money($f['usage']) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('horses.income')) ?></div><div class="v"><?= money($f['income']) ?></div><div class="s"><?= e(__('horses.income_help')) ?></div></div>
  <?php if ($sensitive): ?>
  <div class="stat dark"><div class="k"><?= e(__('horses.net_pl')) ?></div><div class="v"><?= money($f['net']) ?></div><div class="s"><?= e(__('horses.net_help')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('horses.purchase_price')) ?></div><div class="v"><?= money($f['purchase']) ?></div></div>
  <?php endif; ?>
</div>
<section class="card">
  <h2><?= e(__('horses.by_category')) ?></h2>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.type')) ?></th><th><?= e(__('finance.category')) ?></th><th class="num"><?= e(__('finance.approved_paid')) ?></th><th class="num"><?= e(__('finance.pending')) ?></th><th class="num"><?= e(__('common.total')) ?></th></tr></thead><tbody>
  <?php foreach ($f['rows'] as $r): ?><tr><td><?= e(__('bills.type_' . $r['type'])) ?></td><td><?= e($r['category'] ?? '—') ?></td><td class="num"><?= money($r['approved']) ?></td><td class="num"><?= money($r['pending']) ?></td><td class="num"><?= money($r['total']) ?></td></tr><?php endforeach; ?>
  <tr><td><?= e(__('bills.type_expense')) ?></td><td><?= e(__('horses.inventory_used')) ?></td><td class="num"><?= money($f['usage']) ?></td><td class="num"><?= money(0) ?></td><td class="num"><?= money($f['usage']) ?></td></tr>
  </tbody></table></div>
  <p><a class="btn btn-sm" href="<?= e(url('/portal/bills?horse_id=' . $h['id'])) ?>"><?= e(__('horses.all_bills')) ?></a></p>
</section>
