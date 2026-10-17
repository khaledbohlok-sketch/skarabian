<?php
/** Number register: one line per counter (type and month / year), then every number of the chosen one. */
use App\Services\NumberRegister;

$pill = ['active' => 'ok', 'void' => 'warn', 'deleted' => 'bad', 'draft' => '', 'none' => ''];
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('numbers.intro')) ?></p></div></div>
<?php if ($duplicates): ?><div class="alert alert-error"><?= e(__('numbers.duplicates')) ?> <?= e(implode(', ', array_column($duplicates, 1))) ?></div>
<?php else: ?><div class="alert alert-info"><?= e(__('numbers.no_duplicates')) ?></div><?php endif; ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= e(__('common.type')) ?></th><th><?= e(__('numbers.range')) ?></th><th class="num"><?= e(__('numbers.issued')) ?></th>
    <th class="num"><?= e(__('numbers.s_active')) ?></th><th class="num"><?= e(__('numbers.s_void')) ?></th><th class="num"><?= e(__('numbers.s_deleted')) ?></th><th class="num"><?= e(__('numbers.s_none')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $c = $r['count']; ?>
    <tr<?= $r['seq'] === $seq && $r['period'] === $period ? ' class="row-pending"' : '' ?>>
      <td data-label="<?= e(__('common.type')) ?>"><?= e(NumberRegister::label($r['type'], $r['seq'])) ?></td>
      <td data-label="<?= e(__('numbers.range')) ?>" dir="ltr" class="nowrap"><?= e($r['first']) ?> … <?= e($r['last_no']) ?></td>
      <td class="num" data-label="<?= e(__('numbers.issued')) ?>"><?= (int) $r['last'] ?></td>
      <td class="num" data-label="<?= e(__('numbers.s_active')) ?>"><?= (int) (($c['active'] ?? 0) + ($c['draft'] ?? 0)) ?></td>
      <td class="num" data-label="<?= e(__('numbers.s_void')) ?>"><?= (int) ($c['void'] ?? 0) ?></td>
      <td class="num" data-label="<?= e(__('numbers.s_deleted')) ?>"><?= (int) ($c['deleted'] ?? 0) ?></td>
      <td class="num" data-label="<?= e(__('numbers.s_none')) ?>"><?= (int) ($c['none'] ?? 0) ?></td>
      <td><a class="btn btn-sm" href="<?= e(url('/portal/number-register?seq=' . rawurlencode($r['seq']) . '&period=' . rawurlencode($r['period']))) ?>#numbers"><?= e(__('numbers.show')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= e(__('numbers.none_yet')) ?></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($detail !== null): ?>
<section class="card" id="numbers">
  <h2 dir="ltr"><?= e($detail ? $detail[0]['no'] . ' … ' . $detail[count($detail) - 1]['no'] : $seq) ?></h2>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('numbers.number')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('common.date')) ?></th></tr></thead>
    <tbody><?php foreach ($detail as $n): ?>
      <tr><td dir="ltr" data-label="<?= e(__('numbers.number')) ?>"><?php if ($n['url']): ?><a href="<?= e(url($n['url'])) ?>"><?= e($n['no']) ?></a><?php else: ?><?= e($n['no']) ?><?php endif; ?></td>
        <td data-label="<?= e(__('common.status')) ?>"><span class="pill <?= $pill[$n['state']] ?>"><?= e(__('numbers.s_' . $n['state'])) ?></span></td><td class="nowrap" data-label="<?= e(__('common.date')) ?>"><?= e($n['date'] ? fmt_date($n['date']) : '—') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="muted small"><?= e(__('numbers.legend')) ?></p>
</section>
<?php endif; ?>
