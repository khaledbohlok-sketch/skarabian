<?php
use App\Services\HorseService;

/** $mode: 'standalone' (its own <form> + page-head + Save button, the dedicated /pedigree-edit page),
 *  'embedded' (inline inside a caller's own <form>, e.g. the horse create/edit wizard — no <form> tag
 *  here, no csrf field, no Save button, those belong to the outer form), or
 *  'readonly' (display only — the horse profile's Pedigree tab, not clickable, no inputs). */
$mode = $mode ?? 'embedded';
$standalone = $mode === 'standalone';
$readonly = $mode === 'readonly';
$subjectName = $h['name_en'] ?? '';
$pedNode = function (array $t, string $path) {
    $n = $t;
    foreach (str_split($path) as $c) {
        $n = $n[$c === 's' ? 'sire' : 'dam'] ?? null;
        if ($n === null) {
            return null;
        }
    }
    return $n;
};
$gens = (int) ($gens ?? HorseService::PEDIGREE_GENERATIONS);
$nodes = [];
foreach (HorseService::pedigreePaths($gens) as $p) {
    $nodes[$p] = $pedNode($tree, $p);
}
$filled = count(array_filter($nodes));
$total = count($nodes);
$counts = array_count_values(array_map(fn ($n) => $n['id'], array_filter($nodes)));
$inbreeding = count(array_filter($counts, fn ($c) => $c > 1));

// Built as groups of two (father, mother): 1 group of parents, 2 of grandparents, 4, 8, then 16 in the 5th generation
$genCols = [];
$prefixes = [''];
for ($d = 0; $d < $gens; $d++) {
    $groups = [];
    foreach ($prefixes as $prefix) {
        $groups[] = ['prefix' => $prefix, 'father' => $prefix . 's', 'mother' => $prefix . 'd'];
    }
    $genCols[] = $groups;
    $next = [];
    foreach ($prefixes as $prefix) {
        $next[] = $prefix . 's';
        $next[] = $prefix . 'd';
    }
    $prefixes = $next;
}
$colTitles = [__('horses.gen_parents'), __('horses.gen_grandparents'), __('horses.gen_great'), __('horses.gen_great2'), __('horses.gen_5th')];
$groupLabel = function (string $prefix): ?string {
    if ($prefix === '') {
        return null;
    }
    $words = [];
    if (is_rtl()) {
        // Arabic puts the nearest relative last and has no "'s": the sire's mother's parents = "والدا أم الأب"
        foreach (array_reverse(str_split($prefix), true) as $i => $c) {
            $words[] = $i === 0 ? __($c === 's' ? 'horse.sire' : 'horse.dam') : __($c === 's' ? 'horses.ped_father_of' : 'horses.ped_mother_of');
        }
        return __('horses.ped_parents_of') . ' ' . implode(' ', $words);
    }
    foreach (str_split($prefix) as $i => $c) {
        $words[] = ($i === 0 ? ($c === 's' ? __('horse.sire') : __('horse.dam')) : ($c === 's' ? __('horses.ped_father') : __('horses.ped_mother'))) . '’s';
    }
    return implode(' ', $words) . ' ' . __('horses.ped_group_parents');
};
// A box's full relation to the horse ("Sire’s mother’s father" / "أب أم الأب"): its tooltip, and the Review list
$relation = function (string $path): string {
    $words = [];
    if (is_rtl()) {
        foreach (array_reverse(str_split($path), true) as $i => $c) {
            $words[] = $i === 0 ? __($c === 's' ? 'horse.sire' : 'horse.dam') : __($c === 's' ? 'horses.ped_father_of' : 'horses.ped_mother_of');
        }
        return implode(' ', $words);
    }
    foreach (str_split($path) as $i => $c) {
        $words[] = $i === 0 ? __($c === 's' ? 'horse.sire' : 'horse.dam') : mb_strtolower(__($c === 's' ? 'horses.ped_father' : 'horses.ped_mother'));
    }
    return implode('’s ', $words);
};
$rows = 2 ** $gens;
$renderNode = function (string $path, ?string $roleLabel) use ($nodes, $readonly, $relation) {
    $n = $nodes[$path];
    $source = substr($path, -1) === 's' ? 'stallions' : 'mares';
    $branch = $path[0] === 's' ? 'sire' : 'dam';
    $name = $n ? ($n['name_en'] . (!empty($n['is_external']) ? ' (' . __('horses.ext') . ')' : '')) : '';
    $title = $relation($path) . ($name !== '' ? ': ' . $name : '');
    if ($readonly) { ?>
    <div class="ped-node <?= e($branch) ?> readonly" data-ped-path="<?= e($path) ?>" title="<?= e($title) ?>" data-ped-relation="<?= e($relation($path)) ?>">
      <div class="ped-display" aria-disabled="true">
        <?php if ($roleLabel): ?><small><?= e($roleLabel) ?></small><?php endif; ?>
        <?php if ($n): ?>
          <a class="ped-name" href="<?= e(url('/portal/horses/' . (int) $n['id'])) ?>"><?= e($name) ?></a>
        <?php else: ?>
          <span class="ped-name empty"><?= e(__('horses.ped_unknown')) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php return; }
    ?>
    <div class="ped-node <?= e($branch) ?>" data-ped-path="<?= e($path) ?>" data-ped-empty-text="<?= e(__('horses.ped_empty')) ?>" title="<?= e($title) ?>" data-ped-relation="<?= e($relation($path)) ?>">
      <input type="hidden" name="touched_<?= e($path) ?>" value="<?= $n ? '1' : '0' ?>" data-ped-touched>
      <div class="ped-display" tabindex="0" role="button">
        <?php if ($roleLabel): ?><small><?= e($roleLabel) ?></small><?php endif; ?>
        <span class="ped-name<?= $name ? '' : ' empty' ?>"><?= e($name ?: __('horses.ped_empty')) ?></span>
      </div>
      <div class="ped-edit">
        <select name="pedmode_<?= e($path) ?>" data-section-toggle>
          <option value="pick"><?= e(__('common.search_choose')) ?></option>
          <option value="new"><?= e(__('horses.pedigree_new_ancestor')) ?></option>
        </select>
        <div data-section-for="pedmode_<?= e($path) ?>" data-section-value="pick">
          <select data-ped-pick name="pick_<?= e($path) ?>" data-picker="<?= e($source) ?>">
            <option value=""></option>
            <?php if ($n): ?><option value="<?= (int) $n['id'] ?>" selected><?= e($n['name_en']) ?><?= !empty($n['is_external']) ? ' (' . e(__('horses.ext')) . ')' : '' ?></option><?php endif; ?>
          </select>
          <?php if ($n): ?><a class="small" href="<?= e(url('/portal/horses/' . (int) $n['id'])) ?>" target="_blank"><?= e(__('studio.open_record')) ?></a><?php endif; ?>
        </div>
        <div data-section-for="pedmode_<?= e($path) ?>" data-section-value="new" hidden>
          <input type="text" name="new_<?= e($path) ?>_en" placeholder="<?= e(__('horses.name_en')) ?>">
          <input type="text" name="new_<?= e($path) ?>_ar" placeholder="<?= e(__('horses.name_ar')) ?>" dir="rtl">
        </div>
        <div class="ped-edit-actions">
          <button type="button" class="btn btn-sm ghost" data-ped-clear title="<?= e(__('common.clear')) ?>">× <?= e(__('common.clear')) ?></button>
          <button type="button" class="btn btn-sm btn-primary" data-ped-done><?= icon('check') ?></button>
        </div>
      </div>
    </div>
    <?php
};
?>
<?php if ($standalone): ?>
<form method="post" action="<?= e(url('/portal/horses/' . (int) $h['id'] . '/pedigree-edit')) ?>" class="record-form" data-pedigree-editor data-horse-id="<?= (int) $h['id'] ?>" novalidate>
  <?= csrf_field() ?>
  <div class="page-head">
    <h1><?= icon('tree') ?> <?= e(__('horses.pedigree_editor_title', ['name' => $h['name_en']])) ?></h1>
    <div class="actions">
      <button type="submit" class="btn btn-primary"><?= e(__('horses.pedigree_save')) ?></button>
      <a class="btn ghost" href="<?= e(url('/portal/horses/' . (int) $h['id'])) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
  </div>
<?php elseif (!$readonly): ?>
<div data-pedigree-editor>
<?php endif; ?>
  <?php if (!$readonly): ?>
  <div class="ped-legend">
    <span class="ped-leg sire"><?= e(__('horses.ped_legend_sire')) ?></span>
    <span class="ped-leg dam"><?= e(__('horses.ped_legend_dam')) ?></span>
    <span class="ped-leg auto"><?= e(__('horses.ped_legend_auto')) ?></span>
    <span class="ped-leg-hint"><?= e(__('horses.ped_legend_hint')) ?></span>
  </div>
  <p class="muted small" data-ped-complete><?= e(__('horses.pedigree_complete', ['n' => $filled, 'total' => $total])) ?><?= $inbreeding ? ' · ' . e(__('horses.pedigree_inbreeding', ['n' => $inbreeding])) : '' ?></p>
  <p class="small" data-ped-dup-note hidden><?= e(__('horses.pedigree_dup_warning')) ?></p>
  <?php endif; ?>
  <div class="ped-chart card ped-gens-<?= (int) $gens ?>">
    <div class="ped-col ped-col-subject">
      <div class="ped-col-head" style="grid-column:1"><?= e(__('horses.ped_col_horse')) ?></div>
      <div class="ped-node subject" style="grid-column:1;grid-row:2 / span <?= (int) $rows ?>">
        <div class="ped-display" aria-disabled="true"><small><?= e(__('horses.ped_subject')) ?></small><span class="ped-name" data-ped-subject-name data-placeholder="<?= e(__('horses.new_horse_placeholder')) ?>"><?= e($subjectName !== '' ? $subjectName : __('horses.new_horse_placeholder')) ?></span></div>
      </div>
    </div>
    <?php foreach ($genCols as $d => $groups): $colNum = $d + 2; ?>
    <div class="ped-col" data-gen="<?= $d + 1 ?>">
      <div class="ped-col-head" style="grid-column:<?= $colNum ?>"><?= e($colTitles[$d]) ?></div>
      <?php $span = (int) ($rows / count($groups)); foreach ($groups as $gi => $g): ?>
        <div class="ped-group" style="grid-column:<?= $colNum ?>;grid-row:<?= $gi * $span + 2 ?> / span <?= $span ?>">
          <?php if ($label = $groupLabel($g['prefix'])): ?><div class="ped-group-label <?= $g['prefix'][0] === 's' ? 'sire' : 'dam' ?><?= $d >= 3 ? ' deep' : '' ?>"><?= e($label) ?></div><?php endif; ?>
          <?php $renderNode($g['father'], $d === 0 ? __('horses.ped_role_sire') : __('horses.ped_father')); ?>
          <?php $renderNode($g['mother'], $d === 0 ? __('horses.ped_role_dam') : __('horses.ped_mother')); ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$readonly): ?><p class="hint muted small"><?= e(__('horses.pedigree_pick_or_create')) ?></p><?php endif; ?>
<?php if ($standalone): ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= e(__('horses.pedigree_save')) ?></button>
  </div>
</form>
<?php elseif (!$readonly): ?>
</div>
<?php endif; ?>
