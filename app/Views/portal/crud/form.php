<?php
$quick = $row === null && \App\Core\Request::query('quick') && $res->quickFields();
$fields = $res->visibleFields($row === null);
if ($quick) {
    $fields = array_intersect_key($fields, array_flip($res->quickFields()));
}
$bySection = [];
foreach ($fields as $name => $f) {
    $bySection[$f['section'] ?? 'main'][$name] = $f;
}
// A section can have a sectionBody() (e.g. the horse pedigree tree) with no regular fields of its own —
// make sure it still gets a pass through the render loop below.
if (!$quick) {
    foreach (array_keys($res->sections()) as $sec) {
        if (!isset($bySection[$sec]) && $res->sectionBody($sec, $row) !== null) {
            $bySection[$sec] = [];
        }
    }
}
$wizard = $quick ? null : $res->wizard();
$action = $row ? $res->url((int) $row['id']) : $res->url();
$qs = $_GET ? '?' . http_build_query(array_intersect_key($_GET, ['popup' => 1, 'quick' => 1])) : '';
$inModal = (bool) \App\Core\Request::query('popup');
$stepOf = function (string $sec) use ($wizard) {
    if (!$wizard) {
        return null;
    }
    foreach ($wizard as $i => $step) {
        if (in_array($sec, $step['sections'] ?? [], true)) {
            return $i;
        }
    }
    return null;
};
// A section can replace its generic fields with custom HTML (e.g. the horse pedigree chart) — possibly
// its own independent <form>, since HTML forms can't nest. Those are rendered after the main form closes.
$customSections = [];
if (!$quick) {
    foreach (array_keys($bySection) as $sec) {
        $custom = $res->customSection($sec, $row);
        if ($custom !== null) {
            $customSections[$sec] = $custom;
        }
    }
}
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
</div>
<div class="wizard-root"<?= $wizard ? ' data-wizard-root' : '' ?>>
<?php if ($wizard): ?>
  <div class="wizard-bar" role="tablist">
    <div class="wizard-progress"><div class="wizard-progress-bar" data-wizard-progress></div></div>
    <div class="wizard-steps">
      <?php foreach ($wizard as $i => $step): ?>
        <button type="button" class="wizard-step" data-wizard-step="<?= (int) $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
          <span class="wizard-step-n"><?= (int) $i + 1 ?></span><span class="wizard-step-label"><?= e(__($step['label'])) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<form method="post" action="<?= e(url($action) . $qs) ?>" class="record-form" data-autosave="<?= e($res->key . ':' . ($row['id'] ?? 'new')) ?>" novalidate<?= array_filter($fields, fn ($f) => ($f['type'] ?? '') === 'file') ? ' enctype="multipart/form-data"' : '' ?>>
  <?= csrf_field() ?>
  <div class="draft-banner" data-draft-banner hidden>
    <span><?= e(__('common.draft_found')) ?></span>
    <button type="button" class="btn btn-sm" data-draft-restore><?= e(__('common.restore')) ?></button>
    <button type="button" class="btn btn-sm ghost" data-draft-dismiss><?= e(__('common.dismiss')) ?></button>
  </div>
  <?php foreach ($bySection as $sec => $secFields):
      if (isset($customSections[$sec])) {
          continue; // rendered after the form closes instead
      }
      $stepIndex = $stepOf($sec);
      $label = $res->sectionLabel($sec);
      $collapsed = $res->sectionCollapsed($sec) && !array_filter(array_keys($secFields), fn ($n) => \App\Core\Session::error($n));
      $body = $quick ? null : $res->sectionBody($sec, $row);
      $extra = $quick ? null : $res->sectionExtra($sec, $row);
  ?>
    <?php if ($collapsed && $label): ?>
      <details class="card wizard-section" <?= $stepIndex !== null ? 'data-wizard-section="' . (int) $stepIndex . '"' : '' ?>>
        <summary><?= e(__($label)) ?></summary>
        <?php if ($body !== null): ?><?= $body ?><?php else: ?>
        <div class="grid-fields">
          <?php foreach ($secFields as $name => $f): ?>
            <?= \App\Core\View::partial('portal/crud/field', ['res' => $res, 'name' => $name, 'f' => $f, 'row' => $row]) ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?= $extra ?>
      </details>
    <?php else: ?>
      <fieldset class="card wizard-section" <?= $stepIndex !== null ? 'data-wizard-section="' . (int) $stepIndex . '"' : '' ?>>
        <?php if ($label): ?><legend><?= e(__($label)) ?></legend><?php endif; ?>
        <?php if ($body !== null): ?><?= $body ?><?php else: ?>
        <div class="grid-fields">
          <?php foreach ($secFields as $name => $f): ?>
            <?= \App\Core\View::partial('portal/crud/field', ['res' => $res, 'name' => $name, 'f' => $f, 'row' => $row]) ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?= $extra ?>
      </fieldset>
    <?php endif; ?>
  <?php endforeach; ?>
  <div class="form-actions sticky-actions">
    <button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button>
    <?php if (!$row && !$inModal): ?><button class="btn" type="submit" name="again" value="1"><?= e(__('common.save_add_another')) ?></button><?php endif; ?>
    <?php if ($inModal): ?><button type="button" class="btn ghost" data-modal-cancel><?= e(__('common.cancel')) ?></button><?php else: ?><a class="btn ghost" href="<?= e(url($row ? $res->url((int) $row['id']) : $res->url())) ?>"><?= e(__('common.cancel')) ?></a><?php endif; ?>
  </div>
</form>
<?php foreach ($customSections as $sec => $html): ?>
  <div class="wizard-section" <?php $si = $stepOf($sec); if ($si !== null): ?>data-wizard-section="<?= (int) $si ?>"<?php endif; ?>><?= $html ?></div>
<?php endforeach; ?>
</div>
