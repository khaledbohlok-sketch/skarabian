<div class="error-page"><h1><?= e(__('common.error_title')) ?></h1><p><?= e(__('common.error_text')) ?></p><p class="muted"><?= e(__('common.reference')) ?>: <span dir="ltr"><?= e($ref ?? '') ?></span></p>
<?php if (!empty($detail)): // the Owner only: what went wrong, to tell the developer or look up in System errors ?>
  <div class="error-detail"><b><?= e(__('common.error_owner_detail')) ?></b><code dir="ltr"><?= e($detail) ?></code>
    <a href="<?= e(url('/portal/settings/errors?ref=' . ($ref ?? ''))) ?>"><?= e(__('common.error_owner_link')) ?></a></div>
<?php endif; ?>
<p><a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(__('common.go_home')) ?></a></p></div>
