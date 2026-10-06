<?php
use App\Core\Auth;
use App\Core\DB;
use App\Services\FileStore;
use App\Services\Studio;

$files = FileStore::forOwner($recordType, $recordId, $categories);
$canUpload = $canUpload ?? FileStore::canUpload($recordType);
$cats = $categories ?? (FileStore::OWNERS[$recordType][2] ?? ['document']);
// Studio documents are filled from this record ("breeding" is the Studio name for a breeding record)
$kind = ['breeding_record' => 'breeding', 'breeding' => 'breeding'][$recordType] ?? $recordType;
$docs = Auth::can('studio') ? DB::all('SELECT id, ref_no, doc_type, lang, title, doc_date, is_void FROM documents WHERE record_type = ? AND record_id = ? AND deleted_at IS NULL ORDER BY id DESC', [$recordType, $recordId]) : [];
?>
<?php if ($studio && Auth::can('studio')): ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('nav.studio')) ?></h2>
    <div class="actions">
      <?php foreach ($studio as $docType): if (Studio::canCreate($docType)): ?>
        <a class="btn btn-sm" href="<?= e(url('/portal/studio/new/' . $docType . '?record_type=' . $kind . '&record_id=' . $recordId)) ?>">+ <?= e(__('studio.type_' . $docType)) ?></a>
      <?php endif; endforeach; ?>
    </div>
  </div>
  <?php if ($docs): ?>
  <table class="table compact"><thead><tr><th><?= e(__('studio.ref')) ?></th><th><?= e(__('studio.type')) ?></th><th><?= e(__('common.date')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?>
    <tr class="<?= $d['is_void'] ? 'void' : '' ?>"><td><a href="<?= e(url('/portal/studio/' . $d['id'])) ?>"><?= e($d['ref_no']) ?></a></td><td><?= e(__('studio.type_' . $d['doc_type'])) ?> (<?= e(strtoupper($d['lang'])) ?>)</td><td><?= e(fmt_date($d['doc_date'])) ?></td>
    <td><?php if ($d['is_void']): ?><span class="badge badge-cancelled"><?= e(__('studio.void')) ?></span><?php else: ?><a class="btn btn-sm" href="<?= e(url('/portal/studio/' . $d['id'] . '/print')) ?>" target="_blank"><?= e(__('common.print')) ?></a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted"><?= e(__('studio.no_documents')) ?></p><?php endif; ?>
</section>
<?php endif; ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('common.files')) ?></h2></div>
  <?php if ($canUpload): ?>
  <form method="post" action="<?= e(url('/portal/files/upload')) ?>" enctype="multipart/form-data" class="upload-form">
    <?= csrf_field() ?>
    <input type="hidden" name="owner_type" value="<?= e($recordType) ?>"><input type="hidden" name="owner_id" value="<?= (int) $recordId ?>">
    <select name="category"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?= e(__('files.cat_' . $c)) ?></option><?php endforeach; ?></select>
    <input type="text" name="title" placeholder="<?= e(__('files.title_optional')) ?>">
    <label class="btn file-btn"><?= icon('upload') ?> <?= e(__('files.choose')) ?><input type="file" name="files[]" multiple accept="image/*,application/pdf<?= in_array('video', $cats, true) ? ',video/mp4' : '' ?>" data-auto-submit></label>
    <noscript><button class="btn" type="submit"><?= e(__('files.upload')) ?></button></noscript>
  </form>
  <?php endif; ?>
  <?php if ($files): ?>
  <ul class="file-grid">
    <?php foreach ($files as $f): ?>
      <li class="file-item<?= $f['is_sensitive'] ? ' sensitive' : '' ?>">
        <a href="<?= e(url('/portal/files/' . $f['id'])) ?>" target="_blank" rel="noopener">
          <?php if (str_starts_with($f['mime'], 'image/')): ?><img src="<?= e(url('/portal/files/' . $f['id'] . '/thumb')) ?>" alt="<?= e($f['title'] ?? $f['original_name']) ?>" loading="lazy"><?php else: ?><span class="file-icon"><?= $f['mime'] === 'video/mp4' ? '▶' : 'PDF' ?></span><?php endif; ?>
        </a>
        <div class="file-meta"><strong><?= e($f['title'] ?: $f['original_name']) ?></strong><small><?= e(__('files.cat_' . $f['category'])) ?> · <?= e(fmt_date($f['created_at'])) ?><?= $f['is_public'] ? ' · ' . e(__('files.public')) : '' ?></small></div>
        <?php if ($canUpload): ?>
        <div class="file-actions">
          <?php if ($f['category'] === 'photo' && in_array($recordType, ['horse', 'employee'], true)): ?><form method="post" action="<?= e(url('/portal/files/' . $f['id'] . '/main')) ?>"><?= csrf_field() ?><button class="btn btn-xs" type="submit"><?= e(__('files.make_main')) ?></button></form><?php endif; ?>
          <?php if (!$f['is_sensitive'] && in_array($f['category'], ['photo', 'video'], true) && (Auth::can('horses', 'edit') || Auth::can('cms', 'edit'))): ?><form method="post" action="<?= e(url('/portal/files/' . $f['id'] . '/public')) ?>"><?= csrf_field() ?><button class="btn btn-xs" type="submit"><?= e(__($f['is_public'] ? 'files.hide_website' : 'files.show_website')) ?></button></form><?php endif; ?>
          <form method="post" action="<?= e(url('/portal/files/' . $f['id'] . '/delete')) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-danger" type="submit">✕</button></form>
        </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="muted"><?= e(__('files.none')) ?></p><?php endif; ?>
</section>
