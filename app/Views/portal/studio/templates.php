<?php use App\Services\StudioDocs; ?>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/studio')) ?>">← <?= e(__('nav.studio')) ?></a><h1><?= e(__('studio.templates')) ?></h1><p class="muted"><?= e(__('studio.templates_intro')) ?></p></div></div>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('studio.type')) ?></th><th><?= e(__('studio.letterhead')) ?></th><th><?= e(__('studio.last_change')) ?></th><th></th></tr></thead>
  <tbody><?php foreach (StudioDocs::TYPES as $t => $def): if (in_array($t, StudioDocs::UNNUMBERED, true)) { continue; } $r = $rows[$t] ?? null; ?><tr>
    <td data-label="<?= e(__('studio.type')) ?>"><b><?= e(__('studio.type_' . $t)) ?></b> <small class="muted">SKA-<?= e($def['code']) ?></small></td>
    <td data-label="<?= e(__('studio.letterhead')) ?>"><?= e(__((int) ($r['letterhead_version'] ?? 2) === 1 ? 'studio.lh_v1' : 'studio.lh_v2')) ?></td>
    <td data-label="<?= e(__('studio.last_change')) ?>"><?= $r && $r['updated_at'] ? e(fmt_date($r['updated_at'], true) . ' · ' . ($r['editor'] ?? '')) : '<span class="muted">' . e(__('studio.original_wording')) . '</span>' ?></td>
    <td><a class="btn btn-sm" href="<?= e(url('/portal/studio/templates/' . $t)) ?>"><?= e(__('common.edit')) ?></a></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
