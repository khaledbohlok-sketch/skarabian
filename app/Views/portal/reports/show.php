<?php
use App\Core\Auth;
$p = $r['period'];
$presets = ['this_month' => 'reports.this_month', 'last_month' => 'reports.last_month', 'this_quarter' => 'reports.this_quarter', 'this_year' => 'reports.this_year', 'last_year' => 'reports.last_year', 'custom' => 'reports.custom'];
?>
<div class="page-head">
  <div><a class="crumb" href="<?= e(url('/portal/reports')) ?>">← <?= e(__('nav.reports')) ?></a><h1><?= e($title) ?></h1>
    <?php if (empty($r['no_period'])): ?><p class="muted"><?= e(fmt_date($p['from'])) ?> → <?= e(fmt_date($p['to'])) ?></p><?php else: ?><p class="muted"><?= e(__('reports.as_of', ['date' => fmt_date(date('Y-m-d'))])) ?></p><?php endif; ?></div>
  <div class="actions">
    <?php if (Auth::can('reports', 'export')): ?><a class="btn" href="<?= e(query_url(['export' => 'xlsx'])) ?>"><?= icon('download') ?> Excel</a><?php endif; ?>
    <?php if (Auth::can('reports', 'print')): ?><a class="btn" href="<?= e(query_url(['export' => 'pdf'])) ?>" target="_blank"><?= icon('print') ?> PDF</a><?php endif; ?>
  </div>
</div>
<?php if (empty($r['no_period'])): ?>
<form method="get" class="card filters report-filters">
  <div class="field"><label for="f_period"><?= e(__('reports.period')) ?></label>
    <select id="f_period" name="period"><?php foreach ($presets as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($p['key'], $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="f_from"><?= e(__('common.from')) ?></label><input id="f_from" type="date" name="from" value="<?= e($p['from']) ?>"></div>
  <div class="field"><label for="f_to"><?= e(__('common.to')) ?></label><input id="f_to" type="date" name="to" value="<?= e($p['to']) ?>"></div>
  <?php if ($r['type'] === 'expenses'): ?>
  <div class="field"><label for="f_by"><?= e(__('reports.group_by')) ?></label>
    <select id="f_by" name="by"><?php foreach (['category' => 'finance.category', 'supplier' => 'po.supplier', 'horse' => 'bills.horse'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($r['by'] ?? 'category', $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="field"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
  <p class="muted small" style="flex-basis:100%"><?= e(__('reports.custom_help')) ?></p>
</form>
<?php endif; ?>
<?php if ($r['cards']): ?>
<div class="stat-grid">
  <?php foreach ($r['cards'] as $i => [$label, $amount, $sub]): ?>
    <div class="stat<?= $i === 2 && $r['type'] === 'profit-loss' ? ' dark' : '' ?>"><div class="k"><?= e($label) ?></div><div class="v"><?= money($amount) ?></div><?php if ($sub): ?><div class="s"><?= e($sub) ?></div><?php endif; ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php foreach ($r['sections'] as $s): ?>
  <section class="card"><h2><?= e($s['title']) ?></h2><div class="table-wrap"><?= \App\Core\View::partial('portal/reports/table', ['s' => $s, 'tableClass' => 'table compact']) ?></div></section>
<?php endforeach; ?>
