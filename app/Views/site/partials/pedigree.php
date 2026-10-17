<?php
/** $ped = ['sire' => node, 'dam' => node]; node = name_en, name_ar, slug, sire, dam. $gens = 2..5 */
$gens = $gens ?? 3;
$cell = function (?array $n, string $label, bool $dam, string $path = '') {
    $name = $n ? loc($n) : '—';
    $inner = $n && $n['slug'] ? '<a href="' . e(site_url('horses/' . $n['slug'])) . '">' . e($name) . '</a>' : e($name);
    // data-path ("sds": the sire's dam's sire): site.js lights the line back to the horse on hover
    return '<div class="ped-box' . ($dam ? ' dam' : '') . '" data-path="' . e($path) . '" tabindex="0"><small>' . e($label) . '</small>' . $inner . '</div>';
};
// flatten per generation
$cols = [];
$level = [[$ped['sire'] ?? null, 's'], [$ped['dam'] ?? null, 'd']];
for ($g = 0; $g < $gens; $g++) {
    $cols[] = $level;
    $next = [];
    foreach ($level as [$n, $path]) {
        $next[] = [$n['sire'] ?? null, $path . 's'];
        $next[] = [$n['dam'] ?? null, $path . 'd'];
    }
    $level = $next;
}
// Stop at the last generation that has a known horse, so unknown lines don't fill the page with empty boxes
while (count($cols) > 1 && !array_filter(end($cols), fn ($x) => $x[0] !== null)) {
    array_pop($cols);
}
$gens = count($cols);
$labelFor = function (string $p) {
    if (strlen($p) === 1) { return __($p === 's' ? 'horse.sire' : 'horse.dam'); }
    return __(substr($p, -1) === 's' ? 'horse.sire' : 'horse.dam');
};
?>
<?php if (!empty($root)): // the horse at the root of the tree: its portrait in a gold ring (or its monogram), its names, the registry line ?>
<div class="ped-root">
  <span class="ped-root-photo"><?php if ($root['thumb']): ?><img src="<?= e($root['thumb']) ?>" alt="" width="96" height="96" loading="lazy" decoding="async"><?php else: ?><span class="foil"><?= e(\App\Services\SiteData::initials($root['name'])) ?></span><?php endif; ?></span>
  <span class="ped-root-text"><b><?= e($root['name']) ?></b><?php if ($root['other'] !== ''): ?><span class="ped-root-other" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e($root['other']) ?></span><?php endif; ?><?php if ($root['line'] !== ''): ?><small><?= e($root['line']) ?></small><?php endif; ?></span>
  <span class="ped-root-rule" aria-hidden="true"></span>
</div>
<?php endif; ?>
<p class="ped-hint"><?= e(__('site.pedigree_hint')) ?></p>
<?php $roman = ['I', 'II', 'III', 'IV', 'V']; ?>
<div class="ped5 gens-<?= (int) $gens ?>" tabindex="0" role="region" data-pedigree aria-label="<?= e(isset($name) ? __('site.pedigree_of', ['name' => $name]) : __('site.pedigree')) ?>"><table><thead><tr><?php for ($g = 0; $g < $gens; $g++): ?><th scope="col" class="ped-gen"><?= $roman[$g] ?></th><?php endfor; ?></tr></thead><tbody>
<?php $rows = 2 ** $gens; for ($r = 0; $r < $rows; $r++): ?>
  <tr>
  <?php foreach ($cols as $g => $nodes): $span = $rows / count($nodes); if ($r % $span === 0): [$n, $p] = $nodes[intdiv($r, (int) $span)]; ?>
    <td rowspan="<?= (int) $span ?>" class="g<?= $g + 1 ?>"><?= $cell($n, $labelFor($p), substr($p, -1) === 'd', $p) ?></td>
  <?php endif; endforeach; ?>
  </tr>
<?php endfor; ?>
</tbody></table></div>
