<?php $p = $r['period']; ?>
<h1 class="doc-title"><?= e($title) ?></h1>
<p class="doc-meta"><?= empty($r['no_period']) ? e(fmt_date($p['from']) . ' → ' . fmt_date($p['to'])) . ' · ' : '' ?><?= e(__('common.printed_on')) ?>: <?= e(date('d M Y H:i')) ?><?php if (\App\Core\Auth::user()): ?> · <?= e(\App\Core\Auth::user()['name']) ?><?php endif; ?></p>
<?php if ($r['cards']): ?>
<table class="doc-table doc-cards"><tr><?php foreach ($r['cards'] as [$label, $amount]): ?><td><small><?= e($label) ?></small><br><b class="<?= $amount < 0 ? 'neg' : '' ?>"><?= e(number_format((float) $amount, 2)) ?> QAR</b></td><?php endforeach; ?></tr></table>
<?php endif; ?>
<?php foreach ($r['sections'] as $s): ?>
  <h2 class="doc-sub"><?= e($s['title']) ?></h2>
  <?= \App\Core\View::partial('portal/reports/table', ['s' => $s, 'tableClass' => 'doc-table']) ?>
<?php endforeach; ?>
