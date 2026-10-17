<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('semen_stock.count_intro')) ?></p></div></div>
<form method="post" action="<?= e(url('/portal/semen-stock/count')) ?>" class="card record-form" data-autosave="semen-count" data-confirm="<?= e(__('semen_stock.count_confirm')) ?>">
  <?= csrf_field() ?>
  <div class="grid-fields">
    <div class="field col-4"><label for="count_date"><?= e(__('semen_stock.report_date')) ?></label><input id="count_date" type="date" name="count_date" value="<?= e($date) ?>" required></div>
  </div>
  <div class="table-wrap">
    <table class="table compact">
      <thead><tr><th><?= e(__('semen_stock.stallion')) ?></th><th><?= e(__('tanks.singular')) ?></th><th><?= e(__('canisters.singular')) ?></th><th class="num"><?= e(__('semen_stock.system_count')) ?></th><th class="num"><?= e(__('semen_stock.counted')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['stallion'] ?: '—') ?><?= $r['batch_label'] ? ' · ' . e($r['batch_label']) : '' ?></td>
          <td><?= e($r['tank']) ?></td>
          <td><?= e($r['canister']) ?></td>
          <td class="num"><?= (int) $r['straws'] ?></td>
          <td class="num"><input type="number" min="0" name="counted[<?= (int) $r['id'] ?>]" placeholder="<?= (int) $r['straws'] ?>" class="num-input"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$rows): ?><p class="muted"><?= e(__('common.no_records')) ?></p><?php endif; ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('semen_stock.count_submit')) ?></button></div>
</form>
