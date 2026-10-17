<?php
/** The judges' card of a show result: each judge's marks per criterion, as transcribed from the source. $card = decoded show_results.judge_card */
$mark = fn ($v) => is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : (string) $v;
$judges = array_values(array_filter((array) ($card['judges'] ?? []), 'is_string'));
$rows = array_values(array_filter((array) ($card['rows'] ?? []), 'is_array'));
if (!$judges || !$rows) { return; }
$names = (array) ($card['names'] ?? []);
?>
<section class="card judge-card">
  <h2><?= e(__('shows.judge_card')) ?></h2>
  <p class="muted small"><?= e((string) ($card['title'] ?? '')) ?><?= !empty($card['source']) ? ' · ' . e(__('shows.judge_card_source', ['source' => (string) $card['source']])) : '' ?></p>
  <div class="jc-wrap">
    <table class="jc-table">
      <thead><tr><th scope="col"><?= e(__('shows.criterion')) ?></th><?php foreach ($judges as $j): ?><th scope="col" title="<?= e((string) ($names[$j] ?? '')) ?>"><?= e($j) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $marks = array_values((array) ($r['marks'] ?? [])); ?>
        <tr><th scope="row"><?= e((string) ($r['label'] ?? '')) ?></th><?php foreach ($judges as $i => $j): ?><td><?= e($mark($marks[$i] ?? '')) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($names): ?>
    <ul class="jc-judges"><?php foreach ($judges as $j): if (empty($names[$j])) { continue; } ?><li><b><?= e($j) ?></b> <?= e((string) $names[$j]) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if (!empty($card['event_note']) || !empty($card['legend'])): ?>
    <p class="muted small"><?= e(trim(($card['event_note'] ?? '') . ' ' . ($card['legend'] ?? ''))) ?></p>
  <?php endif; ?>
</section>
