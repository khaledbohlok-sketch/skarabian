<?php
/** Users → a user → Reset password: the Owner sets one now (nothing emailed), or a one-time link goes by email. */
$back = '/portal/users/' . (int) $u['id'];
?>
<div class="page-head">
  <div>
    <a class="crumb" href="<?= e(url($back)) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e($u['name']) ?></a>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($u['name']) ?> <span dir="ltr">(<?= e($u['username']) ?>)</span></p>
  </div>
</div>

<div class="grid-2 pw-reset">
  <?php if ($canSet): ?>
  <section class="card">
    <h2><?= e(__('users.reset_set_title')) ?></h2>
    <p class="muted"><?= e(__('users.reset_set_help', ['name' => $u['name']])) ?></p>
    <form method="post" action="<?= e(url($back . '/set-password')) ?>" data-confirm="<?= e(__('users.confirm_set_password', ['name' => $u['name']])) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="field">
        <label for="new-password"><?= e(__('users.new_password')) ?></label>
        <div class="pw-set-row">
          <input id="new-password" type="text" name="password" value="<?= e($suggested) ?>" required minlength="10" maxlength="100" dir="ltr"
                 autocomplete="off" autocapitalize="off" spellcheck="false">
          <a class="btn" href="<?= e(url($back . '/reset-password')) ?>"><?= e(__('users.new_suggestion')) ?></a>
        </div>
        <small class="help"><?= e(__('auth.pw_rules')) ?></small>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('users.set_password_button')) ?></button></div>
    </form>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2><?= e(__('users.reset_email_title')) ?></h2>
    <?php if ($email): ?>
      <p class="muted"><?= e(__('users.reset_email_help', ['name' => $u['name'], 'email' => $email])) ?></p>
      <form method="post" action="<?= e(url($back . '/reset-password')) ?>" data-confirm="<?= e(__('users.confirm_reset_email', ['email' => $email])) ?>">
        <?= csrf_field() ?>
        <div class="form-actions"><button class="btn<?= $canSet ? '' : ' btn-primary' ?>" type="submit"><?= icon('mail') ?> <?= e(__('users.reset_email_button')) ?></button></div>
      </form>
    <?php else: ?>
      <p class="muted"><?= e(__('users.reset_no_address')) ?></p>
    <?php endif; ?>
  </section>
</div>
