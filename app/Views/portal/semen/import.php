<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('semen_stock.import_intro')) ?></p></div></div>
<form method="post" action="<?= e(url('/portal/semen-stock/import')) ?>" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="field col-6">
    <label for="import_file"><?= e(__('semen_stock.import_file')) ?></label>
    <input id="import_file" type="file" name="file" accept=".csv,text/csv" required>
    <small class="help"><?= e(__('semen_stock.import_file_help')) ?></small>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(__('semen_stock.import_button')) ?></button></div>
</form>
<section class="card">
  <h2><?= e(__('semen_stock.import_columns_title')) ?></h2>
  <p class="muted small"><?= e(__('semen_stock.import_columns_help')) ?></p>
  <code>Stallion, Tank, Canister, Straws, Straws per Dose, Report Date, Straw Size, Straw Color, Owner, Batch, Notes</code>
</section>
