<div class="page-head"><div>
  <?php if (!$forced): ?><a class="crumb" href="<?= e(url('/portal/account')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('account.title')) ?></a><?php endif; ?>
  <h1><?= e(__('auth.change_password')) ?></h1><p class="muted"><?= e(__('auth.pw_rules')) ?></p>
</div></div>
<?php if ($forced): ?><div class="alert alert-info"><?= e(__('auth.must_change')) ?></div><?php endif; ?>
<form method="post" class="card record-form form-md" data-autosave="account-password">
  <?= csrf_field() ?>
  <div class="grid-fields">
    <div class="field col-12"><label for="f_cur"><?= e(__('auth.current_password')) ?> <span class="req">*</span></label><input id="f_cur" type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="field col-6"><label for="f_new"><?= e(__('auth.new_password')) ?> <span class="req">*</span></label><input id="f_new" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
    <div class="field col-6"><label for="f_new2"><?= e(__('auth.confirm_password')) ?> <span class="req">*</span></label><input id="f_new2" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></div>
  </div>
  <div class="form-actions">
    <?php if (!$forced): ?><a class="btn ghost" href="<?= e(url('/portal/account')) ?>"><?= e(__('common.cancel')) ?></a><?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button>
  </div>
</form>
