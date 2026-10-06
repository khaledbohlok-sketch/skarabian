<?php use App\Services\StudioDocs; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/studio')) ?>">← <?= e(__('nav.studio')) ?></a><h1><?= e(__('studio.permissions')) ?></h1><p class="muted"><?= e(__('studio.permissions_intro')) ?></p></div></div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table perm-matrix">
    <thead><tr><th><?= e(__('studio.type')) ?></th><?php foreach ($roles as $r): ?><th class="c"><?= e(loc($r, 'name')) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach (array_keys(StudioDocs::TYPES) as $t): ?><tr>
      <td><b><?= e(__('studio.type_' . $t)) ?></b></td>
      <?php foreach ($roles as $r): ?><td class="c"><input type="checkbox" name="perm[<?= (int) $r['id'] ?>][<?= e($t) ?>]" value="1"<?= checked(!empty($have[$r['id']][$t])) ?> aria-label="<?= e(loc($r, 'name') . ' — ' . __('studio.type_' . $t)) ?>"></td><?php endforeach; ?>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <p class="muted small"><?= e(__('studio.permissions_note')) ?></p>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
