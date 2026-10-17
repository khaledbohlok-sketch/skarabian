<?php
/**
 * The page head of a list page, the same as crud/index.php: title, count ("12 documents"), search box, Filters toggle,
 * one or two secondary buttons (three or more go under "More") and one primary action.
 * View::partial('portal/partials/list_head', [
 *   'title' => 'Documents', 'count' => '12 documents', 'sub' => 'optional extra line',
 *   'search' => true | false,           // a ?q= box in the head; keeps the other query values ('placeholder' => text)
 *   'filters' => null | 0..n,           // null: no Filters button; n = active filters (opens #list-filters)
 *   'secondary' => ['<a class="btn" role="menuitem" href="…">…</a>', …],
 *   'extra' => 'html (e.g. a view toggle)', 'primary' => '<a class="btn btn-primary" …>…</a>',
 *   'crumbs' => [['/portal/x', 'Label'], …] ])
 */
$secondary = $secondary ?? [];
$searchName = $searchName ?? 'q';
?>
<div class="page-head">
  <div>
    <?php if (!empty($crumbs)): ?>
      <ol class="breadcrumbs" aria-label="<?= e(__('ui.breadcrumb')) ?>"><?php foreach ($crumbs as [$href, $label]): ?><li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li><?php endforeach; ?></ol>
    <?php endif; ?>
    <h1><?= e($title) ?></h1>
    <?php if (isset($count) && $count !== ''): ?><p class="muted head-count"><?= e($count) ?></p><?php endif; ?>
    <?php if (!empty($sub)): ?><p class="muted"><?= $sub ?></p><?php endif; ?>
  </div>
  <div class="actions">
    <?php if (!empty($search)): ?>
    <form class="head-search" method="get" action="<?= e(url(current_path())) ?>" role="search">
      <?= icon('search') ?>
      <input type="search" name="<?= e($searchName) ?>" value="<?= e(is_scalar($_GET[$searchName] ?? null) ? (string) $_GET[$searchName] : '') ?>" placeholder="<?= e($placeholder ?? __('common.search') . '…') ?>" aria-label="<?= e(__('common.search')) ?>">
      <?php foreach ($_GET as $gk => $gv): if (in_array($gk, [$searchName, 'page'], true) || !is_scalar($gv) || $gv === '') { continue; } ?><input type="hidden" name="<?= e((string) $gk) ?>" value="<?= e((string) $gv) ?>"><?php endforeach; ?>
    </form>
    <?php endif; ?>
    <?php if (isset($filters) && $filters !== null): ?>
      <button class="btn" type="button" data-toggle-filters aria-controls="list-filters" aria-expanded="<?= $filters ? 'true' : 'false' ?>"><?= icon('filter') ?> <?= e(__('common.filters')) ?><?= $filters ? ' <span class="filter-count">' . (int) $filters . '</span>' : '' ?></button>
    <?php endif; ?>
    <?php if (count($secondary) > 2): $ddId = $ddId ?? 'list-more'; ?>
      <div class="dd">
        <button class="btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="<?= e($ddId) ?>" data-dd="<?= e($ddId) ?>"><?= icon('more-horizontal') ?> <?= e(__('ui.more')) ?></button>
        <div class="dd-menu" id="<?= e($ddId) ?>" role="menu" hidden data-align="end"><?= implode('', array_map(fn ($b) => preg_replace('/class="btn(?: [^"]*)?"/', 'class="dd-item"', $b, 1), $secondary)) ?></div>
      </div>
    <?php else: ?>
      <?= implode('', array_map(fn ($b) => str_replace(' role="menuitem"', '', $b), $secondary)) ?>
    <?php endif; ?>
    <?= $extra ?? '' ?>
    <?= $primary ?? '' ?>
  </div>
</div>
