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
<form method="post" class="stack">
  <?= csrf_field() ?>
  <section class="card"><h2><?= e(__('cms.sections')) ?></h2><p class="muted small"><?= e(__('cms.sections_help')) ?></p>
    <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('cms.show')) ?></th><th><?= e(__('cms.section')) ?></th><th style="width:110px"><?= e(__('cms.order')) ?></th></tr></thead><tbody>
    <?php foreach (CmsController::SECTIONS as $i => $s): $on = isset($pos[$s]); ?>
      <tr><td><input type="checkbox" name="sec_on[<?= e($s) ?>]" value="1" aria-label="<?= e(__('cms.sec_' . $s)) ?>"<?= checked($on) ?><?= $canEdit ? '' : ' disabled' ?>></td>
        <td><strong><?= e(__('cms.sec_' . $s)) ?></strong><div class="muted small"><?= e(__('cms.sec_' . $s . '_help')) ?></div></td>
        <td><input type="number" min="1" max="99" name="sec_pos[<?= e($s) ?>]" value="<?= $on ? $pos[$s] + 1 : count($current) + $i + 1 ?>" aria-label="<?= e(__('cms.order')) ?>" style="width:80px"<?= $canEdit ? '' : ' disabled' ?>></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="grid-fields"><div class="field col-6"><label for="featured_horse_id"><?= e(__('cms.featured')) ?></label>
      <select id="featured_horse_id" name="featured_horse_id"<?= $canEdit ? '' : ' disabled' ?>><option value=""><?= e(__('cms.featured_auto')) ?></option>
        <?php foreach ($public as $h): ?><option value="<?= (int) $h['id'] ?>"<?= selected($featured, (int) $h['id']) ?>><?= e($h['name_en'] . ($h['name_ar'] ? ' · ' . $h['name_ar'] : '')) ?></option><?php endforeach; ?></select>
      <small class="help"><?= e(__('cms.featured_help')) ?></small><?= field_error('featured_horse_id') ?></div></div>
  </section>
  <section class="card"><h2><?= e(__('cms.hero_story')) ?></h2>
    <div class="grid-fields"><?= View::partial('portal/admin/settings_fields', ['defs' => $defs, 'disabled' => !$canEdit]) ?></div>
  </section>
  <?php if ($canEdit): ?><div class="form-actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(__('cms.publish')) ?></button></div><?php endif; ?>
</form>
