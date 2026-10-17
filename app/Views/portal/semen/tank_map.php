<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('tanks.map_intro')) ?></p></div></div>
<?php foreach ($tanks as $tank): ?>
  <section class="card">
    <div class="card-head">
      <h2><a href="<?= e(url('/portal/tanks/' . $tank['id'])) ?>"><?= e($tank['name']) ?></a><?= $tank['location'] ? ' · ' . e($tank['location']) : '' ?></h2>
    </div>
    <?php if (!$tank['canisters']): ?><p class="muted"><?= e(__('canisters.none')) ?></p><?php endif; ?>
    <div class="grid-3">
      <?php foreach ($tank['canisters'] as $can): ?>
        <div class="card">
          <h3><?= e($can['name']) ?></h3>
          <?php if ($can['stock']): ?>
            <ul class="alert-list">
              <?php foreach ($can['stock'] as $s): ?>
                <li><a href="<?= e(url('/portal/semen-stock/' . $s['id'])) ?>"><?= e($s['stallion'] ?: '—') ?><?= $s['batch_label'] ? ' · ' . e($s['batch_label']) : '' ?></a><span class="pill"><?= (int) $s['straws'] ?> <?= e(__('semen_stock.straws_short')) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?><p class="muted small"><?= e(__('semen_stock.canister_empty')) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php if (!$tanks): ?><?= \App\Core\View::partial('portal/partials/empty', ['icon' => 'flask', 'message' => __('tanks.none'), 'cta' => \App\Core\Auth::can('semen_stock', 'edit') ? ['href' => '/portal/tanks/create', 'label' => __('common.new')] : null]) ?><?php endif; ?>
