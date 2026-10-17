<?php if (!$tree) { return; } ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('horses.pedigree')) ?></h2>
    <div class="actions">
      <?php if (\App\Core\Auth::can('horses', 'edit')): ?><a class="btn btn-sm" href="<?= e(url('/portal/horses/' . (int) $h['id'] . '/pedigree-edit')) ?>"><?= e(__('horses.pedigree_edit_link')) ?></a><?php endif; ?>
      <a class="btn btn-sm" href="<?= e(url('/portal/studio/new/profile?record_id=' . (int) $h['id'])) ?>"><?= icon('print') ?> <?= e(__('studio.type_profile')) ?></a>
    </div>
  </div>
  <?= \App\Core\View::partial('portal/horses/pedigree_chart', ['h' => $h, 'tree' => $tree, 'mode' => 'readonly', 'gens' => 3]) ?>
  <p class="small muted"><?= e(__('horses.pedigree_help')) ?> <a href="<?= e(url('/portal/horses/' . (int) $h['id'] . '/pedigree')) ?>"><?= e(__('horses.pedigree_full_link')) ?></a></p>
</section>
