<div class="page-head"><h1><?= e(__('auth.twofa_setup')) ?></h1></div>
<?php if ($required && $user['twofa_method'] === 'none'): ?><div class="alert alert-info"><?= e(__('auth.twofa_required_role')) ?></div><?php endif; ?>
<?php if ($user['twofa_method'] !== 'none'): ?>
  <div class="card"><p><?= e(__('auth.twofa_active', ['method' => __('auth.method_' . $user['twofa_method'])])) ?></p>
  <?php if (!$required): ?>
    <form method="post" class="form-inline"><?= csrf_field() ?><input type="hidden" name="action" value="disable">
      <input type="password" name="current_password" placeholder="<?= e(__('auth.current_password')) ?>" required>
      <button class="btn btn-danger" type="submit"><?= e(__('auth.twofa_disable')) ?></button></form>
  <?php endif; ?>
  </div>
<?php endif; ?>
<div class="grid-2">
  <section class="card">
    <h2><?= e(__('auth.method_totp')) ?></h2>
    <ol class="steps"><li><?= e(__('auth.totp_step1')) ?></li><li><?= e(__('auth.totp_step2')) ?></li><li><?= e(__('auth.totp_step3')) ?></li></ol>
    <div class="qr-box"><?= $qr ?></div>
    <p class="small"><?= e(__('auth.manual_key')) ?>: <code class="secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
    <form method="post" class="form-inline"><?= csrf_field() ?><input type="hidden" name="action" value="totp">
      <input type="text" name="code" inputmode="numeric" maxlength="7" placeholder="123456" required class="code-input" autocomplete="one-time-code">
      <button class="btn btn-primary" type="submit"><?= e(__('auth.activate')) ?></button></form>
  </section>
  <section class="card">
    <h2><?= e(__('auth.method_email')) ?></h2>
    <p><?= e(__('auth.email_method_text', ['email' => $user['email']])) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email_send"><button class="btn" type="submit"><?= e(__('auth.send_code')) ?></button></form>
    <?php if (($_GET['m'] ?? '') === 'email'): ?>
    <form method="post" class="form-inline mt"><?= csrf_field() ?><input type="hidden" name="action" value="email">
      <input type="text" name="code" inputmode="numeric" maxlength="7" placeholder="123456" required class="code-input">
      <button class="btn btn-primary" type="submit"><?= e(__('auth.activate')) ?></button></form>
    <?php endif; ?>
  </section>
</div>
