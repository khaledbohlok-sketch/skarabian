<div class="page-head"><h1><?= e(__('auth.change_password')) ?></h1></div>
<?php if ($forced): ?><div class="alert alert-info"><?= e(__('auth.must_change')) ?></div><?php endif; ?>
<form method="post" class="card form-narrow form-stack">
  <?= csrf_field() ?>
  <label><?= e(__('auth.current_password')) ?><input type="password" name="current_password" required autocomplete="current-password"></label>
  <label><?= e(__('auth.new_password')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label><?= e(__('auth.confirm_password')) ?><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
  <p class="small muted"><?= e(__('auth.pw_rules')) ?></p>
  <button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button>
</form>
