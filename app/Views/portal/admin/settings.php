<div class="page-head"><div><h1><?= e(__('nav.settings')) ?></h1><p class="muted"><?= e(__('settings.intro')) ?></p></div></div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => !$canEdit]) ?></div>
  <?php if ($canEdit): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div><?php endif; ?>
</form>
<section class="card"><h2><?= e(__('settings.connections')) ?></h2>
  <ul class="alert-list">
    <li><span><?= e(__('settings.mail')) ?></span><span class="pill <?= $mail === 'log' ? 'warn' : 'ok' ?>"><?= e($mail) ?></span></li>
    <li><span><?= e(__('settings.whatsapp')) ?></span><span class="pill <?= $whatsapp ? 'ok' : 'warn' ?>"><?= e(__($whatsapp ? 'settings.connected' : 'settings.not_connected')) ?></span></li>
    <li><span><?= e(__('settings.drive')) ?></span><span class="pill <?= $drive ? 'ok' : 'warn' ?>"><?= e(__($drive ? 'settings.connected' : 'settings.not_connected')) ?></span></li>
  </ul>
  <p class="muted small"><?= e(__('settings.connections_help')) ?></p>
</section>
<section class="card"><h2><?= e(__('cron.title')) ?></h2>
  <?php if (!$cronLast || strtotime($cronLast) < time() - 7200): ?><div class="alert alert-warning"><?= e(__('cron.not_running')) ?></div><?php endif; ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('cron.task')) ?></th><th><?= e(__('cron.last_run')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('migration.details')) ?></th></tr></thead><tbody>
  <?php foreach ($cron as $c): ?><tr><td><?= e(__('cron.t_' . $c['task'])) ?></td><td class="nowrap"><?= e(isset($c['started_at']) ? fmt_date($c['started_at'], true) : '—') ?></td>
    <td><?php if (isset($c['ok'])): ?><span class="pill <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= e(__($c['ok'] ? 'cron.ok' : 'cron.failed_short')) ?></span><?php endif; ?></td><td class="muted small"><?= e(mb_strimwidth((string) ($c['message'] ?? ''), 0, 120, '…')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="muted small"><?= e(__('cron.help')) ?></p>
</section>
