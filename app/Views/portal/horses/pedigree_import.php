<div class="page-head"><div>
  <a class="crumb" href="<?= e(url('/portal/horses/import')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('himport.title')) ?></a>
  <h1><?= e($title) ?></h1><p class="muted"><?= e(__('horses.pedigree_import_intro')) ?></p></div></div>
<form method="post" action="<?= e(url('/portal/horses/pedigree-import')) ?>" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="field col-6">
    <label for="import_file"><?= e(__('horses.pedigree_import_file')) ?></label>
    <input id="import_file" type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
    <small class="help"><?= e(__('horses.pedigree_import_file_help')) ?></small>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(__('horses.pedigree_import_button')) ?></button></div>
</form>
<section class="card">
  <h2><?= e(__('horses.pedigree_import_columns_title')) ?></h2>
  <p class="muted small"><?= e(__('horses.pedigree_import_columns_help')) ?></p>
  <code>Horse, <?= e(implode(', ', array_keys(\App\Services\PedigreeImportService::columns()))) ?></code>
</section>
