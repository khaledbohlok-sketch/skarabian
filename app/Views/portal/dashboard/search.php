<div class="page-head"><h1><?= e(__('common.search')) ?><?= $q !== '' ? ': “' . e($q) . '”' : '' ?></h1></div>
<?php if ($q === '' || mb_strlen($q) < 2): ?><div class="card muted"><?= e(__('search.min_chars')) ?></div>
<?php elseif (!$groups): ?><?= \App\Core\View::partial('portal/partials/empty', ['icon' => 'search', 'message' => __('common.no_records')]) ?>
<?php else: foreach ($groups as $label => $items): ?>
  <section class="card"><h2><?= e(__($label)) ?> <span class="muted">(<?= count($items) ?>)</span></h2>
    <ul class="alert-list"><?php foreach ($items as [$u, $t, $s]): ?><li><a href="<?= e(url($u)) ?>"><?= e($t) ?></a><span class="muted small"><?= e($s) ?></span></li><?php endforeach; ?></ul></section>
<?php endforeach; endif; ?>
