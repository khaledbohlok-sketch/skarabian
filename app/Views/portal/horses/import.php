<?php
/** Horses → Import from Excel: the template, the upload, then exactly what the import will do — nothing is saved before "Import now". */
use App\Services\HorseImport;

$p = $preview;
$t = $p['totals'] ?? null;
$pills = ['new' => 'ok', 'update' => 'info', 'same' => '', 'skip' => 'bad'];
$lang = \App\Core\Lang::current();
$other = $lang === 'ar' ? 'en' : 'ar';
$work = $t && ($t['new'] + $t['update']) > 0;
?>
<div class="page-head">
  <div>
    <a class="crumb" href="<?= e(url('/portal/horses')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.horses')) ?></a>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e(__('himport.intro')) ?></p>
  </div>
  <?php if ($canPedigree): ?>
  <div class="actions"><a class="btn" href="<?= e(url('/portal/horses/pedigree-import')) ?>"><?= icon('upload') ?> <?= e(__('himport.pedigree_link')) ?></a></div>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($result): $rt = $result['totals']; ?>
<section class="card himport-result">
  <h2><?= e(__('himport.done_title')) ?></h2>
  <p><?= e(__('himport.done', ['new' => (int) $rt['new'], 'update' => (int) $rt['update'], 'ancestors' => (int) $rt['ancestors'], 'skip' => (int) $rt['skip']])) ?></p>
  <?php if ($result['new']): ?>
  <p class="himport-names"><?php foreach ($result['new'] as $i => $h): ?><?= $i ? ($lang === 'ar' ? '، ' : ', ') : '' ?><a href="<?= e(url('/portal/horses/' . (int) $h['id'])) ?>"><?= e($h['name']) ?></a><?php endforeach; ?></p>
  <?php endif; ?>
  <a class="btn" href="<?= e(url('/portal/horses')) ?>"><?= e(__('himport.open_horses')) ?></a>
</section>
<?php endif; ?>

<?php if (!$p): ?>
<div class="grid-2 himport-steps">
  <section class="card">
    <h2><?= e(__('himport.step1')) ?></h2>
    <p class="muted"><?= e(__('himport.step1_help')) ?></p>
    <p class="himport-buttons">
      <a class="btn btn-primary" href="<?= e(url('/portal/horses/import/template', ['lang' => $lang])) ?>"><?= icon('download') ?> <?= e(__('himport.template')) ?></a>
      <a class="btn" href="<?= e(url('/portal/horses/import/template', ['lang' => $other])) ?>"><?= e(__('himport.template_' . $other)) ?></a>
    </p>
    <?php if ($canExport): ?>
    <p class="himport-buttons"><a class="btn" href="<?= e(url('/portal/horses/import/template', ['lang' => $lang, 'with' => 'horses'])) ?>"><?= icon('download') ?> <?= e(__('himport.template_with')) ?></a></p>
    <p class="muted small"><?= e(__('himport.template_with_help')) ?></p>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2><?= e(__('himport.step2')) ?></h2>
    <form method="post" action="<?= e(url('/portal/horses/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label for="himport-file"><?= e(__('himport.file')) ?></label>
        <input id="himport-file" type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
        <small class="help"><?= e(__('himport.file_help', ['mb' => (int) (\App\Services\SheetReader::MAX_BYTES / 1_000_000), 'rows' => number_format(HorseImport::MAX_ROWS)])) ?></small>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(__('himport.check')) ?></button></div>
    </form>
  </section>
</div>

<section class="card">
  <h2><?= e(__('himport.columns_title')) ?></h2>
  <p class="muted small"><?= e(__('himport.columns_help')) ?></p>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('himport.col_heading')) ?></th><th><?= e(__('himport.col_required')) ?></th><th><?= e(__('himport.col_what')) ?></th></tr></thead>
    <tbody><?php foreach (array_keys($columns) as $k): ?>
      <tr>
        <td data-label="<?= e(__('himport.col_heading')) ?>"><b><?= e(HorseImport::label($k)) ?></b></td>
        <td data-label="<?= e(__('himport.col_required')) ?>"><?= HorseImport::required($k) ? '<span class="pill ok">' . e(__('common.yes')) . '</span>' : '<span class="muted">' . e(__('common.no')) . '</span>' ?></td>
        <td data-label="<?= e(__('himport.col_what')) ?>"><?= e(__('himport.help_' . $k)) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>

<?php else: ?>
<section class="card himport-file">
  <div class="card-head">
    <p class="muted"><?= e(__('himport.file_line', ['name' => $state['name'] ?? '', 'sheet' => $p['sheet'] !== '' ? $p['sheet'] : 'CSV', 'row' => $p['header']])) ?></p>
    <form method="post" action="<?= e(url('/portal/horses/import/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= e(__('himport.other_file')) ?></button></form>
  </div>
  <div class="stat-grid himport-totals">
    <div class="stat"><div class="k"><?= e(__('himport.t_new')) ?></div><div class="v"><?= (int) $t['new'] ?></div></div>
    <div class="stat"><div class="k"><?= e(__('himport.t_update')) ?></div><div class="v"><?= (int) $t['update'] ?></div></div>
    <div class="stat"><div class="k"><?= e(__('himport.t_same')) ?></div><div class="v"><?= (int) $t['same'] ?></div></div>
    <div class="stat<?= $t['skip'] ? ' alert-bad' : '' ?>"><div class="k"><?= e(__('himport.t_skip')) ?></div><div class="v"><?= (int) $t['skip'] ?></div></div>
    <div class="stat"><div class="k"><?= e(__('himport.t_ancestors')) ?></div><div class="v"><?= (int) $t['ancestors'] ?></div></div>
  </div>
  <?php if ($p['ignored']): ?><div class="alert alert-warning"><?= e(__('himport.ignored', ['cols' => implode(', ', $p['ignored'])])) ?></div><?php endif; ?>
  <?php if (in_array('purchase_price', $p['hidden'], true)): ?><div class="alert alert-warning"><?= e(__('himport.no_price_permission')) ?></div><?php endif; ?>
  <?php if (in_array('ueln', $p['hidden'], true)): ?><div class="alert alert-warning"><?= e(__('himport.no_ueln')) ?></div><?php endif; ?>
  <p class="muted small"><?= e(__('himport.preview_help')) ?></p>
  <?php if ($work): ?>
  <form method="post" action="<?= e(url('/portal/horses/import/run')) ?>" class="himport-go" data-confirm="<?= e(__('himport.confirm')) ?>">
    <?= csrf_field() ?>
    <?php if ($t['new']): ?>
    <div class="field field-check"><label><input type="checkbox" name="publish" value="1"> <?= e(__('himport.publish')) ?></label><small class="help"><?= e(__('himport.publish_help')) ?></small></div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(__('himport.go')) ?></button>
  </form>
  <?php else: ?>
  <div class="alert alert-info"><?= e(__('himport.nothing')) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <h2><?= e(__('himport.rows_title')) ?> <span class="muted">(<?= count($p['rows']) ?>)</span></h2>
  <div class="table-wrap"><table class="table compact himport-rows">
    <thead><tr><th class="num"><?= e(__('himport.col_row')) ?></th><th><?= e(__('himport.col_horse')) ?></th><th><?= e(__('himport.col_result')) ?></th><th><?= e(__('himport.col_details')) ?></th></tr></thead>
    <tbody><?php foreach ($p['rows'] as $r): $quiet = !$r['promote'] && !$r['diffs'] && !$r['msg'] && !($r['status'] === 'update' && $r['filled']); ?>
      <tr<?= $r['status'] === 'skip' ? ' class="row-alert"' : '' ?>>
        <td class="num" data-label="<?= e(__('himport.col_row')) ?>"><?= (int) $r['row'] ?></td>
        <td data-label="<?= e(__('himport.col_horse')) ?>"><?php if ($r['id'] && $r['status'] !== 'new'): ?><a href="<?= e(url('/portal/horses/' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a><?php else: ?><b><?= e($r['name'] !== '' ? $r['name'] : '—') ?></b><?php endif; ?></td>
        <td data-label="<?= e(__('himport.col_result')) ?>"><span class="pill <?= e($pills[$r['status']] ?? '') ?>"><?= e(__('himport.s_' . $r['status'])) ?></span></td>
        <td data-label="<?= e(__('himport.col_details')) ?>"<?= $quiet ? ' class="m-hide"' : '' ?>>
          <?php if ($r['promote']): ?><div class="himport-msg"><?= e(__('himport.promote')) ?></div><?php endif; ?>
          <?php if ($r['status'] === 'update' && $r['filled']): ?><div class="himport-msg"><?= e(__('himport.filled', ['fields' => implode($lang === 'ar' ? '، ' : ', ', array_unique(array_map(fn ($k) => HorseImport::label($k), $r['filled'])))])) ?></div><?php endif; ?>
          <?php foreach ($r['diffs'] as [$field, $have, $file]): ?><div class="himport-msg diff"><?= e(__('himport.diff', ['field' => $field, 'have' => $have, 'file' => $file])) ?></div><?php endforeach; ?>
          <?php foreach ($r['msg'] as [$level, $text]): ?><div class="himport-msg <?= e($level) ?>"><?= e($text) ?></div><?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
