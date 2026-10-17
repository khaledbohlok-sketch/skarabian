<?php
/** Administration → System errors (Owner): the private error log, newest first; one entry opens with its trace. */
use App\Services\ErrorLog;

$what = fn (array $e) => mb_strimwidth(preg_replace('/ @ \S+:\d+$/', '', $e['head']), 0, 160, '…');
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('errors.intro')) ?></p></div>
  <form method="get" class="inline errors-find"><label class="sr-only" for="err-ref"><?= e(__('errors.find')) ?></label>
    <input id="err-ref" type="search" name="ref" value="<?= e($ref) ?>" placeholder="<?= e(__('errors.find')) ?>" dir="ltr" maxlength="8"><button class="btn" type="submit"><?= icon('search') ?></button></form></div>

<?php if ($ref !== '' && !$one): ?><div class="alert alert-warning"><?= e(__('errors.not_found', ['ref' => $ref])) ?></div><?php endif; ?>
<?php if ($one): ?>
<section class="card error-one">
  <h2><?= e(__('common.reference')) ?> <span dir="ltr"><?= e($one['ref']) ?></span>
    <span class="pill <?= $one['kept_going'] ? 'warn' : 'bad' ?>"><?= e(__($one['kept_going'] ? 'errors.kind_warning' : 'errors.kind_error')) ?></span></h2>
  <dl class="kv-list">
    <div><dt><?= e(__('errors.time')) ?></dt><dd><?= e(fmt_date(substr($one['time'], 0, 19), true)) ?></dd></div>
    <?php if (!empty($one['ctx']['page'])): ?><div><dt><?= e(__('errors.page')) ?></dt><dd dir="ltr"><?= e(($one['ctx']['method'] ?? '') . ' ' . $one['ctx']['page']) ?></dd></div><?php endif; ?>
    <?php if ($u = ErrorLog::userName($one)): ?><div><dt><?= e(__('errors.user')) ?></dt><dd><?= e($u) ?></dd></div><?php endif; ?>
    <div><dt><?= e(__('errors.where')) ?></dt><dd dir="ltr"><?= e(ErrorLog::where($one)) ?></dd></div>
  </dl>
  <pre class="error-text" dir="ltr"><?= e($one['head'] . "\n" . implode("\n", $one['trace'])) ?></pre>
  <p class="muted small"><?= e(__('errors.send_hint')) ?></p>
</section>
<?php endif; ?>

<section class="card">
  <h2><?= e(__('errors.latest')) ?> <span class="muted">(<?= (int) $total ?>)</span></h2>
  <?php if (!$entries): ?><p><span class="pill ok"><?= e(__('errors.none')) ?></span></p>
  <?php else: ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('errors.time')) ?></th><th><?= e(__('common.reference')) ?></th><th><?= e(__('errors.what')) ?></th><th><?= e(__('errors.page')) ?></th><th><?= e(__('errors.user')) ?></th></tr></thead>
    <tbody><?php foreach ($entries as $e): ?>
      <tr<?= $e['kept_going'] ? '' : ' class="row-alert"' ?>>
        <td class="nowrap" data-label="<?= e(__('errors.time')) ?>"><?= e(fmt_date(substr($e['time'], 0, 19), true)) ?></td>
        <td data-label="<?= e(__('common.reference')) ?>"><a href="<?= e(url('/portal/settings/errors?ref=' . $e['ref'])) ?>" dir="ltr"><?= e($e['ref']) ?></a></td>
        <td data-label="<?= e(__('errors.what')) ?>"><div class="err-what"><span class="pill <?= $e['kept_going'] ? 'warn' : 'bad' ?>"><?= e(__($e['kept_going'] ? 'errors.short_warning' : 'errors.short_error')) ?></span> <span dir="ltr"><?= e($what($e)) ?></span>
          <small class="muted block" dir="ltr"><?= e(ErrorLog::where($e)) ?></small></div></td>
        <td data-label="<?= e(__('errors.page')) ?>" dir="ltr"><?= e(mb_strimwidth((string) ($e['ctx']['page'] ?? '—'), 0, 60, '…')) ?></td>
        <td data-label="<?= e(__('errors.user')) ?>"><?= e(ErrorLog::userName($e) ?: '—') ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($php): ?>
<section class="card">
  <h2><?= e(__('errors.php_title')) ?></h2>
  <p class="muted small"><?= e(__('errors.php_help')) ?></p>
  <pre class="error-text" dir="ltr"><?= e(implode("\n", $php)) ?></pre>
</section>
<?php endif; ?>

<p class="muted small errors-server"><?= e(__('errors.server')) ?> <span dir="ltr">PHP <?= e($server['php']) ?> · DB <?= e($server['db']) ?> · SQL mode: <?= e($server['mode']) ?></span></p>
