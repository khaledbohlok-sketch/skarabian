<form method="post" action="<?= e(url('/portal/login')) ?>" class="form-stack" autocomplete="on">
  <?= csrf_field() ?>
  <label><?= e(__('auth.username_or_email')) ?>
    <input type="text" name="login" value="<?= e($login) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false">
  </label>
  <label><?= e(__('auth.password')) ?>
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <?php if ($captcha): ?>
    <div class="captcha">
      <img src="<?= e(url('/portal/captcha')) ?>?t=<?= time() ?>" alt="<?= e(__('auth.captcha')) ?>" width="170" height="56" data-captcha>
      <button type="button" class="linklike" data-captcha-refresh><?= e(__('auth.new_code')) ?></button>
    </div>
    <label><?= e(__('auth.captcha_enter')) ?>
      <input type="text" name="captcha" required autocomplete="off" autocapitalize="characters" maxlength="5">
    </label>
  <?php endif; ?>
  <button class="btn btn-primary btn-block" type="submit"><?= e(__('auth.login')) ?></button>
  <p class="small muted"><?= e(__('auth.own_account_only')) ?></p>
</form>
