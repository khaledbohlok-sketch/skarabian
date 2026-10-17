<?php
/**
 * The judges' scorecard of a show result, as the public sees it: the total in large numerals, the medal, then each
 * criterion (type, head, neck, body, legs, movement) with the judges' average mark drawn as a gold bar out of 20.
 * $r = a SiteData::results() row with judge_card (JSON) and score; $compact = true for the homepage (no judge names).
 * Marks are the card's own: every criterion is out of 20, as on an ECAHO scoring sheet.
 */
use App\Services\SiteData;

$card = !empty($r['judge_card']) ? json_decode((string) $r['judge_card'], true) : null;
$rows = is_array($card) ? array_values(array_filter((array) ($card['rows'] ?? []), fn ($x) => is_array($x) && !empty($x['marks']))) : [];
if (!$rows) { return; }
$judges = array_values(array_filter((array) ($card['judges'] ?? []), 'is_string'));
$compact = $compact ?? false;
$medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze'];
$total = $r['score'] !== null ? (float) $r['score'] : (float) ($card['total'] ?? 0);
$labels = ['TYPE' => 'site.crit_type', 'HEAD' => 'site.crit_head', 'NECK' => 'site.crit_neck', 'H&N' => 'site.crit_hn', 'BODY' => 'site.crit_body', 'B&T' => 'site.crit_body', 'LEGS' => 'site.crit_legs', 'MOVE' => 'site.crit_move'];
$fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
$names = (array) ($card['names'] ?? []);
// the line show people repeat: the criterion most judges marked 20 ("5 of 6 judges gave 20 for Head")
$perfect = null;
foreach ($rows as $row) {
    $marks = array_values(array_filter((array) $row['marks'], 'is_numeric'));
    $n20 = count(array_filter($marks, fn ($m) => (float) $m >= 20));
    if ($n20 >= 2 && ($perfect === null || $n20 > $perfect[0])) {
        $label = strtoupper(trim((string) ($row['label'] ?? '')));
        $perfect = [$n20, count($marks), isset($labels[$label]) ? __($labels[$label]) : $label];
    }
}
$show = loc(['name_en' => $r['show_en'], 'name_ar' => $r['show_ar']]);
$year = substr((string) $r['start_date'], 0, 4);
?>
<figure class="scorecard cert<?= $compact ? ' compact' : '' ?>" data-reveal>
  <?php foreach (['tl', 'tr', 'bl', 'br'] as $c): ?><svg class="orn sc-corner <?= $c ?>" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#rosette"></use></svg><?php endforeach; ?>
  <span class="seal seal-stamp" aria-hidden="true"><svg viewBox="0 0 100 100"><use href="#seal"></use></svg><img src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="" width="20" height="30" loading="lazy"></span>
  <figcaption class="sc-head">
    <span class="eyebrow" style="margin:0"><?= e(__('shows.judge_card')) ?></span>
    <strong class="sc-show"><?= e($show) ?> · <?= e($year) ?></strong>
    <?php if ($r['class_name']): ?><small class="sc-class"><?= e(__('shows.class')) ?> <?= e((string) $r['class_name']) ?><?= $r['placing'] ? ' · ' . e(__('site.placed', ['n' => (int) $r['placing']])) : '' ?></small><?php endif; ?>
  </figcaption>
  <div class="sc-total">
    <b class="num" data-count-float="<?= e($fmt($total)) ?>"><?= e($fmt($total)) ?></b>
    <span><?= e(__('site.score_total')) ?><?php if (isset($medalCls[$r['medal']])): ?> · <span class="medal sm <?= e($medalCls[$r['medal']]) ?>" aria-hidden="true">★</span> <?= e(SiteData::resultText($r)) ?><?php endif; ?></span>
  </div>
  <dl class="sc-rows">
    <?php foreach ($rows as $ri => $row): $marks = array_values(array_filter((array) $row['marks'], 'is_numeric')); if (!$marks) { continue; }
      $avg = array_sum($marks) / count($marks); $max = max($marks); $min = min($marks); $label = strtoupper(trim((string) ($row['label'] ?? ''))); ?>
      <div class="sc-row" style="--v:<?= e(number_format($avg / 20 * 100, 1, '.', '')) ?>%;--lo:<?= e(number_format($min / 20 * 100, 1, '.', '')) ?>%;--hi:<?= e(number_format($max / 20 * 100, 1, '.', '')) ?>%;--i:<?= (int) $ri ?>">
        <dt><?= e(isset($labels[$label]) ? __($labels[$label]) : $label) ?><?php if (isset($labels[$label])): ?><small lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= e(t_other($labels[$label])) ?></small><?php endif; ?></dt>
        <dd><span class="sc-bar" aria-hidden="true"><i class="range"></i><i></i></span><b class="num"><?= e($fmt(round($avg, 2))) ?></b><small class="num" aria-label="<?= e(__('site.score_range')) ?>"><?= e($fmt($min)) ?>–<?= e($fmt($max)) ?></small></dd>
      </div>
    <?php endforeach; ?>
  </dl>
  <?php if (!$compact && count($judges) >= 2): // the matrix: one column per judge (the name on hover), one row per criterion, a 20 marked with a gold rhombus ?>
  <div class="table-scroll sc-matrix-wrap">
    <table class="sc-matrix">
      <thead><tr><th scope="col"><span class="sr-only"><?= e(__('shows.criterion')) ?></span></th>
        <?php foreach ($judges as $j): ?><th scope="col"><?= !empty($names[$j]) ? '<abbr title="' . e((string) $names[$j]) . '">' . e($j) . '</abbr>' : e($j) ?></th><?php endforeach; ?>
        <th scope="col" class="sc-avg">Ø</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): $marks = (array) $row['marks']; $nums = array_values(array_filter($marks, 'is_numeric')); if (!$nums) { continue; } $label = strtoupper(trim((string) ($row['label'] ?? ''))); ?>
        <tr><th scope="row"><?= e(isset($labels[$label]) ? __($labels[$label]) : $label) ?></th>
          <?php foreach ($judges as $ji => $j): $m = $marks[$ji] ?? null; ?><td<?= is_numeric($m) && (float) $m >= 20 ? ' class="sc-20"' : '' ?>><?= is_numeric($m) ? e($fmt((float) $m)) : '—' ?></td><?php endforeach; ?>
          <td class="sc-avg"><?= e($fmt(round(array_sum($nums) / count($nums), 2))) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php if ($perfect): ?><p class="sc-perfect"><?= e(__('site.judges_perfect', ['n' => $perfect[0], 'total' => $perfect[1], 'crit' => $perfect[2]])) ?></p><?php endif; ?>
  <?php if ($judges): ?><p class="sc-note"><?= e(__('site.judges_n', ['n' => count($judges)])) ?><?= !$compact && !empty($card['source']) ? ' · ' . e((string) $card['source']) : '' ?></p><?php endif; ?>
</figure>
