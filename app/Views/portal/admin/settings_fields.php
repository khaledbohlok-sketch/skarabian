<?php $allDisabled = $disabled;
// $translate: the form's name for the ✨ Translate buttons under its English / Arabic text pairs (cms-home), when allowed
$aiForm = !empty($translate) && !$allDisabled && \App\Services\Ai::allowedFor($translateModule ?? 'cms', $translate) ? $translate : null;
foreach ($defs as $key => $def): [$type, $label] = $def; $disabled = $allDisabled || in_array($key, $locked ?? [], true); $field = str_replace('.', '__', $key); $v = setting($key, $def[2] ?? '');$dir = in_array($type, ['money', 'int', 'phone', 'hours', 'url', 'email'], true) || str_ends_with($key, '_en') ? 'ltr' : (str_ends_with($key, '_ar') ? 'rtl' : '');
$aiFrom = $aiForm && !$disabled && in_array($type, ['text', 'textarea'], true) && preg_match('/^(.+)_(en|ar)$/', $key, $aiM) && isset($defs[$p = $aiM[1] . '_' . ($aiM[2] === 'en' ? 'ar' : 'en')]) ? str_replace('.', '__', $p) : null;
$aiButton = $aiFrom ? \App\Core\View::partial('partials/ai_translate', ['form' => $aiForm, 'field' => $field, 'from' => $aiFrom]) : ''; ?>
  <?php if ($type === 'bool'): ?>
    <div class="field col-6 field-check"><label><input type="checkbox" name="<?= e($field) ?>" value="1"<?= checked($v === '1') ?><?= $disabled ? ' disabled' : '' ?>> <?= e(__($label)) ?></label></div>
  <?php elseif ($type === 'checks'): $sel = array_map('trim', explode(',', (string) $v)); ?>
    <div class="field col-6"><span class="label"><?= e(__($label)) ?></span>
      <div class="check-row"><?php foreach ($def['options'] as $ov => $ol): ?><label class="check-chip"><input type="checkbox" name="<?= e($field) ?>[]" value="<?= e((string) $ov) ?>"<?= checked(in_array((string) $ov, $sel, true)) ?><?= $disabled ? ' disabled' : '' ?>> <?= e(__($ol)) ?></label><?php endforeach; ?></div>
      <?php if (\App\Core\Lang::has($label . '_help')): ?><small class="help"><?= e(__($label . '_help')) ?></small><?php endif; ?><?= field_error($field) ?></div>
  <?php elseif ($type === 'textarea'): ?>
    <div class="field col-6"><label for="s_<?= e($field) ?>"><?= e(__($label)) ?></label>
      <textarea id="s_<?= e($field) ?>" name="<?= e($field) ?>" rows="4"<?= $dir ? ' dir="' . $dir . '"' : '' ?><?= $disabled ? ' disabled' : '' ?>><?= e($v) ?></textarea><?= $aiButton ?><?= field_error($field) ?></div>
  <?php elseif ($type === 'encrypted'): ?>
    <div class="field col-6"><label for="s_<?= e($field) ?>"><?= e(__($label)) ?></label>
      <input id="s_<?= e($field) ?>" type="password" name="<?= e($field) ?>" value="" autocomplete="off" dir="ltr" placeholder="<?= \App\Services\Settings::has($key) ? '••••••••••••' : '' ?>"<?= $disabled ? ' disabled' : '' ?>>
      <small class="help"><?= e(__(\App\Services\Settings::has($key) ? 'settings.secret_set_help' : 'settings.secret_empty_help')) ?></small><?= field_error($field) ?></div>
  <?php else: ?>
    <div class="field col-6"><label for="s_<?= e($field) ?>"><?= e(__($label)) ?></label>
      <input id="s_<?= e($field) ?>" type="<?= ['time' => 'time', 'url' => 'url', 'email' => 'email'][$type] ?? 'text' ?>" name="<?= e($field) ?>" value="<?= e($v) ?>"<?= $dir ? ' dir="' . $dir . '"' : '' ?><?= $disabled ? ' disabled' : '' ?>><?= $aiButton ?>
      <?php if (\App\Core\Lang::has($label . '_help')): ?><small class="help"><?= e(__($label . '_help')) ?></small><?php endif; ?><?= field_error($field) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
