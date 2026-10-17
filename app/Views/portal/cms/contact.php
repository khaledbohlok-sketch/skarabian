<?php
// two groups in one form: what the website and the staff screens show, and what is printed on documents and letterheads
$docKeys = array_filter(array_keys($defs), fn ($k) => str_starts_with($k, 'company.doc_') || $k === 'company.cr');
$docDefs = array_intersect_key($defs, array_flip($docKeys));
$siteDefs = array_diff_key($defs, $docDefs);
$social = array_filter($siteDefs, fn ($d) => ($d[0] ?? '') === 'url');
$siteDefs = array_diff_key($siteDefs, $social);
?>
<div class="page-head"><div><h1><?= e(__('nav.contact_details')) ?></h1><p class="muted"><?= e(__('cms.contact_intro')) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/' . lang() . '/contact')) ?>" target="_blank" rel="noopener"><?= icon('globe') ?> <?= e(__('cms.view_site')) ?></a></div></div>
<form method="post" class="stack record-form" data-autosave="cms-contact">
  <?= csrf_field() ?>
  <section class="card"><div class="card-head"><h2><?= e(__('cms.contact_site')) ?></h2></div>
    <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $siteDefs, 'disabled' => false]) ?></div>
  </section>
  <?php if ($social): ?>
  <section class="card"><div class="card-head"><h2><?= e(__('cms.contact_social')) ?></h2></div>
    <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $social, 'disabled' => false]) ?></div>
  </section>
  <?php endif; ?>
  <?php if ($docDefs): ?>
  <section class="card"><div class="card-head"><h2><?= e(__('cms.contact_docs')) ?></h2></div>
    <p class="muted small"><?= e(__('cms.contact_docs_help')) ?></p>
    <div class="grid-fields"><?= \App\Core\View::partial('portal/admin/settings_fields', ['defs' => $docDefs, 'disabled' => false]) ?></div>
  </section>
  <?php endif; ?>
  <div class="form-actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(__('cms.publish')) ?></button></div>
</form>
