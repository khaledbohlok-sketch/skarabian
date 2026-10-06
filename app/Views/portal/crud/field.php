<?php
use App\Core\Auth;
use App\Core\DB;
use App\Services\Money;
use App\Services\Pickers;

$type = $f['type'] ?? 'text';
$val = $res->formValue($name, $f, $row);
$id = 'f_' . $name;
$req = !empty($f['required']);
$col = (int) ($f['col'] ?? ($type === 'textarea' ? 12 : 6));
$attrs = ($req ? ' required' : '') . (!empty($f['readonly']) ? ' readonly disabled' : '') . (!empty($f['max']) && in_array($type, ['text', 'textarea', 'encrypted'], true) ? ' maxlength="' . (int) $f['max'] . '"' : '');
foreach (($f['attrs'] ?? []) as $ak => $av) { $attrs .= ' ' . e($ak) . '="' . e($av) . '"'; }
?>
<div class="field col-<?= $col ?><?= $type === 'checkbox' ? ' field-check' : '' ?><?= !empty($f['sensitive']) ? ' field-sensitive' : '' ?>">
<?php if ($type === 'checkbox'): ?>
  <label><input type="checkbox" name="<?= e($name) ?>" value="1"<?= checked($val) ?><?= $attrs ?>> <?= e(__($f['label'])) ?></label>
<?php else: ?>
  <label for="<?= e($id) ?>"><?= e(__($f['label'])) ?><?= $req ? ' <span class="req">*</span>' : '' ?><?php if (!empty($f['sensitive'])): ?> <span class="lock" title="<?= e(__('common.sensitive')) ?>">🔒</span><?php endif; ?></label>
  <?php switch ($type):
    case 'textarea': ?>
      <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($f['rows'] ?? 4) ?>"<?= $attrs ?>><?= e($val) ?></textarea>
    <?php break; case 'select': $opts = is_callable($f['options']) ? ($f['options'])() : $f['options']; ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrs ?>>
        <?php if (!$req || $val === null || $val === ''): ?><option value=""><?= e(__('common.choose')) ?></option><?php endif; ?>
        <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($val, $v) ?>><?= e(__($l)) ?></option><?php endforeach; ?>
      </select>
    <?php break; case 'lookup': ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrs ?>>
        <option value=""><?= e(__('common.choose')) ?></option>
        <?php foreach (DB::all('SELECT id, value_en, value_ar FROM lookups WHERE type = ? AND (active = 1 OR id = ?) ORDER BY sort, value_en', [$f['lookup'], (int) $val]) as $o): ?>
          <option value="<?= (int) $o['id'] ?>"<?= selected($val, $o['id']) ?>><?= e(loc($o, 'value')) ?></option>
        <?php endforeach; ?>
      </select>
    <?php break; case 'currency': ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>" data-currency<?= $attrs ?>>
        <?php foreach (Money::currencies() as $c): ?><option value="<?= e($c['code']) ?>" data-rate="<?= e($c['rate_to_qar']) ?>"<?= selected($val ?: 'QAR', $c['code']) ?>><?= e($c['code']) ?> — <?= e(loc($c, 'name')) ?></option><?php endforeach; ?>
      </select>
    <?php break; case 'picker':
      $src = Pickers::sources()[$f['source']];
      $canAdd = !empty($src['create']) && Auth::can($src['create'][1], 'create') && empty($f['no_add']);
      $params = http_build_query($f['filter'] ?? []); ?>
      <div class="picker-row">
        <select id="<?= e($id) ?>" name="<?= e($name) ?>" data-picker="<?= e($f['source']) ?>" data-params="<?= e($params) ?>"<?= $attrs ?>>
          <option value=""><?= e(__('common.search_choose')) ?></option>
          <?php if ($val !== null && $val !== ''): ?><option value="<?= e($val) ?>" selected><?= e(Pickers::label($f['source'], $val) ?? ('#' . $val)) ?></option><?php endif; ?>
        </select>
        <?php if ($canAdd): ?><button type="button" class="btn btn-sm add-new" data-add-new="<?= e(url($src['create'][0])) ?>" data-target="<?= e($id) ?>">+ <?= e(__('common.add_new')) ?></button><?php endif; ?>
      </div>
    <?php break; case 'money': case 'decimal': ?>
      <input id="<?= e($id) ?>" type="text" inputmode="decimal" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?> dir="ltr">
    <?php break; case 'int': ?>
      <input id="<?= e($id) ?>" type="number" step="1" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?>>
    <?php break; case 'date': case 'time': case 'email': case 'url': ?>
      <input id="<?= e($id) ?>" type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?><?= in_array($type, ['email', 'url'], true) ? ' dir="ltr"' : '' ?>>
    <?php break; case 'phone': ?>
      <input id="<?= e($id) ?>" type="tel" name="<?= e($name) ?>" value="<?= e($val) ?>" dir="ltr" placeholder="+974 5555 5555"<?= $attrs ?>>
    <?php break; case 'display': ?>
      <div class="display-value"><?= $f['render']($row) ?></div>
    <?php break; default: ?>
      <input id="<?= e($id) ?>" type="text" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?><?= $type === 'encrypted' ? ' autocomplete="off" dir="ltr"' : '' ?>>
  <?php endswitch; ?>
<?php endif; ?>
<?php if (!empty($f['help'])): ?><small class="help"><?= e(__($f['help'])) ?></small><?php endif; ?>
<?= field_error($name) ?>
</div>
