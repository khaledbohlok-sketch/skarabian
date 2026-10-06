<?php
$fields = $res->visibleFields($row === null);
$bySection = [];
foreach ($fields as $name => $f) {
    $bySection[$f['section'] ?? 'main'][$name] = $f;
}
$sections = $res->sections();
$action = $row ? $res->url((int) $row['id']) : $res->url();
$qs = $_GET ? '?' . http_build_query(array_intersect_key($_GET, ['popup' => 1])) : '';
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <div class="actions"><a class="btn ghost" href="<?= e(url($row ? $res->url((int) $row['id']) : $res->url())) ?>"><?= e(__('common.cancel')) ?></a></div>
</div>
<form method="post" action="<?= e(url($action) . $qs) ?>" class="record-form" novalidate<?= array_filter($fields, fn ($f) => ($f['type'] ?? '') === 'file') ? ' enctype="multipart/form-data"' : '' ?>>
  <?= csrf_field() ?>
  <?php foreach ($bySection as $sec => $secFields): ?>
    <fieldset class="card">
      <?php if (!empty($sections[$sec])): ?><legend><?= e(__($sections[$sec])) ?></legend><?php endif; ?>
      <div class="grid-fields">
        <?php foreach ($secFields as $name => $f): ?>
          <?= \App\Core\View::partial('portal/crud/field', ['res' => $res, 'name' => $name, 'f' => $f, 'row' => $row]) ?>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  <div class="form-actions sticky-actions">
    <button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button>
    <a class="btn ghost" href="<?= e(url($row ? $res->url((int) $row['id']) : $res->url())) ?>"><?= e(__('common.cancel')) ?></a>
  </div>
</form>
