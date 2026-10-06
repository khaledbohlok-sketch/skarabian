<?php
/** 5-generation pedigree (parents → great-great-grandparents), links to every horse record. */
if (!$tree) { return; }
$cols = [];
$level = [[$tree['sire'] ?? null, 's'], [$tree['dam'] ?? null, 'd']];
for ($g = 0; $g < 4; $g++) {
    $cols[] = $level;
    $next = [];
    foreach ($level as [$n, $p]) { $next[] = [$n['sire'] ?? null, $p . 's']; $next[] = [$n['dam'] ?? null, $p . 'd']; }
    $level = $next;
}
$titles = ['horses.gen_parents', 'horses.gen_grandparents', 'horses.gen_great', 'horses.gen_great2'];
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('horses.pedigree')) ?></h2><a class="btn btn-sm" href="<?= e(url('/portal/studio/new/profile?record_id=' . (int) $tree['id'])) ?>"><?= icon('print') ?> <?= e(__('studio.type_profile')) ?></a></div>
  <div style="overflow-x:auto"><div class="pedigree-tree" style="grid-template-rows:repeat(16,minmax(30px,auto))">
    <?php foreach ($cols as $g => $nodes): $span = 16 / count($nodes); foreach ($nodes as $i => [$n, $p]): ?>
      <div class="p <?= substr($p, -1) === 'd' ? 'd' : '' ?>" style="grid-column:<?= $g + 1 ?>;grid-row:<?= (int) ($i * $span + 1) ?> / span <?= (int) $span ?>">
        <?php if ($g === 0): ?><small><?= e(__(substr($p, -1) === 's' ? 'horse.sire' : 'horse.dam')) ?></small><?php endif; ?>
        <?php if ($n): ?><a href="<?= e(url('/portal/horses/' . (int) $n['id'])) ?>"><?= e($n['name_en']) ?></a><?php if (!empty($n['is_external'])): ?> <small>(<?= e(__('horses.ext')) ?>)</small><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?>
      </div>
    <?php endforeach; endforeach; ?>
  </div></div>
  <p class="small muted"><?= e(__('horses.pedigree_help')) ?></p>
</section>
