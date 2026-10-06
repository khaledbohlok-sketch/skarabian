<?php
use App\Core\Auth;
use App\Core\View;
use App\Services\Chart;
use App\Services\Dashboard;

$from = date('Y-m-01');
$to = date('Y-m-t');
$name = explode(' ', (string) (Auth::user()['name'] ?? ''))[0];
?>
<div class="page-head">
  <div><h1><?= e(__('dashboard.hello', ['name' => $name])) ?></h1><p class="muted"><?= e(fmt_date(date('Y-m-d'))) ?></p></div>
  <div class="quick-add">
    <?php if (can('finance', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/bills/create')) ?>"><?= icon('plus') ?> <?= e(__('dashboard.add_bill')) ?></a><?php endif; ?>
    <?php if (can('horses', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/horses/create')) ?>"><?= icon('plus') ?> <?= e(__('dashboard.add_horse')) ?></a><?php endif; ?>
    <?php if (can('embryos', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/embryos/create')) ?>"><?= icon('plus') ?> <?= e(__('dashboard.add_embryo')) ?></a><?php endif; ?>
    <?php if (can('horse_health', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/health-records/create')) ?>"><?= icon('plus') ?> <?= e(__('dashboard.add_health')) ?></a><?php endif; ?>
    <?php if (can('horse_diet', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/diet-logs/create')) ?>"><?= icon('plus') ?> <?= e(__('dashboard.add_feed')) ?></a><?php endif; ?>
  </div>
</div>

<?php if ($approvals): ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('dashboard.waiting_approval')) ?> <span class="pill warn"><?= count($approvals) ?></span></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/approvals')) ?>"><?= e(__('common.view_all')) ?></a></div>
  <?php foreach (array_slice($approvals, 0, 5) as $a): ?><?= View::partial('portal/approvals/card', ['a' => $a]) ?><?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (can('horses')): $hs = Dashboard::horses(); ?>
<div class="stat-grid six">
  <a class="stat dark" href="<?= e(url('/portal/horses')) ?>"><div class="k"><?= e(__('nav.horses')) ?></div><div class="v"><?= (int) $hs['total'] ?></div><div class="s"><?= e(__('dashboard.active_sk')) ?></div></a>
  <?php foreach (['stallion', 'mare', 'colt', 'filly', 'foal'] as $c): ?>
    <a class="stat" href="<?= e(url('/portal/horses?category=' . $c)) ?>"><div class="k"><?= e(__('horse.cat_' . $c)) ?></div><div class="v"><?= (int) ($hs['cats'][$c] ?? 0) ?></div></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-2">
  <?php if (can('horses')): ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.breeding')) ?></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/breeding-records')) ?>"><?= e(__('common.view_all')) ?></a></div>
    <ul class="alert-list">
      <li><span><?= e(__('dashboard.pregnant_mares')) ?></span><span class="pill ok"><?= (int) $hs['pregnant'] ?></span></li>
      <?php foreach ($hs['foaling30'] as $f): $d = days_until($f['expected_foaling_date']); ?>
        <li><a href="<?= e(url('/portal/horses/' . $f['mare_id'])) ?>"><?= e(__('dashboard.foaling_due', ['name' => $f['name_en']])) ?></a><span class="pill <?= $d < 0 ? 'bad' : 'warn' ?>"><?= e(fmt_date($f['expected_foaling_date'])) ?></span></li>
      <?php endforeach; ?>
      <?php foreach ($hs['checks'] as $c): ?>
        <li><a href="<?= e(url('/portal/breeding-records/' . $c['id'])) ?>"><?= e(__('dashboard.check_needed', ['name' => $c['name_en']])) ?></a><span class="pill warn"><?= e(fmt_date($c['start_date'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if (can('embryos')): $em = Dashboard::embryos(); ?>
      <h3 class="mt"><?= e(__('nav.embryos')) ?></h3>
      <div class="chips"><?php foreach (['fresh', 'frozen', 'transferred', 'pregnant', 'foaled', 'failed'] as $s): ?><a class="chip" href="<?= e(url('/portal/embryos?status=' . $s)) ?>"><?= e(__('embryos.status_' . $s)) ?>: <b><?= (int) ($em[$s] ?? 0) ?></b></a><?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (can('horse_health')): $due = Dashboard::healthDue(); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.health_due')) ?></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/health-records?due=overdue')) ?>"><?= e(__('common.view_all')) ?></a></div>
    <?php if ($due): ?><ul class="alert-list"><?php foreach ($due as $r): $d = days_until($r['next_due_date']); ?>
      <li><a href="<?= e(url('/portal/horses/' . $r['horse_id'] . '?tab=health-records')) ?>"><b><?= e($r['name_en']) ?></b> · <?= e(__('health.type_' . $r['type'])) ?></a><span class="pill <?= $d < 0 ? 'bad' : 'warn' ?>"><?= $d < 0 ? e(__('common.overdue')) : e(fmt_date($r['next_due_date'])) ?></span></li>
    <?php endforeach; ?></ul><?php else: ?><p class="muted"><?= e(__('dashboard.nothing_due')) ?></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (can('hr')): $docs = Dashboard::documents(); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.staff_documents')) ?></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/employees')) ?>"><?= e(Dashboard::employees()) ?> <?= e(__('nav.employees')) ?></a></div>
    <div class="stat-grid" style="grid-template-columns:1fr 1fr">
      <div class="stat alert-bad"><div class="k"><?= e(__('dashboard.expired')) ?></div><div class="v"><?= count($docs['expired']) ?></div></div>
      <div class="stat alert-warn"><div class="k"><?= e(__('dashboard.expiring_60')) ?></div><div class="v"><?= count($docs['soon']) ?></div></div>
    </div>
    <ul class="alert-list">
      <?php foreach (array_merge($docs['expired'], $docs['soon']) as $x): $exp = $x['date'] < date('Y-m-d'); ?>
        <li><a href="<?= e(url('/portal/employees/' . $x['id'])) ?>"><b><?= e($x['name']) ?></b> · <?= e(__($x['doc'])) ?></a><span class="pill <?= $exp ? 'bad' : 'warn' ?>"><?= e(fmt_date($x['date'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if (can('inventory')): $inv = Dashboard::inventory(); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('nav.inventory')) ?></h2><a class="btn btn-sm ghost" href="<?= e(url('/portal/items')) ?>"><?= (int) $inv['items'] ?> <?= e(__('nav.items')) ?> · <?= (int) $inv['stores'] ?> <?= e(__('nav.inventories')) ?></a></div>
    <ul class="alert-list">
      <?php foreach ($inv['low'] as $i): ?><li><a href="<?= e(url('/portal/items/' . $i['id'])) ?>"><?= e($i['name_en']) ?></a><span class="pill bad"><?= e(__('dashboard.low_stock')) ?>: <?= e(rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . $i['unit']) ?></span></li><?php endforeach; ?>
      <?php foreach ($inv['expiring'] as $i): ?><li><a href="<?= e(url('/portal/items/' . $i['id'])) ?>"><?= e($i['name_en']) ?></a><span class="pill warn"><?= e(__('dashboard.expires')) ?> <?= e(fmt_date($i['expiry_date'])) ?></span></li><?php endforeach; ?>
      <?php if (!$inv['low'] && !$inv['expiring']): ?><li class="muted"><?= e(__('dashboard.stock_ok')) ?></li><?php endif; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>

<?php if (can('finance')): $mo = Dashboard::money($from, $to); $bills = Dashboard::bills(); ?>
<h2 class="mt"><?= e(__('dashboard.money_month', ['month' => date('F Y')])) ?></h2>
<div class="stat-grid">
  <a class="stat" href="<?= e(url('/portal/bills?type=income&from=' . $from . '&to=' . $to)) ?>"><div class="k"><?= e(__('dashboard.income')) ?></div><div class="v"><?= money($mo['inc']) ?></div><div class="s"><?= e(__('finance.approved_paid')) ?> <?= money($mo['inc_ok']) ?> · <?= e(__('finance.pending')) ?> <?= money($mo['inc_pend']) ?></div></a>
  <a class="stat" href="<?= e(url('/portal/bills?type=expense&from=' . $from . '&to=' . $to)) ?>"><div class="k"><?= e(__('dashboard.expenses')) ?></div><div class="v"><?= money($mo['exp']) ?></div><div class="s"><?= e(__('finance.approved_paid')) ?> <?= money($mo['exp_ok']) ?> · <?= e(__('finance.pending')) ?> <?= money($mo['exp_pend']) ?></div></a>
  <div class="stat dark"><div class="k"><?= e(__('dashboard.net')) ?></div><div class="v"><?= money($mo['net']) ?></div><div class="s"><?= e(__('dashboard.net_help')) ?></div></div>
  <a class="stat <?= $bills['overdue'] ? 'alert-bad' : '' ?>" href="<?= e(url('/portal/bills?status=pending')) ?>"><div class="k"><?= e(__('dashboard.pending_bills')) ?></div><div class="v"><?= (int) $bills['pending'] ?></div><div class="s"><?= e(__('status.overdue')) ?>: <?= count($bills['overdue']) ?> · <?= money($bills['overdue_total']) ?></div></a>
</div>
<div class="grid-2">
  <section class="card chart">
    <h2><?= e(__('dashboard.income_vs_expense')) ?></h2>
    <?php $m = Dashboard::monthly(); $labels = array_map(fn ($k) => date('M', strtotime($k . '-01')), array_keys($m)); ?>
    <?= Chart::bars($labels, [[__('dashboard.income'), '#B08D57', array_column($m, 'income')], [__('dashboard.expenses'), '#213562', array_column($m, 'expense')]]) ?>
    <div class="legend"><span><i style="background:#B08D57"></i><?= e(__('dashboard.income')) ?></span><span><i style="background:#213562"></i><?= e(__('dashboard.expenses')) ?></span><span><?= e(__('dashboard.chart_note')) ?></span></div>
  </section>
  <section class="card chart">
    <h2><?= e(__('dashboard.top_expenses')) ?></h2>
    <?= Chart::hbars(array_map(fn ($r) => [loc(['name_en' => $r['name'], 'name_ar' => $r['name_ar']]), $r['total']], Dashboard::topCategories(date('Y-01-01'), $to))) ?: '<p class="muted">' . e(__('common.no_records')) . '</p>' ?>
    <h2 class="mt"><?= e(__('dashboard.cost_per_horse')) ?></h2>
    <?= Chart::hbars(array_map(fn ($r) => [$r['name_en'], $r['total']], Dashboard::costPerHorse(date('Y-01-01'), $to)), '#B08D57') ?: '<p class="muted">' . e(__('common.no_records')) . '</p>' ?>
  </section>
  <?php if (can('finance', 'sensitive')): ?>
  <section class="card">
    <h2><?= e(__('dashboard.cash_position')) ?></h2>
    <ul class="alert-list"><?php $sum = 0; foreach (Dashboard::cash() as $a): $sum += (float) $a['balance']; ?><li><span><?= e($a['name']) ?> <small class="muted">· <?= e(__('accounts.type_' . $a['type'])) ?></small></span><?= money($a['balance']) ?></li><?php endforeach; ?>
      <li><b><?= e(__('common.total')) ?></b><b><?= money($sum) ?></b></li></ul>
  </section>
  <?php endif; ?>
  <?php $over = Dashboard::budgetsExceeded(); if ($over || $bills['overdue']): ?>
  <section class="card">
    <h2><?= e(__('dashboard.finance_alerts')) ?></h2>
    <ul class="alert-list">
      <?php foreach ($bills['overdue'] as $b): ?><li><a href="<?= e(url('/portal/bills/' . $b['id'])) ?>"><?= e($b['number']) ?> · <?= e($b['party'] ?? $b['description']) ?></a><span class="pill bad"><?= money($b['due'], 'QAR', false) ?></span></li><?php endforeach; ?>
      <?php foreach ($over as $b): ?><li><span><?= e(__('dashboard.budget_exceeded', ['cat' => $b['name_en']])) ?></span><span class="pill bad"><?= money($b['spent'], 'QAR', false) ?> / <?= money($b['amount_qar'], 'QAR', false) ?></span></li><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (can('payroll')): $p = Dashboard::payrollDue(); ?>
<section class="card mt">
  <div class="card-head"><h2><?= e(__('dashboard.payroll_due', ['month' => date('F Y')])) ?></h2><a class="btn btn-sm" href="<?= e(url('/portal/payroll')) ?>"><?= e(__('nav.payroll')) ?></a></div>
  <p><?= $p['run'] ? status_badge($p['run']['status'], 'payroll.status') . ' ' . (can('payroll', 'sensitive') ? money($p['run']['total_net_qar']) : '') : e(__('dashboard.payroll_not_started')) . (can('payroll', 'sensitive') ? ' · ' . e(__('dashboard.estimate')) . ' ' . money($p['estimate']) : '') ?></p>
</section>
<?php endif; ?>
