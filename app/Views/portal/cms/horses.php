<?php
$ret = '?' . http_build_query(array_filter(['tab' => $tab === 'experts' ? 'experts' : null, 'q' => $q, 'show' => $filter ?? null]));
$sw = function (string $kind, int $id, string $field, bool $on, string $label, bool $enabled = true) use ($canEdit, $ret): string {
    if (!$canEdit || !$enabled) {
        return '<span class="pill ' . ($on ? 'ok' : '') . '">' . e($on ? __('common.yes') : __('common.no')) . '</span>';
    }
    return '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="kind" value="' . $kind . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="field" value="' . e($field) . '"><input type="hidden" name="value" value="' . ($on ? '0' : '1') . '"><input type="hidden" name="return" value="' . e($ret) . '">'
        . '<button type="submit" class="toggle' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . e($label) . '"><span></span></button></form>';
};
?>
<div class="page-head"><div><h1><?= e(__('nav.website_horses')) ?></h1><p class="muted"><?= e(__('cms.horses_intro')) ?></p></div></div>
<nav class="tabs"><a href="<?= e(url('/portal/cms/website-horses')) ?>" class="<?= $tab === 'horses' ? 'on' : '' ?>"><?= e(__('nav.horses')) ?></a><a href="<?= e(url('/portal/cms/website-horses?tab=experts')) ?>" class="<?= $tab === 'experts' ? 'on' : '' ?>"><?= e(__('cms.experts')) ?></a></nav>
<form method="get" class="filters card"><?php if ($tab === 'experts'): ?><input type="hidden" name="tab" value="experts"><?php endif; ?>
  <div class="field"><label for="q"><?= e(__('common.search')) ?></label><input type="search" id="q" name="q" value="<?= e($q) ?>"></div>
  <?php if ($tab === 'horses'): ?><div class="field"><label for="show"><?= e(__('cms.show')) ?></label><select id="show" name="show"><option value=""><?= e(__('common.all')) ?></option>
    <?php foreach (['on' => 'cms.f_on', 'off' => 'cms.f_off', 'waiting' => 'cms.f_waiting'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($filter, $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="field"><button class="btn" type="submit"><?= e(__('common.apply')) ?></button></div>
</form>
<?php if (!$rows): ?><div class="card empty"><?= e(__('common.no_records')) ?></div>
<?php elseif ($tab === 'horses'): ?>
<div class="card table-wrap"><table class="table">
  <thead><tr><th></th><th><?= e(__('common.name')) ?></th><th><?= e(__('horses.category')) ?></th><th><?= e(__('cms.on_website')) ?></th><th><?= e(__('cms.breeding_stallion')) ?></th><th><?= e(__('cms.favorite')) ?></th><th><?= e(__('cms.story')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $h): $foalWait = $h['born_at_sk'] && !$h['website_approved_at']; ?>
    <tr><td><img class="thumb" src="<?= e($h['main_photo_id'] ? url('/portal/files/' . $h['main_photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt=""></td>
      <td><a href="<?= e(url('/portal/horses/' . $h['id'])) ?>"><strong><?= e($h['name_en']) ?></strong></a><?php if ($h['name_ar']): ?><div class="muted small" dir="rtl"><?= e($h['name_ar']) ?></div><?php endif; ?></td>
      <td><?= e(__('horse.cat_' . $h['category'])) ?></td>
      <td><?php if ($h['waiting']): ?><span class="pill warn"><?= e(__('cms.waiting_owner')) ?></span>
        <?php else: ?><?= $sw('horse', (int) $h['id'], 'show_on_website', (bool) $h['show_on_website'], __('cms.on_website') . ': ' . $h['name_en']) ?><?php if ($foalWait && !$h['show_on_website']): ?><div class="muted small"><?= e(__('cms.foal_needs_owner')) ?></div><?php endif; ?><?php endif; ?></td>
      <td><?= $h['sex'] === 'male' ? $sw('horse', (int) $h['id'], 'breeding_stallion', (bool) $h['breeding_stallion'], __('cms.breeding_stallion') . ': ' . $h['name_en']) : '—' ?></td>
      <td><?= $sw('horse', (int) $h['id'], 'is_favorite', (bool) $h['is_favorite'], __('cms.favorite') . ': ' . $h['name_en']) ?></td>
      <td><?= $h['story_en'] ? '✓' : '<a class="small" href="' . e(url('/portal/horses/' . $h['id'] . '/edit')) . '">' . e(__('cms.add_story')) . '</a>' ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php else: ?>
<div class="card table-wrap"><table class="table">
  <thead><tr><th></th><th><?= e(__('common.name')) ?></th><th><?= e(__('cms.public_title')) ?></th><th><?= e(__('cms.on_website')) ?></th><th><?= e(__('cms.order')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td><img class="thumb" style="border-radius:50%" src="<?= e($r['photo_id'] ? url('/portal/files/' . $r['photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt=""></td>
      <td><a href="<?= e(url('/portal/employees/' . $r['id'])) ?>"><strong><?= e($r['name_en']) ?></strong></a><div class="muted small"><?= e($r['position'] ?? '') ?></div></td>
      <td><?= e($r['public_title_en'] ?: '—') ?><?php if (!$r['public_bio_en']): ?><div class="small"><a href="<?= e(url('/portal/employees/' . $r['id'] . '/edit')) ?>"><?= e(__('cms.add_bio')) ?></a></div><?php endif; ?></td>
      <td><?= $sw('expert', (int) $r['id'], 'show_on_website', (bool) $r['show_on_website'], __('cms.on_website') . ': ' . $r['name_en']) ?></td>
      <td><?php if ($canEdit): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="kind" value="expert"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="field" value="website_sort">
        <input type="number" name="value" min="0" max="999" value="<?= (int) $r['website_sort'] ?>" style="width:70px" aria-label="<?= e(__('cms.order')) ?>"> <button class="btn btn-sm" type="submit"><?= e(__('common.save')) ?></button></form><?php else: ?><?= (int) $r['website_sort'] ?><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
