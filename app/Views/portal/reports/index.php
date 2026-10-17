<div class="page-head"><div><h1><?= e(__('nav.reports')) ?></h1><p class="muted"><?= e(__('reports.intro')) ?></p></div></div>
<div class="report-grid">
  <?php foreach ($types as $key => [$label, $help]): ?>
    <a class="card report-card" href="<?= e(url('/portal/reports/' . $key)) ?>"><?= icon('chart') ?><h2><?= e(__($label)) ?></h2><p class="muted"><?= e(__($help)) ?></p></a>
  <?php endforeach; ?>
</div>
