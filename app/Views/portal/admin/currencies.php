<div class="page-head"><div><h1><?= e(__('nav.currencies')) ?></h1><p class="muted"><?= e(__('currencies.intro')) ?></p></div></div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('currencies.code')) ?></th><th><?= e(__('currencies.name')) ?></th><th><?= e(__('currencies.rate')) ?></th><th><?= e(__('currencies.auto')) ?></th><th><?= e(__('common.active')) ?></th><th><?= e(__('currencies.updated')) ?></th></tr></thead>
    <tbody><?php foreach ($rows as $c): $q = $c['code'] === 'QAR'; ?><tr>
      <td><b><?= e($c['code']) ?></b></td><td><?= e(loc($c, 'name')) ?></td>
      <td><?php if ($q): ?>1<?php else: ?><input class="num-input" type="text" inputmode="decimal" dir="ltr" name="cur[<?= e($c['code']) ?>][rate]" value="<?= e(rtrim(rtrim((string) $c['rate_to_qar'], '0'), '.')) ?>"<?= $canEdit ? '' : ' disabled' ?>> QAR<?= $c['manual_override'] ? ' <span class="badge">' . e(__('currencies.manual')) . '</span>' : '' ?><?php endif; ?></td>
      <td><?php if (!$q): ?><input type="checkbox" name="cur[<?= e($c['code']) ?>][auto]" value="1"<?= checked($c['auto_update']) ?><?= $canEdit ? '' : ' disabled' ?>><?php endif; ?></td>
      <td><?php if (!$q): ?><input type="checkbox" name="cur[<?= e($c['code']) ?>][active]" value="1"<?= checked($c['active']) ?><?= $canEdit ? '' : ' disabled' ?>><?php else: ?>✓<?php endif; ?></td>
      <td><?= e(fmt_date((string) $c['updated_at'], true)) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <p class="muted small"><?= e(__('currencies.help')) ?></p>
  <?php if ($canEdit): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div><?php endif; ?>
</form>
<section class="card"><h2><?= e(__('currencies.history')) ?></h2>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('currencies.code')) ?></th><th><?= e(__('currencies.rate')) ?></th><th><?= e(__('currencies.source')) ?></th></tr></thead><tbody>
  <?php foreach ($history as $h): ?><tr><td><?= e(fmt_date($h['created_at'], true)) ?></td><td><?= e($h['code']) ?></td><td dir="ltr"><?= e(rtrim(rtrim((string) $h['rate_to_qar'], '0'), '.')) ?></td><td><?= e($h['source']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
