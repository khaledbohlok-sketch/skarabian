<?php
// One report section as a table. Money cells are red when negative.
$cell = function ($v, string $fmt) {
    if ($v === '' || $v === null) { return ''; }
    return match ($fmt) {
        'money' => money($v, 'QAR', $html ?? true),
        'num'   => e(rtrim(rtrim(number_format((float) $v, 2), '0'), '.')),
        'pct'   => e(number_format((float) $v, 1)) . '%',
        default => e((string) $v),
    };
};
?>
<?php if (!empty($s['note'])): ?><p class="muted small"><?= e($s['note']) ?></p><?php endif; ?>
<table class="<?= e($tableClass) ?>">
  <thead><tr><?php foreach ($s['columns'] as [$label, $fmt]): ?><th class="<?= $fmt !== 'text' ? 'num' : '' ?>"><?= e(__($label)) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($s['rows'] as $row): ?><tr><?php foreach ($s['columns'] as $k => [$label, $fmt]): ?><td class="<?= $fmt !== 'text' ? 'num' : '' ?>" data-label="<?= e(__($label)) ?>"><?= $cell($row[$k] ?? '', $fmt) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <?php if (!$s['rows']): ?><tr><td colspan="<?= count($s['columns']) ?>" class="muted"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
  </tbody>
  <?php if ($s['rows']): ?><tfoot><tr><?php foreach ($s['columns'] as $k => [$label, $fmt]): ?><th class="<?= $fmt !== 'text' ? 'num' : '' ?>"><?= $cell($s['totals'][$k] ?? '', $fmt) ?></th><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table>
