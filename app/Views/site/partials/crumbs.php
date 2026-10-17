<?php
/** Breadcrumbs, the same everywhere: Home first, then $items = [[label, url|null], ...] (the last one is this page). */
$items = array_merge([[__('site.home'), site_url()]], $items ?? []);
$last = count($items) - 1;
?>
<nav class="crumbs" aria-label="<?= e(__('site.breadcrumb')) ?>"><ol>
<?php foreach ($items as $i => [$label, $href]): ?>
  <li><?php if ($href && $i < $last): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?><?php if ($i < $last): ?><span class="sep" aria-hidden="true">/</span><?php endif; ?></li>
<?php endforeach; ?>
</ol></nav>
