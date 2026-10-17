<?php
use App\Core\Auth;
use App\Core\View;
use App\Services\Chart;
use App\Services\Dashboard;
use App\Services\SiteVisits;

$from = date('Y-m-01');
$to = date('Y-m-t');
$name = explode(' ', (string) (Auth::user()['name'] ?? ''))[0];
$activity = $activity ?? null;

// ---- figures (read only, from the dashboard service)
$hs = can('horses') ? Dashboard::horses() : null;
$em = can('embryos') ? Dashboard::embryos() : null;
$bills = can('finance') ? Dashboard::bills() : null;
$inv = can('inventory') ? Dashboard::inventory() : null;
$docs = can('hr') ? Dashboard::documents() : null;
$staff = can('hr') ? Dashboard::employees() : null;
$healthDue = can('horse_health') ? Dashboard::healthDue() : [];
$pregChecks = can('horses') && can('horse_breeding') ? Dashboard::pregnancyChecks() : [];
$tanks = can('semen_stock') ? Dashboard::tanksDue() : [];
$over = can('finance') ? Dashboard::budgetsExceeded() : [];

// ---- "Needs attention": one list, each line opens the page to act on: [tone, url, text (HTML), pill (HTML)]
$attention = [];
if ($approvals) {
    $attention[] = ['warn', '/portal/approvals', e(__('dashboard.waiting_approval')), '<span class="pill warn">' . count($approvals) . '</span>'];
}
foreach ($bills['overdue'] ?? [] as $b) {
    $attention[] = ['bad', '/portal/bills/' . $b['id'], e($b['number']) . ' · ' . e($b['party'] ?? $b['description']), '<span class="pill bad">' . money($b['due'], 'QAR', false) . '</span>'];
}
foreach ($over as $b) {
    $attention[] = ['bad', '/portal/budgets', e(__('dashboard.budget_exceeded', ['cat' => loc($b)])), '<span class="pill bad" dir="ltr"><span class="nowrap">' . money($b['spent'], 'QAR', false) . '</span> / <span class="nowrap">' . money($b['amount_qar'], 'QAR', false) . '</span></span>'];
}
foreach ($healthDue as $r) {
    $d = days_until($r['next_due_date']);
    $attention[] = [$d < 0 ? 'bad' : 'warn', '/portal/horses/' . $r['horse_id'] . '?tab=health-records', '<b>' . e(loc($r)) . '</b> · ' . e(__('health.type_' . $r['type'])), '<span class="pill ' . ($d < 0 ? 'bad' : 'warn') . '">' . ($d < 0 ? e(__('common.overdue')) : e(fmt_date($r['next_due_date']))) . '</span>'];
}
foreach ($hs['foaling30'] ?? [] as $f) {
    $d = days_until($f['expected_foaling_date']);
    $attention[] = [$d < 0 ? 'bad' : 'info', '/portal/horses/' . $f['mare_id'], e(__('dashboard.foaling_due', ['name' => loc($f)])), '<span class="pill ' . ($d < 0 ? 'bad' : 'warn') . '">' . e(fmt_date($f['expected_foaling_date'])) . '</span>'];
}
foreach ($pregChecks as $c) {
    $attention[] = [$c['days'] < 0 ? 'bad' : 'warn', '/portal/breeding-records/' . $c['id'], e(__('dashboard.preg_check_day', ['name' => loc($c), 'day' => $c['day']])), '<span class="pill ' . ($c['days'] < 0 ? 'bad' : 'warn') . '">' . ($c['days'] < 0 ? e(__('common.overdue')) . ' · ' : '') . e(fmt_date($c['due_date'])) . '</span>'];
}
foreach (array_merge($docs['expired'] ?? [], $docs['soon'] ?? []) as $x) {
    $exp = $x['date'] < date('Y-m-d');
    $attention[] = [$exp ? 'bad' : 'warn', '/portal/employees/' . $x['id'], '<b>' . e($x['name']) . '</b> · ' . e(__($x['doc'])), '<span class="pill ' . ($exp ? 'bad' : 'warn') . '">' . e(fmt_date($x['date'])) . '</span>'];
}
foreach ($inv['low'] ?? [] as $i) {
    $attention[] = ['bad', '/portal/items/' . $i['id'], e(loc($i)), '<span class="pill bad">' . e(__('dashboard.low_stock')) . ': ' . e(rtrim(rtrim((string) $i['quantity'], '0'), '.') . ' ' . lookup_label('unit', $i['unit'])) . '</span>'];
}
foreach ($inv['expiring'] ?? [] as $i) {
    $attention[] = ['warn', '/portal/items/' . $i['id'], e(loc($i)), '<span class="pill warn">' . e(__('dashboard.expires')) . ' ' . e(fmt_date($i['expiry_date'])) . '</span>'];
}
foreach ($tanks as $tk) {
    $attention[] = [$tk['days'] < 0 ? 'bad' : 'warn', can('semen_stock', 'edit') ? '/portal/tanks/' . $tk['id'] . '?tab=refills' : '/portal/semen-stock',
        '<b>' . e($tk['name']) . '</b> · ' . e($tk['days'] < 0 ? __('dashboard.refill_overdue', ['n' => -$tk['days']]) : __('dashboard.refill_due', ['date' => fmt_date($tk['due_date'])])), '<span class="pill ' . ($tk['days'] < 0 ? 'bad' : 'warn') . '">' . e(fmt_date($tk['due_date'])) . '</span>'];
}
?>
<?php // the welcome: the date, then a greeting for the time of day (the app's time zone) rising word by word, the name in gold
$hour = (int) date('G');
$greet = $hour < 12 ? 'dashboard.greet_morning' : ($hour < 18 ? 'dashboard.greet_afternoon' : 'dashboard.greet_evening'); ?>
<div class="page-head dash-hello">
  <div>
    <span class="eyebrow"><?= e(fmt_date(date('Y-m-d'))) ?></span>
    <h1 class="split split-now"><?= split_title(__($greet, ['name' => $name])) ?></h1>
  </div>
</div>

<?php if (!empty($pendingDb)): ?><div class="alert alert-warning"><strong><?= e(__('settings.db_pending', ['n' => $pendingDb])) ?></strong> <a href="<?= e(url('/portal/settings#database-update')) ?>"><?= e(__('settings.db_open')) ?></a></div><?php endif; ?>

<?php if ($hs || $em !== null || $bills || $inv || $staff !== null): ?>
<div class="kpi-grid">
  <?php if ($hs): ?>
  <a class="kpi" href="<?= e(url('/portal/horses')) ?>"><span class="kpi-icon"><?= icon('horse') ?></span><span class="kpi-label"><?= e(__('nav.horses')) ?></span><span class="kpi-value"><?= number_format((int) $hs['total']) ?></span><span class="kpi-sub"><?= e(__('dashboard.active_sk')) ?></span></a>
  <?php endif; ?>
  <?php if ($em !== null): ?>
  <a class="kpi" href="<?= e(url('/portal/embryos')) ?>"><span class="kpi-icon"><?= icon('embryo') ?></span><span class="kpi-label"><?= e(__('nav.embryos')) ?></span><span class="kpi-value"><?= number_format((int) array_sum($em)) ?></span><span class="kpi-sub"><?= e(__('embryos.status_fresh')) ?> <?= (int) ($em['fresh'] ?? 0) ?> · <?= e(__('embryos.status_frozen')) ?> <?= (int) ($em['frozen'] ?? 0) ?></span></a>
  <?php endif; ?>
  <?php if ($bills): ?>
  <a class="kpi<?= $bills['overdue_count'] ? ' is-bad' : '' ?>" href="<?= e(url('/portal/bills?status=pending')) ?>"><span class="kpi-icon"><?= icon('wallet') ?></span><span class="kpi-label"><?= e(__('dashboard.pending_bills')) ?></span><span class="kpi-value"><?= number_format((int) $bills['pending']) ?></span><span class="kpi-sub"><?= e(__('status.overdue')) ?>: <?= (int) $bills['overdue_count'] ?> · <?= money($bills['overdue_total']) ?></span></a>
  <?php endif; ?>
  <?php if ($inv): $invAlerts = count($inv['low']) + count($inv['expiring']); ?>
  <a class="kpi<?= $invAlerts ? ' is-warn' : '' ?>" href="<?= e(url('/portal/items')) ?>"><span class="kpi-icon"><?= icon('box') ?></span><span class="kpi-label"><?= e(__('dashboard.inventory_alerts')) ?></span><span class="kpi-value"><?= $invAlerts ?></span><span class="kpi-sub"><?= e(plural((int) $inv['items'], 'count.item')) ?> · <?= e(plural((int) $inv['stores'], 'count.store')) ?></span></a>
  <?php endif; ?>
  <?php if ($staff !== null): $expired = count($docs['expired'] ?? []); ?>
  <a class="kpi<?= $expired ? ' is-warn' : '' ?>" href="<?= e(url('/portal/employees')) ?>"><span class="kpi-icon"><?= icon('users') ?></span><span class="kpi-label"><?= e(__('dashboard.staff')) ?></span><span class="kpi-value"><?= number_format($staff) ?></span><span class="kpi-sub"><?= e(__('dashboard.expired')) ?>: <?= $expired ?> · <?= e(__('dashboard.expiring_60')) ?>: <?= count($docs['soon'] ?? []) ?></span></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($approvals): ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('dashboard.waiting_approval')) ?> <span class="pill warn"><?= count($approvals) ?></span></h2><a href="<?= e(url('/portal/approvals')) ?>"><?= e(__('common.view_all')) ?></a></div>
  <?php foreach (array_slice($approvals, 0, 5) as $a): ?><?= View::partial('portal/approvals/card', ['a' => $a]) ?><?php endforeach; ?>
</section>
<?php endif; ?>

<div class="dash-grid<?= $activity === null ? ' one' : '' ?>">
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.needs_attention')) ?><?php if ($attention): ?> <span class="pill <?= array_filter($attention, fn ($x) => $x[0] === 'bad') ? 'bad' : 'warn' ?>"><?= count($attention) ?></span><?php endif; ?></h2></div>
    <?php if ($attention): ?>
    <ul class="alert-list attention-list scroll-list">
      <?php foreach ($attention as [$tone, $href, $text, $pill]): ?>
        <li><span class="att-dot <?= e($tone) ?>" aria-hidden="true"></span><a href="<?= e(url($href)) ?>"><?= $text ?></a><?= $pill ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="all-clear"><?= icon('check-circle') ?><?= e(__('dashboard.all_clear')) ?></p>
    <?php endif; ?>
  </section>
  <?php if ($activity !== null): ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.recent_activity')) ?></h2><a href="<?= e(url('/portal/activity')) ?>"><?= e(__('common.view_all')) ?></a></div>
    <?php if ($activity): ?>
    <ul class="alert-list activity-list scroll-list">
      <?php foreach ($activity as $a): $who = (string) ($a['user_name'] ?? ''); ?>
        <li><span class="avatar sm" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($who !== '' ? $who : '·', 0, 1))) ?></span>
          <a href="<?= e(url('/portal/activity/' . $a['id'])) ?>"><b><?= e($who !== '' ? $who : '—') ?></b> · <?= e(\App\Core\Lang::has('activity.a_' . $a['action']) ? __('activity.a_' . $a['action']) : $a['action']) ?><?php $act = \App\Core\Lang::has('activity.a_' . $a['action']) ? __('activity.a_' . $a['action']) : $a['action']; $sum = activity_summary($a); if ($sum !== '' && mb_strtolower($sum) !== mb_strtolower($act)): ?> — <?= e(mb_strimwidth($sum, 0, 70, '…')) ?><?php endif; ?></a>
          <small><time datetime="<?= e($a['created_at']) ?>"><?= e(fmt_date($a['created_at'], true)) ?></time></small></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><?= View::partial('portal/partials/empty', ['card' => false, 'icon' => 'history', 'message' => __('common.no_activity')]) ?><?php endif; ?>
  </section>
  <?php endif; ?>
</div>

<?php if ($hs): ?>
<div class="stat-grid six">
  <a class="stat dark" href="<?= e(url('/portal/horses')) ?>"><div class="k"><?= e(__('nav.horses')) ?></div><div class="v"><?= (int) $hs['total'] ?></div><div class="s"><?= e(__('dashboard.active_sk')) ?></div></a>
  <?php foreach (['stallion', 'mare', 'colt', 'filly', 'foal'] as $c): ?>
    <a class="stat" href="<?= e(url('/portal/horses?category=' . $c)) ?>"><div class="k"><?= e(__('horse.cat_' . $c)) ?></div><div class="v"><?= (int) ($hs['cats'][$c] ?? 0) ?></div></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-2">
  <?php if ($hs): ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.breeding')) ?></h2><a href="<?= e(url('/portal/breeding-records')) ?>"><?= e(__('common.view_all')) ?></a></div>
    <ul class="alert-list">
      <li><span><?= e(__('dashboard.pregnant_mares')) ?></span><span class="pill ok"><?= (int) $hs['pregnant'] ?></span></li>
    </ul>
    <?php if ($em !== null): ?>
      <h3 class="mt"><?= e(__('nav.embryos')) ?></h3>
      <div class="chips"><?php foreach (['fresh', 'frozen', 'transferred', 'pregnant', 'foaled', 'failed'] as $s): ?><a class="chip" href="<?= e(url('/portal/embryos?status=' . $s)) ?>"><?= e(__('embryos.status_' . $s)) ?>: <b><?= (int) ($em[$s] ?? 0) ?></b></a><?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (can('semen_stock')): $sm = Dashboard::semen(); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('nav.semen_stock')) ?></h2><a href="<?= e(url('/portal/semen-stock')) ?>"><?= e(__('common.view_all')) ?></a></div>
    <div class="stat-grid two">
      <div class="stat"><div class="k"><?= e(__('semen_stock.total_doses')) ?></div><div class="v"><?= (int) $sm['doses'] ?></div></div>
      <div class="stat <?= $sm['low'] ? 'alert-bad' : '' ?>"><div class="k"><?= e(__('semen_stock.low_stallions')) ?></div><div class="v"><?= (int) $sm['low'] ?></div></div>
    </div>
    <h3 class="mt"><?= e(__('dashboard.tank_refills')) ?></h3>
    <ul class="alert-list">
      <?php foreach ($tanks as $t): ?>
        <li><span><b><?= e($t['name']) ?></b> · <?= e($t['days'] < 0 ? __('dashboard.refill_overdue', ['n' => -$t['days']]) : __('dashboard.refill_due', ['date' => fmt_date($t['due_date'])])) ?></span><?php if (can('semen_stock', 'edit')): ?><a class="btn btn-sm" href="<?= e(url('/portal/tanks/' . $t['id'] . '?tab=refills')) ?>"><?= e(__('dashboard.record_refill')) ?></a><?php else: ?><span class="pill <?= $t['days'] < 0 ? 'bad' : 'warn' ?> nowrap"><?= e(fmt_date($t['due_date'])) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
      <?php if (!$tanks): ?><li class="muted"><?= e(__('dashboard.tanks_ok')) ?></li><?php endif; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if (can('cms') && SiteVisits::on()): $vt = SiteVisits::totals(7); $vh = SiteVisits::horses(7, 3); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('visits.dash_title')) ?></h2><a href="<?= e(url('/portal/cms/visitors?days=7')) ?>"><?= e(__('visits.details')) ?></a></div>
    <div class="stat-grid two">
      <div class="stat"><div class="k"><?= e(__('visits.visits')) ?></div><div class="v"><?= e(number_format($vt['visits'])) ?></div><div class="s"><?= e(__('visits.prev', ['n' => number_format($vt['prev_visits'])])) ?></div></div>
      <div class="stat"><div class="k"><?= e(__('visits.views')) ?></div><div class="v"><?= e(number_format($vt['views'])) ?></div></div>
    </div>
    <h3 class="mt"><?= e(__('visits.top_horses')) ?></h3>
    <ul class="alert-list">
      <?php foreach ($vh as $h): ?><li><span><?= e(loc($h)) ?></span><span class="pill nowrap"><?= e(__('visits.n_visits', ['n' => number_format((int) $h['visits'])])) ?></span></li><?php endforeach; ?>
      <?php if (!$vh): ?><li class="muted"><?= e(__('visits.no_horse_views')) ?></li><?php endif; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if (can('payroll')): $p = Dashboard::payrollDue(); ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.payroll_due', ['month' => fmt_month(date('Y-m-d'))])) ?></h2><a class="btn btn-sm" href="<?= e(url('/portal/payroll')) ?>"><?= e(__('nav.payroll')) ?></a></div>
    <p><?= $p['run'] ? status_badge($p['run']['status'], 'payroll.status') . ' ' . (can('payroll', 'sensitive') ? money($p['run']['total_net_qar']) : '') : e(__('dashboard.payroll_not_started')) . (can('payroll', 'sensitive') ? ' · ' . e(__('dashboard.estimate')) . ' ' . money($p['estimate']) : '') ?></p>
  </section>
  <?php endif; ?>
</div>

<?php if (can('finance')): $mo = Dashboard::money($from, $to);
  // paid / approved-unpaid / pending approval / draft: kept apart (drafts are shown but not in the total)
  $split = fn (array $f, bool $income) => '<div class="s money-split">'
      . '<span>' . e(__($income ? 'finance.b_received' : 'finance.b_paid')) . ' <b>' . money($f['paid']) . '</b></span>'
      . '<span>' . e(__($income ? 'finance.b_not_received' : 'finance.b_unpaid')) . ' <b>' . money($f['unpaid']) . '</b></span>'
      . '<span>' . e(__('finance.b_pending')) . ' <b>' . money($f['pending']) . '</b></span>'
      . ($f['draft'] ? '<span class="muted">' . e(__('finance.b_draft_not_counted')) . ' <b>' . money($f['draft']) . '</b></span>' : '') . '</div>'; ?>
<h2 class="mt"><?= e(__('dashboard.money_month', ['month' => fmt_month(date('Y-m-d'))])) ?></h2>
<div class="stat-grid">
  <a class="stat" href="<?= e(url('/portal/bills?type=income&from=' . $from . '&to=' . $to)) ?>"><div class="k"><?= e(__('dashboard.income')) ?></div><div class="v"><?= money($mo['inc']['total']) ?></div><?= $split($mo['inc'], true) ?></a>
  <a class="stat" href="<?= e(url('/portal/bills?type=expense&from=' . $from . '&to=' . $to)) ?>"><div class="k"><?= e(__('dashboard.expenses')) ?></div><div class="v"><?= money($mo['exp']['total']) ?></div><?= $split($mo['exp'], false) ?></a>
  <div class="stat dark"><div class="k"><?= e(__('dashboard.net')) ?></div><div class="v"><?= money($mo['net']) ?></div><div class="s"><?= e(__('dashboard.net_help')) ?></div></div>
  <a class="stat <?= $bills['overdue'] ? 'alert-bad' : '' ?>" href="<?= e(url('/portal/bills?status=pending')) ?>"><div class="k"><?= e(__('dashboard.pending_bills')) ?></div><div class="v"><?= (int) $bills['pending'] ?></div><div class="s"><?= e(__('status.overdue')) ?>: <?= (int) $bills['overdue_count'] ?> · <?= money($bills['overdue_total']) ?></div></a>
</div>
<div class="grid-2">
  <section class="card chart">
    <div class="card-head"><h2><?= e(__('dashboard.income_vs_expense')) ?></h2></div>
    <?php $m = Dashboard::monthly(); $labels = array_map(fn ($k) => fmt_month($k . '-01', true, false), array_keys($m)); ?>
    <?= Chart::bars($labels, [[__('dashboard.income'), '#B08D57', array_column($m, 'income')], [__('dashboard.expenses'), '#213562', array_column($m, 'expense')]]) ?>
    <div class="legend"><span><i class="s-a"></i><?= e(__('dashboard.income')) ?></span><span><i class="s-b"></i><?= e(__('dashboard.expenses')) ?></span><span class="muted"><?= e(__('dashboard.chart_note')) ?></span></div>
  </section>
  <section class="card chart">
    <div class="card-head"><h2><?= e(__('dashboard.top_expenses')) ?></h2></div>
    <?= Chart::hbars(array_map(fn ($r) => [loc(['name_en' => $r['name'], 'name_ar' => $r['name_ar']]), $r['total']], Dashboard::topCategories(date('Y-01-01'), $to))) ?: Chart::empty() ?>
    <h2 class="mt"><?= e(__('dashboard.cost_per_horse')) ?></h2>
    <?= Chart::hbars(array_map(fn ($r) => [loc($r), $r['total']], Dashboard::costPerHorse(date('Y-01-01'), $to)), '#B08D57') ?: Chart::empty() ?>
  </section>
  <?php if (can('finance', 'sensitive')): ?>
  <section class="card">
    <div class="card-head"><h2><?= e(__('dashboard.cash_position')) ?></h2></div>
    <ul class="alert-list"><?php $sum = 0; foreach (Dashboard::cash() as $a): $sum += (float) $a['balance']; ?><li><span><?= e($a['name']) ?> <small class="muted">· <?= e(__('accounts.type_' . $a['type'])) ?></small></span><?= money($a['balance']) ?></li><?php endforeach; ?>
      <li><b><?= e(__('common.total')) ?></b><b><?= money($sum) ?></b></li></ul>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>
