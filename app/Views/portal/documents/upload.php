<?php
use App\Resources\Documents;
?>
<h2><?= e($title) ?></h2>
<form id="doc-upload-form" method="post" action="<?= e(url('/portal/documents/upload')) ?>" enctype="multipart/form-data"
      data-msg-none="<?= e(__('files.none_selected')) ?>" data-msg-error="<?= e(__('common.error_text')) ?>" data-msg-expired="<?= e(__('documents.upload_expired')) ?>">
  <?= csrf_field() ?>
  <div class="dropzone" data-dropzone>
    <p><?= icon('upload') ?><br><?= e(__('documents.drop_here')) ?></p>
    <label class="btn file-btn"><?= e(__('documents.choose_files')) ?><input type="file" name="files[]" id="doc-upload-input" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label>
  </div>
  <ul class="doc-upload-list" data-file-list></ul>

  <div class="grid-fields">
    <div class="field col-6">
      <label for="d_category"><?= e(__('documents.category')) ?></label>
      <select id="d_category" name="category_id">
        <option value=""><?= e(__('common.choose')) ?></option>
        <?php foreach (Documents::categoryOptions() as $id => $label): ?><option value="<?= (int) $id ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field col-6">
      <label for="d_date"><?= e(__('documents.doc_date')) ?></label>
      <input id="d_date" type="date" name="doc_date">
    </div>
    <div class="field col-12">
      <label for="d_title"><?= e(__('documents.shared_title_help')) ?></label>
      <input id="d_title" type="text" name="title" maxlength="200" placeholder="<?= e(__('documents.shared_title_placeholder')) ?>">
    </div>
    <div class="field col-12">
      <label for="d_notes"><?= e(__('common.notes')) ?></label>
      <textarea id="d_notes" name="notes" rows="3"></textarea>
    </div>
  </div>

  <div class="sk-field-error-box" data-upload-errors hidden></div>
  <div class="upload-progress" data-progress hidden>
    <div class="upload-progress-bar" data-progress-bar></div>
  </div>
  <p class="help"><?= e(__('documents.max_size_help', ['mb' => (int) \App\Core\Config::get('documents.max_upload_mb', 10)])) ?></p>

  <div class="f-buttons">
    <button class="btn btn-primary" type="submit" data-submit><?= icon('upload') ?> <?= e(__('documents.upload_button')) ?></button>
  </div>
</form>
