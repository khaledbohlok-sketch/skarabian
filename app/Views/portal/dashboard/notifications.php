<div class="page-head"><h1><?= e(__('nav.notifications')) ?></h1></div>
<section class="card">
<?php if ($rows): ?><ul class="alert-list"><?php foreach ($rows as $n): ?>
  <li><span><?php if ($n['url']): ?><a href="<?= e(url($n['url'])) ?>"><b><?= e($n['title']) ?></b></a><?php else: ?><b><?= e($n['title']) ?></b><?php endif; ?><?= $n['body'] ? '<br><small class="muted">' . e($n['body']) . '</small>' : '' ?></span><span class="muted small"><?= e(fmt_date($n['created_at'], true)) ?><?= !$n['read_at'] ? ' <span class="pill warn">' . e(__('inbox.status_new')) . '</span>' : '' ?></span></li>
<?php endforeach; ?></ul><?php else: ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
</section>
