<div class="page-head"><div><h1><?= e(__('nav.backups')) ?></h1><p class="muted"><?= e(__('backups.intro')) ?></p></div>
  <div class="actions"><form method="post" action="<?= e(url('/portal/settings/backups/test')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= e(__('backups.test')) ?></button></form>
  <form method="post" action="<?= e(url('/portal/settings/backups/run')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('backups.run')) ?></button></form></div></div>
<?php if (!$drive): ?><div class="alert alert-warning"><?= e(__('backups.no_offsite')) ?></div>
<?php elseif ($offsite['late']): ?><div class="alert alert-error"><?= e(__('backups.offsite_late')) ?><?= $offsite['error'] ? ' — ' . e($offsite['error']) : '' ?></div>
<?php else: ?><div class="alert alert-info"><?= e(__('backups.offsite_ok', ['dest' => (string) $offsite['dest'], 'date' => fmt_date((string) $offsite['last'], true)])) ?></div><?php endif; ?>
<section class="card">
  <h2><?= e(__('settings.drive')) ?> <span class="pill <?= $drive ? 'ok' : 'warn' ?>"><?= e(__($drive ? 'settings.connected' : 'settings.not_connected')) ?></span></h2>
  <p class="muted small"><?= e(__('settings.drive_help')) ?></p>
  <form method="post" action="<?= e(url('/portal/settings/backups/drive')) ?>" enctype="multipart/form-data" class="record-form" data-autosave="backups-drive">
    <?= csrf_field() ?>
    <div class="grid-fields">
      <div class="field col-6"><label for="gdrive_folder_id"><?= e(__('settings.drive_folder_id')) ?></label>
        <input id="gdrive_folder_id" type="text" name="gdrive_folder_id" value="<?= e($folderId) ?>" dir="ltr">
        <small class="help"><?= e(__('settings.drive_folder_id_help')) ?></small></div>
      <div class="field col-6"><label for="gdrive_json"><?= e(__('settings.drive_json')) ?></label>
        <input id="gdrive_json" type="file" name="gdrive_json" accept="application/json,.json">
        <small class="help"><?= e(__($hasKey ? 'settings.drive_json_set_help' : 'settings.drive_json_help')) ?></small></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
  </form>
</section>
<section class="card">
  <div class="card-head"><h2><?= e(__('backups.file')) ?></h2><?php if ($files): ?><span class="muted small"><?= e(plural(count($files), 'count.file')) ?></span><?php endif; ?></div>
  <?php if ($files): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('backups.file')) ?></th><th class="num"><?= e(__('backups.size')) ?></th><th class="nowrap"><?= e(__('common.date')) ?></th><th><span class="sr-only"><?= e(__('common.download')) ?></span></th></tr></thead>
    <tbody><?php foreach ($files as $f): ?><tr><td class="is-title" data-label="<?= e(__('backups.file')) ?>" dir="ltr"><?= e($f['name']) ?></td><td class="num" data-label="<?= e(__('backups.size')) ?>"><?= e(number_format($f['size'] / 1048576, 2)) ?> MB</td><td class="nowrap" data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date(date('Y-m-d H:i:s', $f['time']), true)) ?></td>
      <td class="doc-row-actions"><a class="btn btn-sm" href="<?= e(url('/portal/settings/backups/' . $f['name'])) ?>"><?= icon('download') ?> <?= e(__('common.download')) ?></a></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?>
    <?= \App\Core\View::partial('portal/partials/empty', ['card' => false, 'icon' => 'shield', 'message' => __('backups.none')]) ?>
  <?php endif; ?>
</section>
