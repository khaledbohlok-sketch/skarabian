<?php
use App\Core\Auth;
use App\Core\View;

$fields = $res->visibleFields(false);
$bySection = [];
foreach ($fields as $name => $f) {
    if (($f['type'] ?? '') === 'display' || !empty($f['hide_on_show']) || !empty($f['virtual'])) { continue; }
    $bySection[$f['section'] ?? 'main'][$name] = $f;
}
$allTabs = ['details' => ['label' => 'common.details']] + $tabs;
if (!isset($allTabs[$tab])) { $tab = 'details'; }
?>
<?php $media = $res->headMedia($row); // e.g. the horse's photo next to its name
// a record with a photo opens on a cinematic header card: the photo large, and behind it a softly blurred, slowly drifting copy
$heroImg = $media !== '' && !str_contains($media, 'is-empty') && preg_match('/<img[^>]+src="([^"]+)"/', $media, $mm) ? html_entity_decode($mm[1], ENT_QUOTES) : null; ?>
<div class="page-head<?= $media !== '' ? ' record-hero' : '' ?>">
  <?php if ($heroImg): ?><div class="rh-bg" aria-hidden="true"><img src="<?= e($heroImg) ?>" alt="" decoding="async"></div><?php endif; ?>
  <div<?= $media !== '' ? ' class="head-media"' : '' ?>>
    <?= $media ?><?php if ($media !== ''): ?><div class="head-text"><?php endif; ?>
    <?php if ($res->parentField && !empty($row[$res->parentField]) && $res->parentResource): ?>
      <a class="crumb" href="<?= e(url('/portal/' . $res->parentResource . '/' . $row[$res->parentField] . '?tab=' . $res->key)) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('common.back')) ?></a>
    <?php else: ?>
      <a class="crumb" href="<?= e(url($res->url())) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__($res->title)) ?></a>
    <?php endif; ?>
    <?php // the record's status as a badge, as its list shows it (unless the record's own header badges already do)
    $hb = $res->headerBadges($row);
    foreach ($res->visibleColumns() as $ck => $cc) {
        if (($cc['fmt'] ?? '') === 'badge' && isset($row[$ck]) && $row[$ck] !== '' && !str_contains($hb, 'badge-' . $row[$ck])) {
            $hb = status_badge((string) $row[$ck], $cc['prefix'] ?? 'status') . ' ' . $hb;
            break;
        }
    } ?>
    <h1><?= e($title) ?> <?= $hb ?></h1>
    <?= $res->headSub($row) ?>
    <?php if ($media !== ''): ?></div><?php endif; ?>
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
      <form method="post" action="<?= e(url($res->url((int) $row['id'], '/archive'))) ?>" class="inline"<?= $row['archived_at'] ? '' : ' data-confirm="' . e(__('common.confirm_archive')) . '"' ?>><?= csrf_field() ?><button class="btn" type="submit"><?= e(__($row['archived_at'] ? 'common.unarchive' : 'common.archive')) ?></button></form>
    <?php endif; ?>
    <?php if ($res->canDelete($row)): $cf = $res->deleteConfirmField(); ?>
      <form method="post" action="<?= e(url($res->url((int) $row['id'], '/delete'))) ?>" class="inline"<?= $cf ? ' data-confirm-type="' . e((string) $row[$cf]) . '" data-confirm-type-message="' . e(__('common.confirm_delete_type', ['value' => (string) $row[$cf]])) . '"' : ' data-confirm="' . e(delete_confirm()) . '"' ?>><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?> <?= e(__('common.delete')) ?></button></form>
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
    <?php if ($label = $res->sectionLabel($sec)): ?><h2><?= e(__($label)) ?></h2><?php endif; ?>
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
