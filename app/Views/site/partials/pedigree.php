<?php
/** $ped = ['sire' => node, 'dam' => node]; node = name_en, name_ar, slug, sire, dam. $gens = 2..4 */
$gens = $gens ?? 3;
$cell = function (?array $n, string $label, bool $dam) {
    $name = $n ? loc($n) : '—';
    $inner = $n && $n['slug'] ? '<a href="' . e(site_url('horses/' . $n['slug'])) . '">' . e($name) . '</a>' : e($name);
    return '<div class="ped-box' . ($dam ? ' dam' : '') . '"><small>' . e($label) . '</small>' . $inner . '</div>';
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
$labelFor = function (string $p) {
    if (strlen($p) === 1) { return __($p === 's' ? 'horse.sire' : 'horse.dam'); }
    return __(substr($p, -1) === 's' ? 'horse.sire' : 'horse.dam');
};
?>
<div class="ped5"><table><tbody>
<?php $rows = 2 ** $gens; for ($r = 0; $r < $rows; $r++): ?>
  <tr>
  <?php foreach ($cols as $g => $nodes): $span = $rows / count($nodes); if ($r % $span === 0): [$n, $p] = $nodes[intdiv($r, (int) $span)]; ?>
    <td rowspan="<?= (int) $span ?>"><?= $cell($n, $labelFor($p), substr($p, -1) === 'd') ?></td>
  <?php endif; endforeach; ?>
  </tr>
<?php endfor; ?>
</tbody></table></div>
