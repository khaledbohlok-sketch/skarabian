<form method="post" action="<?= e(url('/portal/login/2fa')) ?>" class="form-stack">
  <?= csrf_field() ?>
  <p><?= e(__($method === 'email' ? 'auth.twofa_email_prompt' : 'auth.twofa_app_prompt')) ?></p>
  <label><?= e(__('auth.code')) ?>
    <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus class="code-input">
  </label>
  <button class="btn btn-primary btn-block" type="submit"><?= e(__('auth.verify')) ?></button>
</form>
<?php if ($method === 'email'): ?>
<form method="post" action="<?= e(url('/portal/login/2fa/resend')) ?>" class="center"><?= csrf_field() ?><button class="linklike" type="submit"><?= e(__('auth.resend_code')) ?></button></form>
<?php endif; ?>
