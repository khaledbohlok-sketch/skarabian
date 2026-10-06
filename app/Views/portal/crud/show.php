<?php
use App\Core\Auth;
use App\Core\View;

$fields = $res->visibleFields(false);
$bySection = [];
foreach ($fields as $name => $f) {
    if (($f['type'] ?? '') === 'display' || !empty($f['hide_on_show'])) { continue; }
    $bySection[$f['section'] ?? 'main'][$name] = $f;
}
$sections = $res->sections();
$allTabs = ['details' => ['label' => 'common.details']] + $tabs;
if (!isset($allTabs[$tab])) { $tab = 'details'; }
?>
<div class="page-head">
  <div>
    <?php if ($res->parentField && !empty($row[$res->parentField]) && $res->parentResource): ?>
      <a class="crumb" href="<?= e(url('/portal/' . $res->parentResource . '/' . $row[$res->parentField] . '?tab=' . $res->key)) ?>">← <?= e(__('common.back')) ?></a>
    <?php else: ?>
      <a class="crumb" href="<?= e(url($res->url())) ?>">← <?= e(__($res->title)) ?></a>
    <?php endif; ?>
    <h1><?= e($title) ?> <?= $res->headerBadges($row) ?></h1>
  </div>
  <div class="actions">
    <?php foreach ($res->actions($row) as $a): ?>
      <?php if (($a['method'] ?? 'get') === 'post'): ?>
        <form method="post" action="<?= e(url($a['url'])) ?>" class="inline"<?= !empty($a['confirm']) ? ' data-confirm="' . e(__($a['confirm'])) . '"' : '' ?>><?= csrf_field() ?><?php foreach ($a['fields'] ?? [] as $fk => $fv): ?><input type="hidden" name="<?= e($fk) ?>" value="<?= e($fv) ?>"><?php endforeach; ?><button class="btn <?= e($a['class'] ?? '') ?>" type="submit"><?= e(__($a['label'])) ?></button></form>
      <?php else: ?>
        <a class="btn <?= e($a['class'] ?? '') ?>" href="<?= e(url($a['url'])) ?>"<?= !empty($a['blank']) ? ' target="_blank"' : '' ?>><?= e(__($a['label'])) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($res->canEdit($row)): ?><a class="btn btn-primary" href="<?= e(url($res->url((int) $row['id'], '/edit'))) ?>"><?= icon('edit') ?> <?= e(__('common.edit')) ?></a><?php endif; ?>
    <?php if ($res->archivable && Auth::can($res->module, 'delete')): ?>
      <form method="post" action="<?= e(url($res->url((int) $row['id'], '/archive'))) ?>" class="inline"><?= csrf_field() ?><button class="btn" type="submit"><?= e(__($row['archived_at'] ? 'common.unarchive' : 'common.archive')) ?></button></form>
    <?php endif; ?>
    <?php if ($res->canDelete($row)): ?>
      <form method="post" action="<?= e(url($res->url((int) $row['id'], '/delete'))) ?>" class="inline" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?> <?= e(__('common.delete')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<?= $res->beforeTabs($row) ?>
<?php if (count($allTabs) > 1): ?>
<nav class="tabs" role="tablist">
  <?php foreach ($allTabs as $k => $t): ?>
    <a role="tab" class="<?= $k === $tab ? 'on' : '' ?>" href="<?= e(url($res->url((int) $row['id'])) . '?tab=' . $k) ?>"<?= $k === $tab ? ' aria-selected="true"' : '' ?>><?= e(__($t['label'])) ?><?= isset($t['count']) && $t['count'] !== null ? ' <span class="count">' . (int) $t['count'] . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if ($tab === 'details'): ?>
  <?php foreach ($bySection as $sec => $secFields): ?>
  <section class="card">
    <?php if (!empty($sections[$sec])): ?><h2><?= e(__($sections[$sec])) ?></h2><?php endif; ?>
    <dl class="dl dl-grid">
      <?php foreach ($secFields as $name => $f): ?>
        <div class="<?= ($f['type'] ?? '') === 'textarea' ? 'wide' : '' ?>"><dt><?= e(__($f['label'])) ?></dt><dd><?= $res->display($name, $f, $row) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </section>
  <?php endforeach; ?>
  <?= $res->afterDetails($row) ?>
<?php else: ?>
  <?= View::partial('portal/crud/tab', ['res' => $res, 'row' => $row, 't' => $allTabs[$tab], 'tabKey' => $tab]) ?>
<?php endif; ?>
