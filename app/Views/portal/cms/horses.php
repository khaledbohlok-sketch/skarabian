<?php
$filter = $filter ?? null;
$ret = '?' . http_build_query(array_filter(['tab' => $tab === 'experts' ? 'experts' : null, 'q' => $q, 'show' => $filter]));
$sw = function (string $kind, int $id, string $field, bool $on, string $label, bool $enabled = true) use ($canEdit, $ret): string {
    if (!$canEdit || !$enabled) {
        return '<span class="pill ' . ($on ? 'ok' : '') . '">' . e($on ? __('common.yes') : __('common.no')) . '</span>';
    }
    return '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="kind" value="' . $kind . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="field" value="' . e($field) . '"><input type="hidden" name="value" value="' . ($on ? '0' : '1') . '"><input type="hidden" name="return" value="' . e($ret) . '">'
        . '<button type="submit" class="toggle' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . e($label) . '"><span></span></button></form>';
};
$bulk = function (string $what, string $label, string $confirm, string $class = '') {
    return '<form method="post" class="inline" data-confirm="' . e(__($confirm)) . '">' . csrf_field() . '<input type="hidden" name="action" value="bulk"><input type="hidden" name="what" value="' . e($what) . '">'
        . '<button class="btn btn-sm ' . $class . '" type="submit">' . e(__($label)) . '</button></form>';
};
$active = $tab === 'horses' && $filter ? 1 : 0;
$filtered = $active || $q !== '';
// the other language's name under the shown one (an English name under the Arabic one, and the reverse)
$otherName = fn (array $r) => \App\Core\Lang::current() === 'ar' ? (string) ($r['name_en'] ?? '') : (string) ($r['name_ar'] ?? '');
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => __('nav.website_horses'), 'count' => plural(count($rows), $tab === 'horses' ? 'count.horse' : 'count.record'), 'sub' => e(__('cms.horses_intro')),
    'search' => true, 'filters' => $tab === 'horses' ? $active : null,
    'secondary' => ['<a class="btn" role="menuitem" href="' . e(url('/' . lang() . '/horses')) . '" target="_blank" rel="noopener">' . icon('globe') . ' <span>' . e(__('cms.view_site')) . '</span></a>'],
]) ?>
<nav class="tabs"><a href="<?= e(url('/portal/cms/website-horses')) ?>" class="<?= $tab === 'horses' ? 'on' : '' ?>"<?= $tab === 'horses' ? ' aria-current="page"' : '' ?>><?= e(__('nav.horses')) ?></a><a href="<?= e(url('/portal/cms/website-horses?tab=experts')) ?>" class="<?= $tab === 'experts' ? 'on' : '' ?>"<?= $tab === 'experts' ? ' aria-current="page"' : '' ?>><?= e(__('cms.experts')) ?></a></nav>
<?php if ($tab === 'horses'): ?>
<form method="get" class="filters card<?= $active ? ' open' : ' is-collapsed' ?>" id="list-filters" data-collapsible action="<?= e(url(current_path())) ?>">
  <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
  <label class="f-item"><span><?= e(__('cms.show')) ?></span><select name="show"><option value=""><?= e(__('common.all')) ?></option>
    <?php foreach (['on' => 'cms.f_on', 'off' => 'cms.f_off', 'waiting' => 'cms.f_waiting'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($filter, $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></label>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button><?php if ($filtered): ?><a class="btn ghost" href="<?= e(url(current_path())) ?>"><?= e(__('common.reset')) ?></a><?php endif; ?></div>
</form>
<?php endif; ?>
<?php if ($tab === 'horses' && $canEdit): ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('cms.bulk_title')) ?></h2></div>
  <p class="muted small"><?= e(__('cms.bulk_help')) ?></p>
  <div class="actions mt">
    <?= $bulk('horses_on', 'cms.bulk_horses_on', 'cms.bulk_horses_on_confirm') ?>
    <?= $bulk('horses_off', 'cms.bulk_horses_off', 'cms.bulk_horses_off_confirm') ?>
    <?= $bulk('results_on', 'cms.bulk_results_on', 'cms.bulk_results_on_confirm') ?>
    <?= $bulk('results_off', 'cms.bulk_results_off', 'cms.bulk_results_off_confirm') ?>
  </div>
</section>
<?php endif; ?>
<?php if (!$rows): ?>
  <?= \App\Core\View::partial('portal/partials/empty', ['icon' => $filtered ? 'search' : 'globe', 'message' => __($filtered ? 'ui.empty_filtered' : 'common.no_records'), 'clear' => $filtered ? current_path() . ($tab === 'experts' ? '?tab=experts' : '') : null]) ?>
<?php elseif ($tab === 'horses'): $order = $canEdit && !empty($canOrder); ?>
<?php if ($order): ?><form method="post" id="order-form"><?= csrf_field() ?><input type="hidden" name="action" value="order"><input type="hidden" name="return" value="<?= e($ret) ?>"></form><?php endif; ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th class="is-thumb"><span class="sr-only"><?= e(__('horses.photo')) ?></span></th><th><?= e(__('common.name')) ?></th><th><?= e(__('horses.category')) ?></th><th class="nowrap"><?= e(__('cms.on_website')) ?></th><th class="nowrap"><?= e(__('cms.favorite')) ?></th><?php if (!empty($canOrder)): ?><th class="nowrap"><?= e(__('cms.order')) ?></th><?php endif; ?><th class="num"><?= e(__('cms.results_public')) ?></th><th><?= e(__('cms.story')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $h): $foalWait = $h['born_at_sk'] && !$h['website_approved_at']; $other = $otherName($h); ?>
    <tr><td class="is-title is-thumb"><img class="thumb" src="<?= e($h['main_photo_id'] ? url('/portal/files/' . $h['main_photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt="" width="46" height="46" loading="lazy"></td>
      <td class="is-title2" data-label="<?= e(__('common.name')) ?>"><a href="<?= e(url('/portal/horses/' . $h['id'])) ?>"><strong><?= e(loc($h)) ?></strong></a><?php if ($other !== '' && $other !== loc($h)): ?><div class="muted small" dir="auto"><?= e($other) ?></div><?php endif; ?></td>
      <td data-label="<?= e(__('horses.category')) ?>"><?= e(__('horse.cat_' . $h['category'])) ?></td>
      <td data-label="<?= e(__('cms.on_website')) ?>"><?php if ($h['waiting']): ?><span class="badge badge-pending"><?= e(__('cms.waiting_owner')) ?></span>
        <?php else: ?><?= $sw('horse', (int) $h['id'], 'show_on_website', (bool) $h['show_on_website'], __('cms.on_website') . ': ' . loc($h)) ?><?php if ($foalWait && !$h['show_on_website']): ?><div class="muted small"><?= e(__('cms.foal_needs_owner')) ?></div><?php endif; ?><?php endif; ?></td>
      <td data-label="<?= e(__('cms.favorite')) ?>"><?= $sw('horse', (int) $h['id'], 'is_favorite', (bool) $h['is_favorite'], __('cms.favorite') . ': ' . loc($h)) ?></td>
      <?php if (!empty($canOrder)): ?><td data-label="<?= e(__('cms.order')) ?>"><?php if ($order): ?><input class="order-input" form="order-form" type="number" name="order[<?= (int) $h['id'] ?>]" min="1" max="9999" value="<?= $h['website_sort'] !== null ? (int) $h['website_sort'] : '' ?>" aria-label="<?= e(__('cms.order') . ': ' . loc($h)) ?>"><?php else: ?><?= $h['website_sort'] !== null ? (int) $h['website_sort'] : '—' ?><?php endif; ?></td><?php endif; ?>
      <td class="num" data-label="<?= e(__('cms.results_public')) ?>"><?= (int) $h['results'] ? number_format((int) $h['results_public']) . ' / ' . number_format((int) $h['results']) : '<span class="muted">—</span>' ?></td>
      <td data-label="<?= e(__('cms.story')) ?>"><?= $h['story_en'] ? '<span class="tick" title="' . e(__('common.yes')) . '">✓</span>' : '<a class="small" href="' . e(url('/portal/horses/' . $h['id'] . '/edit')) . '">' . e(__('cms.add_story')) . '</a>' ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php if ($order): ?><div class="form-actions sticky-actions"><span class="muted small"><?= e(__('cms.order_help')) ?></span><button class="btn btn-primary" type="submit" form="order-form"><?= e(__('cms.save_order')) ?></button></div><?php endif; ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th class="is-thumb"><span class="sr-only"><?= e(__('horses.photo')) ?></span></th><th><?= e(__('common.name')) ?></th><th><?= e(__('cms.public_title')) ?></th><th class="nowrap"><?= e(__('cms.on_website')) ?></th><th class="nowrap"><?= e(__('cms.order')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td class="is-title is-thumb"><img class="thumb round" src="<?= e($r['photo_id'] ? url('/portal/files/' . $r['photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt="" width="46" height="46" loading="lazy"></td>
      <td class="is-title2" data-label="<?= e(__('common.name')) ?>"><a href="<?= e(url('/portal/employees/' . $r['id'])) ?>"><strong><?= e(loc($r)) ?></strong></a><div class="muted small"><?= e($r['position'] ?? '') ?></div></td>
      <td data-label="<?= e(__('cms.public_title')) ?>"><?= e($r['public_title_en'] ?: '—') ?><?php if (!$r['public_bio_en']): ?><div class="small"><a href="<?= e(url('/portal/employees/' . $r['id'] . '/edit')) ?>"><?= e(__('cms.add_bio')) ?></a></div><?php endif; ?></td>
      <td data-label="<?= e(__('cms.on_website')) ?>"><?= $sw('expert', (int) $r['id'], 'show_on_website', (bool) $r['show_on_website'], __('cms.on_website') . ': ' . loc($r)) ?></td>
      <td data-label="<?= e(__('cms.order')) ?>"><?php if ($canEdit): ?><form method="post" class="order-row"><?= csrf_field() ?><input type="hidden" name="kind" value="expert"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="field" value="website_sort">
        <input class="order-input" type="number" name="value" min="0" max="999" value="<?= (int) $r['website_sort'] ?>" aria-label="<?= e(__('cms.order') . ': ' . loc($r)) ?>"> <button class="btn btn-sm" type="submit"><?= e(__('common.save')) ?></button></form><?php else: ?><?= (int) $r['website_sort'] ?><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
