<?php
/**
 * A printout ($doc, see PrintoutService) as a run of blocks. print.js lays them out on letterhead pages: each table
 * continues from page to page with its headings repeated, its total row stays with the last rows, and a heading
 * marked data-keep-next never ends a page on its own.
 */
$cell = function (mixed $c): string {
    if (is_array($c)) {
        if (isset($c['img'])) {
            return $c['img'] ? '<img class="pthumb" src="' . e(url('/portal/files/' . (int) $c['img'] . '/thumb')) . '" alt="">' : '';
        }
        if (isset($c['sw'])) {
            return '<span class="pswatch" style="background:' . e($c['sw']) . '"></span>';
        }
        return e($c['v'] ?? '');
    }
    return e($c);
};
$cls = fn (mixed $c, array $col): string => trim((($col[1] ?? 's') === 'e' ? 'num ' : '') . (is_array($c) ? ($c['c'] ?? '') : ''));
?>
<div class="pblk doc-head">
  <h1 class="doc-title"><?= e($doc['title']) ?><?php if (!empty($doc['confidential'])): ?> <span class="conf"><?= e(__('print.confidential')) ?></span><?php endif; ?></h1>
  <?php if (!empty($doc['period'])): ?><p class="doc-period"><?= e($doc['period']) ?></p><?php endif; ?>
  <p class="doc-meta"><?= e(__('common.printed_on')) ?>: <?= e(fmt_date($doc['at'], true)) ?><?= ($doc['by'] ?? '') !== '' ? ' · ' . e($doc['by']) : '' ?><?= empty($doc['hide_count']) ? ' · ' . e(__('print.n_records', ['n' => number_format((int) $doc['count'])])) : '' ?></p>
  <?php if (!empty($doc['meta'])): ?><p class="doc-meta doc-filters"><?php foreach ($doc['meta'] as [$k, $v]): ?><span class="chip"><b><?= e($k) ?>:</b> <?= e($v) ?></span> <?php endforeach; ?></p><?php endif; ?>
</div>
<?php if (!empty($doc['cards'])): ?>
<div class="pblk doc-cards"><?php foreach ($doc['cards'] as [$label, $value, $neg]): ?><div class="kc"><small><?= e($label) ?></small><b class="<?= $neg ? 'neg' : '' ?>"><?= e($value) ?></b></div><?php endforeach; ?></div>
<?php endif; ?>
<?php foreach ($doc['sections'] as $s): $n = count($s['cols']); ?>
  <?php if (!empty($s['title'])): ?><h2 class="doc-sub" data-keep-next><?= e($s['title']) ?></h2><?php endif; ?>
  <?php if (!empty($s['note'])): ?><p class="doc-note" data-keep-next><?= e($s['note']) ?></p><?php endif; ?>
  <table class="doc-table<?= $n > 9 ? ' dense2' : ($n > 7 ? ' dense' : '') ?>" data-split data-title="<?= e(($s['title'] ?? '') ?: $doc['title']) ?>">
    <colgroup><?php foreach ($s['cols'] as $col): ?><col<?= ($col[2] ?? '') !== '' ? ' class="c-' . e($col[2]) . '"' : '' ?>><?php endforeach; ?></colgroup>
    <thead><tr><?php foreach ($s['cols'] as $col): ?><th class="<?= ($col[1] ?? 's') === 'e' ? 'num' : '' ?>"><span class="h"><?= e($col[0]) ?></span></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($s['rows'] as $row): ?><tr><?php foreach ($row as $i => $c): ?><td class="<?= e($cls($c, $s['cols'][$i] ?? [])) ?>"><?= $cell($c) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (!$s['rows']): ?><tr class="empty"><td colspan="<?= $n ?>"><?= e(__('common.no_records')) ?></td></tr><?php endif; ?>
    </tbody>
    <?php if (!empty($s['totals'])): ?><tfoot><tr><?php foreach ($s['totals'] as $i => $c): ?><th class="<?= e($cls($c, $s['cols'][$i] ?? [])) ?>"><?= $cell($c) ?></th><?php endforeach; ?></tr></tfoot><?php endif; ?>
  </table>
<?php endforeach; ?>
<?php if (!empty($doc['capped'])): ?><p class="pblk doc-note"><?= e(__('print.capped', ['n' => number_format((int) $doc['count'])])) ?></p><?php endif; ?>
<p class="pblk doc-end"><?= e(__('print.end')) ?><?= !empty($stamp) ? ' · ' . e($stamp['ref']) : '' ?></p>
