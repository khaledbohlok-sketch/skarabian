<form method="post" action="<?= e(url('/portal/password-reset/' . $token)) ?>" class="form-stack" autocomplete="off">
  <?= csrf_field() ?>
  <p><?= e(__('auth.reset_intro', ['name' => $name])) ?></p>
  <label><?= e(__('auth.new_password')) ?>
    <span class="pw-field"><input type="password" name="password" required autocomplete="new-password"></span>
  </label>
  <label><?= e(__('auth.confirm_password')) ?>
    <span class="pw-field"><input type="password" name="password_confirmation" required autocomplete="new-password"></span>
  </label>
  <p class="small muted"><?= e(__('auth.pw_rules')) ?></p>
  <button class="btn btn-primary btn-block" type="submit"><?= e(__('auth.reset_submit')) ?></button>
  <p class="small muted"><?= e(__('auth.reset_2fa_note')) ?></p>
</form>
