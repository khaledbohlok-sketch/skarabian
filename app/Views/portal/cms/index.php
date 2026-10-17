<?php use App\Controllers\Portal\CmsController; use App\Core\View;
$pos = array_flip($current); ?>
<div class="page-head"><div><h1><?= e(__('nav.cms_home')) ?></h1><p class="muted"><?= e(__('cms.intro')) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/' . lang())) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('cms.view_site')) ?></a></div></div>
<div class="stat-grid">
  <a class="stat" href="<?= e(url('/portal/cms/website-horses?show=on')) ?>"><div class="k"><?= e(__('cms.public_horses')) ?></div><div class="v"><?= (int) $stats['horses'] ?></div></a>
  <a class="stat" href="<?= e(url('/portal/news')) ?>"><div class="k"><?= e(__('nav.news')) ?></div><div class="v"><?= (int) $stats['news'] ?></div></a>
  <a class="stat" href="<?= e(url('/portal/gallery')) ?>"><div class="k"><?= e(__('nav.gallery')) ?></div><div class="v"><?= (int) $stats['gallery'] ?></div></a>
  <a class="stat" href="<?= e(url('/portal/cms/website-horses?tab=experts')) ?>"><div class="k"><?= e(__('cms.experts')) ?></div><div class="v"><?= (int) $stats['experts'] ?></div></a>
  <?php if ($stats['inbox'] !== null): ?><a class="stat<?= $stats['inbox'] ? ' alert-warn' : '' ?>" href="<?= e(url('/portal/inbox')) ?>"><div class="k"><?= e(__('cms.new_messages')) ?></div><div class="v"><?= (int) $stats['inbox'] ?></div></a><?php endif; ?>
</div>
<form method="post" class="stack record-form" data-autosave="cms-home">
  <?= csrf_field() ?>
  <section class="card"><h2><?= e(__('cms.sections')) ?></h2><p class="muted small"><?= e(__('cms.sections_help')) ?></p>
    <div class="table-wrap"><table class="table compact"><thead><tr><th class="bulk-col"><?= e(__('cms.show')) ?></th><th><?= e(__('cms.section')) ?></th><th class="w-order"><?= e(__('cms.order')) ?></th></tr></thead><tbody>
    <?php foreach (CmsController::SECTIONS as $i => $s): $on = isset($pos[$s]); ?>
      <tr><td class="bulk-col" data-label="<?= e(__('cms.show')) ?>"><input type="checkbox" name="sec_on[<?= e($s) ?>]" value="1" aria-label="<?= e(__('cms.sec_' . $s)) ?>"<?= checked($on) ?><?= $canEdit ? '' : ' disabled' ?>></td>
        <td class="is-title" data-label="<?= e(__('cms.section')) ?>"><strong><?= e(__('cms.sec_' . $s)) ?></strong><div class="muted small"><?= e(__('cms.sec_' . $s . '_help')) ?></div></td>
        <td data-label="<?= e(__('cms.order')) ?>"><input type="number" min="1" max="99" name="sec_pos[<?= e($s) ?>]" value="<?= $on ? $pos[$s] + 1 : count($current) + $i + 1 ?>" aria-label="<?= e(__('cms.order')) ?>" class="order-input"<?= $canEdit ? '' : ' disabled' ?>></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="grid-fields"><div class="field col-6"><label for="featured_horse_id"><?= e(__('cms.featured')) ?></label>
      <select id="featured_horse_id" name="featured_horse_id"<?= $canEdit ? '' : ' disabled' ?>><option value=""><?= e(__('cms.featured_auto')) ?></option>
        <?php foreach ($public as $h): ?><option value="<?= (int) $h['id'] ?>"<?= selected($featured, (int) $h['id']) ?>><?= e($h['name_en'] . ($h['name_ar'] ? ' · ' . $h['name_ar'] : '')) ?></option><?php endforeach; ?></select>
      <small class="help"><?= e(__('cms.featured_help')) ?></small><?= field_error('featured_horse_id') ?></div></div>
  </section>
  <section class="card"><h2><?= e(__('cms.pages')) ?></h2><p class="muted small"><?= e(__('cms.pages_help')) ?></p>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $pages, 'disabled' => !$canEdit]) ?></div>
  </section>
  <section class="card"><h2><?= e(__('cms.profiles')) ?></h2><p class="muted small"><?= e(__('cms.profiles_help')) ?></p>
    <div class="grid-fields">
      <?= View::partial('portal/admin/settings_fields', ['defs' => $profile, 'disabled' => !$canEdit]) ?>
      <div class="field col-6"><label for="profile_pedigree_gens"><?= e(__('cms.p_gens')) ?></label>
        <select id="profile_pedigree_gens" name="profile_pedigree_gens"<?= $canEdit ? '' : ' disabled' ?>><?php for ($g = 2; $g <= \App\Services\HorseService::PEDIGREE_GENERATIONS; $g++): ?><option value="<?= $g ?>"<?= selected($gens, $g) ?>><?= e(__('cms.n_generations', ['n' => $g])) ?></option><?php endfor; ?></select></div>
    </div>
    <p class="muted small"><?= e(__('cms.never_public')) ?></p>
  </section>
  <section class="card"><h2><?= e(__('cms.results')) ?></h2><p class="muted small"><?= e(__('cms.results_help')) ?></p>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $results, 'disabled' => !$canEdit]) ?></div>
  </section>
  <section class="card"><h2><?= e(__('cms.hero_story')) ?></h2>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => !$canEdit, 'translate' => 'cms-home']) ?></div>
  </section>
  <?php if ($canEdit): ?><div class="form-actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(__('cms.publish')) ?></button></div><?php endif; ?>
</form>
