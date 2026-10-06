<?php if ($done): ?>
  <h2>Installation complete</h2>
  <p>The database is ready and your Owner account was created.</p>
  <ul class="small muted"><?php foreach ($done as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
  <p>Next: log in, set up 2-factor authentication, then add the cron job (see docs/INSTALL.md).</p>
  <p><a class="btn btn-primary btn-block" href="<?= e(url('/portal/login')) ?>">Go to login</a></p>
<?php else: ?>
  <h2>First-time setup</h2>
  <p class="muted small">Creates the database tables and the first Owner account. This page disappears once the Owner exists.</p>
  <?php if ($suggest): ?><div class="alert alert-warning">config/config.php has no app key yet. Put this line in it (app → key), save, keep a safe copy, then reload this page:<br><code dir="ltr" style="user-select:all;word-break:break-all">'key' => '<?= e($suggest) ?>',</code></div><?php endif; ?>
  <?php foreach (['db', 'key'] as $k): if (!empty($errors[$k])): ?><div class="alert alert-error"><?= e($errors[$k]) ?></div><?php endif; endforeach; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field"><label for="app_key">App key (from config/config.php)</label><input type="password" id="app_key" name="app_key" required dir="ltr"></div>
    <div class="field"><label for="name">Your full name</label><input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>"><?php if (!empty($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?></div>
    <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" required dir="ltr" value="<?= e($_POST['username'] ?? '') ?>"><?php if (!empty($errors['username'])): ?><small class="field-error"><?= e($errors['username']) ?></small><?php endif; ?></div>
    <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" required dir="ltr" value="<?= e($_POST['email'] ?? '') ?>"><?php if (!empty($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?></div>
    <div class="field"><label for="password">Password (10+ characters, upper and lower case, a number and a symbol)</label><input type="password" id="password" name="password" required minlength="10"><?php if (!empty($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?></div>
    <button class="btn btn-primary btn-block" type="submit">Install</button>
  </form>
<?php endif; ?>
