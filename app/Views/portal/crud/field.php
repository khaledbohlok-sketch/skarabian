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
<div class="field col-<?= $col ?><?= $type === 'checkbox' ? ' field-check' : '' ?><?= !empty($f['sensitive']) ? ' field-sensitive' : '' ?>"<?= !empty($f['unique']) ? ' data-unique="' . e($name) . '"' : '' ?><?= !empty($f['remember']) ? ' data-remember="' . e($res->key . ':' . $name) . '"' : '' ?>>
<?php if ($type === 'checkbox'): ?>
  <label><input type="checkbox" name="<?= e($name) ?>" value="1"<?= checked($val) ?><?= $attrs ?>> <?= e(__($f['label'])) ?></label>
<?php else: ?>
  <label for="<?= e($id) ?>"><?= e(__($f['label'])) ?><?= $req ? '&nbsp;<span class="req">*</span>' : '' ?><?php if (!empty($f['sensitive'])): ?> <span class="lock" title="<?= e(__('common.sensitive')) ?>"><?= icon('lock') ?></span><?php endif; ?></label>
  <?php switch ($type):
    case 'segment': $opts = is_callable($f['options']) ? ($f['options'])() : $f['options']; ?>
      <div class="segment" role="radiogroup">
        <?php foreach ($opts as $v => $l): ?>
          <label class="segment-opt"><input type="radio" name="<?= e($name) ?>" value="<?= e($v) ?>"<?= (string) $val === (string) $v ? ' checked' : '' ?><?= $attrs ?>><span><?= e(opt_label($l)) ?></span></label>
        <?php endforeach; ?>
      </div>
    <?php break; case 'textarea': ?>
      <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($f['rows'] ?? 4) ?>"<?= $attrs ?>><?= e($val) ?></textarea>
    <?php break; case 'select': $opts = is_callable($f['options']) ? ($f['options'])() : $f['options']; ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrs ?>>
        <?php if (!$req || $val === null || $val === ''): ?><option value=""><?= e(__('common.choose')) ?></option><?php endif; ?>
        <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($val, $v) ?>><?= e(opt_label($l)) ?></option><?php endforeach; ?>
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
        <select id="<?= e($id) ?>" name="<?= e($name) ?>" data-picker="<?= e($f['source']) ?>" data-params="<?= e($params) ?>"<?php if (!empty($f['depends'])): ?> data-depends="<?= e(implode(',', array_map(fn ($p, $o) => $p . ':' . $o, array_keys($f['depends']), $f['depends']))) ?>"<?php endif; ?><?= $attrs ?>>
          <option value=""><?= e(__('common.search_choose')) ?></option>
          <?php if ($val !== null && $val !== ''): ?><option value="<?= e($val) ?>" selected><?= e(Pickers::label($f['source'], $val) ?? ('#' . $val)) ?></option><?php endif; ?>
        </select>
        <?php if ($canAdd): ?><button type="button" class="btn btn-sm add-new" data-add-new="<?= e(url($src['create'][0])) ?>" data-target="<?= e($id) ?>">+ <?= e(__('common.add_new')) ?></button><?php endif; ?>
      </div>
    <?php break; case 'country':
      $countrySrc = !empty($f['as']) && $f['as'] === 'nationality' ? 'nationalities' : 'countries'; ?>
      <select id="<?= e($id) ?>" name="<?= e($name) ?>" data-picker="<?= e($countrySrc) ?>" data-allow-clear="1"<?= $attrs ?>>
        <option value=""><?= e(__('common.search_choose')) ?></option>
        <?php if ($val !== null && $val !== ''): ?><option value="<?= e($val) ?>" selected><?= e(Pickers::label($countrySrc, $val) ?? $val) ?></option><?php endif; ?>
      </select>
    <?php break; case 'pednode':
      $pedPath = $f['pedpath'];
      $pedLabel = Pickers::label($f['source'], $val); ?>
      <div class="ped-node <?= $pedPath === 's' ? 'sire' : 'dam' ?> ped-node-inline" data-ped-path="<?= e($pedPath) ?>" data-ped-empty-text="<?= e(__('horses.ped_empty')) ?>">
        <div class="ped-display" tabindex="0" role="button">
          <span class="ped-name<?= $pedLabel ? '' : ' empty' ?>"><?= e($pedLabel ?: __('horses.ped_empty')) ?></span>
        </div>
        <div class="ped-edit">
          <select name="pedmode_<?= e($pedPath) ?>" data-section-toggle>
            <option value="pick"><?= e(__('common.search_choose')) ?></option>
            <option value="new"><?= e(__('horses.pedigree_new_ancestor')) ?></option>
          </select>
          <div data-section-for="pedmode_<?= e($pedPath) ?>" data-section-value="pick">
            <select id="<?= e($id) ?>" name="<?= e($name) ?>" data-picker="<?= e($f['source']) ?>" data-ped-pick<?= $attrs ?>>
              <option value=""></option>
              <?php if ($val !== null && $val !== ''): ?><option value="<?= e($val) ?>" selected><?= e($pedLabel ?? ('#' . $val)) ?></option><?php endif; ?>
            </select>
          </div>
          <div data-section-for="pedmode_<?= e($pedPath) ?>" data-section-value="new" hidden>
            <input type="text" name="new_<?= e($pedPath) ?>_en" placeholder="<?= e(__('horses.name_en')) ?>">
            <input type="text" name="new_<?= e($pedPath) ?>_ar" placeholder="<?= e(__('horses.name_ar')) ?>" dir="rtl">
            <input type="text" name="new_<?= e($pedPath) ?>_country" placeholder="<?= e(__('horses.origin_country')) ?>">
          </div>
          <button type="button" class="btn btn-sm" data-ped-done><?= icon('check') ?></button>
        </div>
      </div>
    <?php break; case 'money': case 'decimal':
      if (is_numeric($val)) {
          // money with 2 decimals (95.0000 → 95.00); a price kept to 4 decimals (an average cost, 95.1234) keeps them, so
          // saving the form never rounds it. Other decimals: up to 3, without trailing zeros (10.000 → 10).
          $two = number_format((float) $val, 2, '.', '');
          $val = $type === 'money'
              ? (abs((float) $two - (float) $val) < 0.000001 ? $two : rtrim(rtrim(number_format((float) $val, 4, '.', ''), '0'), '.'))
              : rtrim(rtrim(number_format((float) $val, 3, '.', ''), '0'), '.');
      } ?>
      <div class="field-unit-wrap"><input id="<?= e($id) ?>" type="text" inputmode="decimal" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?> dir="ltr"><?php if (!empty($f['unit'])): ?><span class="field-unit"><?= e(__('units.' . $f['unit'])) ?></span><?php endif; ?></div>
    <?php break; case 'int': ?>
      <div class="field-unit-wrap"><input id="<?= e($id) ?>" type="number" step="1" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?>><?php if (!empty($f['unit'])): ?><span class="field-unit"><?= e(__('units.' . $f['unit'])) ?></span><?php endif; ?></div>
    <?php break; case 'date': case 'time': case 'email': case 'url': ?>
      <input id="<?= e($id) ?>" type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?><?= in_array($type, ['email', 'url'], true) ? ' dir="ltr"' : '' ?>>
    <?php break; case 'phone': ?>
      <input id="<?= e($id) ?>" type="tel" name="<?= e($name) ?>" value="<?= e($val) ?>" dir="ltr" placeholder="+974 5555 5555"<?= $attrs ?>>
    <?php break; case 'file': ?>
      <input id="<?= e($id) ?>" type="file" name="<?= e($name) ?>" accept="<?= e($f['accept'] ?? 'image/*,application/pdf') ?>"<?= $attrs ?>>
    <?php break; case 'color': ?>
      <input id="<?= e($id) ?>" type="color" name="<?= e($name) ?>" value="<?= e($val ?: '#8a8f98') ?>"<?= $attrs ?> style="width:70px;padding:3px">
    <?php break; case 'display': ?>
      <div class="display-value"><?= $f['render']($row) ?></div>
    <?php break; default: ?>
      <input id="<?= e($id) ?>" type="text" name="<?= e($name) ?>" value="<?= e($val) ?>"<?= $attrs ?><?= $type === 'encrypted' ? ' autocomplete="off" dir="ltr" data-no-draft' : '' ?>>
  <?php endswitch; ?>
<?php endif; ?>
<?php // ✨ Translate: an English / Arabic text of a website form with its partner field (AI assistant switched on)
if (in_array($type, ['text', 'textarea'], true) && empty($f['readonly']) && preg_match('/^(.+)_(en|ar)$/', $name, $aiM) && \App\Services\Ai::allowedFor($res->module, $res->key)
    && isset($res->fields()[$aiFrom = $aiM[1] . '_' . ($aiM[2] === 'en' ? 'ar' : 'en')])): ?>
  <?= \App\Core\View::partial('partials/ai_translate', ['form' => $res->key, 'field' => $name, 'from' => $aiFrom]) ?>
<?php endif; ?>
<?php if (!empty($f['help'])): ?><small class="help"><?= e(__($f['help'])) ?></small><?php endif; ?>
<?= field_error($name) ?>
</div>
