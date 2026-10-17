<?php
use App\Core\DB;
use App\Services\Pickers;

// $headSearch: the list page puts its search box in the page head (crud/index); the panel then only holds the filters,
// folded away until the head's "Filters" button opens it (open at once when a filter is in use).
$headSearch = !empty($headSearch);
$filters = $res->filters();
$active = count(array_intersect_key(array_filter($_GET, fn ($v) => $v !== '' && $v !== null), $filters));
if ($headSearch && !$filters) {
    return;
}
?>
<form class="filters card<?= $active ? ' open' : '' ?><?= $headSearch && !$active ? ' is-collapsed' : '' ?>" method="get" action="<?= e(url(current_path())) ?>"<?= $headSearch ? ' id="list-filters" data-collapsible' : '' ?>>
  <?php if ($res->search && !$headSearch): ?>
    <label class="f-search"><span class="sr-only"><?= e(__('common.search')) ?></span>
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('common.search')) ?>…">
    </label>
  <?php elseif ($headSearch && ($_GET['q'] ?? '') !== ''): ?>
    <input type="hidden" name="q" value="<?= e($_GET['q']) ?>">
  <?php endif; ?>
  <?php foreach ($filters as $key => $f): $val = $_GET[$key] ?? ($f['default'] ?? ''); ?>
    <label class="f-item"><span><?= e(__($f['label'])) ?></span>
    <?php switch ($f['type']):
      case 'date_from': case 'date_to': ?>
        <input type="date" name="<?= e($key) ?>" value="<?= e($val) ?>">
      <?php break; case 'text': ?>
        <input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>">
      <?php break; case 'bool': case 'archived': case 'trashed': ?>
        <select name="<?= e($key) ?>"><option value=""><?= e(__('common.all')) ?></option><option value="1"<?= selected($val, '1') ?>><?= e(__('common.yes')) ?></option><?php if ($f['type'] === 'bool'): ?><option value="0"<?= selected($val, '0') ?>><?= e(__('common.no')) ?></option><?php endif; ?></select>
      <?php break; case 'lookup': ?>
        <select name="<?= e($key) ?>"><option value=""><?= e(__('common.all')) ?></option>
          <?php foreach (DB::all('SELECT id, value_en, value_ar FROM lookups WHERE type = ? ORDER BY sort, value_en', [$f['lookup']]) as $o): ?>
            <option value="<?= (int) $o['id'] ?>"<?= selected($val, $o['id']) ?>><?= e(loc($o, 'value')) ?></option>
          <?php endforeach; ?></select>
      <?php break; case 'picker': ?>
        <select name="<?= e($key) ?>" data-picker="<?= e($f['source']) ?>" data-allow-clear="1">
          <option value=""><?= e(__('common.all')) ?></option>
          <?php if ($val !== ''): ?><option value="<?= e($val) ?>" selected><?= e(Pickers::label($f['source'], $val) ?? '#' . $val) ?></option><?php endif; ?>
        </select>
      <?php break; default: $opts = is_callable($f['options'] ?? null) ? ($f['options'])() : ($f['options'] ?? []); ?>
        <select name="<?= e($key) ?>"><option value=""><?= e(__('common.all')) ?></option>
          <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($val, $v) ?>><?= e(opt_label($l)) ?></option><?php endforeach; ?>
        </select>
    <?php endswitch; ?>
    </label>
  <?php endforeach; ?>
  <?php foreach (['sort', 'dir', 'per'] as $keep): if (!empty($_GET[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= e($_GET[$keep]) ?>"><?php endif; endforeach; ?>
  <div class="f-buttons">
    <?php if ($filters && !$headSearch): ?><button class="btn f-toggle" type="button" data-toggle-filters aria-expanded="<?= $active ? 'true' : 'false' ?>"><?= icon('filter') ?> <?= e(__('common.filters')) ?><?= $active ? ' <span class="filter-count">' . $active . '</span>' : '' ?></button><?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button>
    <?php if (array_diff_key($_GET, ['sort' => 1, 'dir' => 1, 'per' => 1, 'page' => 1])): ?><a class="btn ghost" href="<?= e(url(current_path())) ?>"><?= e(__('common.reset')) ?></a><?php endif; ?>
  </div>
</form>
