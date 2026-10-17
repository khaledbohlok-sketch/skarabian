<?php $mailVerified ??= null; $offsite ??= ['dest' => null, 'last' => null, 'late' => false]; ?>
<div class="page-head"><div><h1><?= e(__('nav.settings')) ?></h1><p class="muted"><?= e(__('settings.intro')) ?></p></div></div>
<?php if ($pendingDb): ?>
<div class="alert alert-warning db-update" id="database-update"><div><strong><?= e(__('settings.db_pending', ['n' => $pendingDb])) ?></strong> <?= e(__('settings.db_pending_help')) ?></div>
  <form method="post" action="<?= e(url('/portal/settings/database-update')) ?>" data-confirm="<?= e(__('settings.db_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?> <?= e(__('settings.db_apply')) ?></button></form>
</div>
<?php endif; ?>
<form method="post" class="card record-form" data-autosave="settings">
  <?= csrf_field() ?>
  <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => !$canEdit, 'locked' => $locked ?? []]) ?></div>
  <?php if ($canEdit): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div><?php endif; ?>
</form>
<section class="card"><h2><?= e(__('settings.connections')) ?></h2>
  <ul class="alert-list">
    <li><span><?= e(__('settings.mail')) ?><?= $mailVerified ? ' <small class="muted">· ' . e(__('settings.mail_verified', ['date' => fmt_date($mailVerified, true)])) . '</small>' : '' ?></span><?php $mail = in_array($mail, ['smtp', 'mail'], true) ? $mail : 'log'; ?><span class="pill <?= $mail === 'log' ? 'warn' : 'ok' ?>"><?= e(__('settings.mail_' . $mail)) ?></span></li>
    <li><span><a href="<?= e(url('/portal/settings/whatsapp')) ?>"><?= e(__('settings.whatsapp')) ?></a></span><span class="pill <?= $whatsapp ? 'ok' : 'warn' ?>"><?= e(__($whatsapp ? 'settings.connected' : 'settings.not_connected')) ?></span></li>
    <?php $aiOn = \App\Services\Ai::enabled(); ?>
    <li><span><?php if ($isOwner): ?><a href="<?= e(url('/portal/settings/ai')) ?>"><?= e(__('ai.title')) ?></a><?php else: ?><?= e(__('ai.title')) ?><?php endif; ?></span><span class="pill <?= $aiOn ? 'ok' : 'warn' ?>"><?= e(__($aiOn ? 'ai.on' : 'ai.off')) ?></span></li>
    <?php // where the off-server copy of each backup really goes, and when the last one succeeded ?>
    <li><span><a href="<?= e(url('/portal/settings/backups')) ?>"><?= e(__('settings.offsite')) ?></a><?= $offsite['dest'] ? ' <small class="muted">· ' . e($offsite['dest']) . '</small>' : '' ?></span>
      <span class="pill <?= !$offsite['dest'] ? 'warn' : ($offsite['late'] ? 'bad' : 'ok') ?>"><?= e(!$offsite['dest'] ? __('settings.offsite_none') : ($offsite['late'] ? __('settings.offsite_late') : __('settings.offsite_last', ['date' => fmt_date((string) $offsite['last'], true)]))) ?></span></li>
  </ul>
  <p class="muted small"><?= e(__('settings.connections_help')) ?></p>
  <?php if ($isOwner): ?>
  <form method="post" action="<?= e(url('/portal/settings/test-email')) ?>" class="test-mail">
    <?= csrf_field() ?>
    <label for="test-mail-to"><?= e(__('settings.test_mail')) ?></label>
    <div class="form-inline"><input id="test-mail-to" type="email" name="to" value="<?= e($ownerEmail) ?>" required maxlength="190" autocomplete="email" dir="ltr">
      <button class="btn btn-sm" type="submit"><?= icon('mail') ?> <?= e(__('settings.test_mail_send')) ?></button></div>
    <p class="muted small"><?= e(__('settings.test_mail_help')) ?></p>
  </form>
  <?php endif; ?>
</section>
<section class="card"><h2><?= e(__('cron.title')) ?></h2>
  <?php if (!$cronLast || strtotime($cronLast) < time() - 7200): ?><div class="alert alert-warning"><?= e(__('cron.not_running')) ?></div><?php endif; ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('cron.task')) ?></th><th><?= e(__('cron.last_run')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('migration.details')) ?></th></tr></thead><tbody>
  <?php foreach ($cron as $c): ?><tr><td><?= e(__('cron.t_' . $c['task'])) ?></td><td class="nowrap"><?= e(isset($c['started_at']) ? fmt_date($c['started_at'], true) : '—') ?></td>
    <td><?php if (isset($c['ok'])): ?><span class="pill <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= e(__($c['ok'] ? 'cron.ok' : 'cron.failed_short')) ?></span><?php endif; ?></td><td class="muted small"><?= e(mb_strimwidth((string) ($c['message'] ?? ''), 0, 120, '…')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="muted small"><?= e(__('cron.help')) ?></p>
</section>
