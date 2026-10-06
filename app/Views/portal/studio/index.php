<?php
use App\Core\Auth;
$groupNames = ['office' => 'studio.group_office', 'horses' => 'studio.group_horses', 'staff' => 'studio.group_staff', 'tools' => 'studio.group_tools'];
?>
<div class="page-head">
  <div><h1><?= e(__('nav.studio')) ?></h1><p class="muted"><?= e(__('studio.intro')) ?></p></div>
  <?php if (Auth::isOwner()): ?><div class="actions"><a class="btn" href="<?= e(url('/portal/studio/templates')) ?>"><?= e(__('studio.templates')) ?></a><a class="btn" href="<?= e(url('/portal/studio/permissions')) ?>"><?= e(__('studio.permissions')) ?></a></div><?php endif; ?>
</div>
<?php if ($groups): ?>
<div class="studio-new">
  <?php foreach ($groupNames as $g => $label): if (empty($groups[$g])) { continue; } ?>
    <section class="card"><h2><?= e(__($label)) ?></h2>
      <div class="studio-types"><?php foreach ($groups[$g] as $t): ?><a class="studio-type" href="<?= e(url('/portal/studio/new/' . $t)) ?>"><?= icon('doc') ?><span><?= e(__('studio.type_' . $t)) ?></span></a><?php endforeach; ?></div>
    </section>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('studio.archive')) ?> <span class="muted">(<?= (int) $total ?>)</span></h2></div>
  <form method="get" class="filters">
    <label class="f-search"><span class="sr-only"><?= e(__('common.search')) ?></span><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('studio.search_ph')) ?>"></label>
    <label class="f-item"><span><?= e(__('studio.type')) ?></span><select name="type"><option value=""><?= e(__('common.all')) ?></option>
      <?php foreach (array_keys(\App\Services\StudioDocs::TYPES) as $t): if (in_array($t, \App\Services\StudioDocs::UNNUMBERED, true)) { continue; } ?><option value="<?= e($t) ?>"<?= selected($_GET['type'] ?? '', $t) ?>><?= e(__('studio.type_' . $t)) ?></option><?php endforeach; ?>
    </select></label>
    <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
  </form>
  <?php if ($docs): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('studio.ref')) ?></th><th><?= e(__('studio.title')) ?></th><th><?= e(__('common.date')) ?></th><th><?= e(__('studio.by_col')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($docs as $d): ?><tr class="<?= $d['is_void'] ? 'row-muted' : '' ?>">
      <td data-label="<?= e(__('studio.ref')) ?>"><a href="<?= e(url('/portal/studio/' . $d['id'])) ?>"><b><?= e($d['ref_no']) ?></b></a><?= $d['is_void'] ? ' <span class="badge badge-cancelled">' . e(__('studio.void')) . '</span>' : '' ?></td>
      <td data-label="<?= e(__('studio.title')) ?>"><?= e($d['title']) ?> <small class="muted">(<?= e(strtoupper($d['lang'])) ?>)</small></td>
      <td data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($d['doc_date'])) ?></td>
      <td data-label="<?= e(__('studio.by_col')) ?>"><?= e($d['author'] ?? '—') ?></td>
      <td><a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $d['id'] . '/print')) ?>" target="_blank"><?= e(__('common.print')) ?></a></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => max(1, (int) ceil($total / 30))]) ?>
  <?php else: ?><p class="muted"><?= e(__('studio.no_documents')) ?></p><?php endif; ?>
</section>
