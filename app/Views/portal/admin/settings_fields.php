<?php foreach ($defs as $key => [$type, $label]): $field = str_replace('.', '__', $key); $v = setting($key, ''); $dir = in_array($type, ['money', 'int', 'phone', 'hours', 'url', 'email'], true) || str_ends_with($key, '_en') ? 'ltr' : (str_ends_with($key, '_ar') ? 'rtl' : ''); ?>
  <?php if ($type === 'bool'): ?>
    <div class="field col-6 field-check"><label><input type="checkbox" name="<?= e($field) ?>" value="1"<?= checked($v === '1') ?><?= $disabled ? ' disabled' : '' ?>> <?= e(__($label)) ?></label></div>
  <?php elseif ($type === 'textarea'): ?>
    <div class="field col-6"><label for="s_<?= e($field) ?>"><?= e(__($label)) ?></label>
      <textarea id="s_<?= e($field) ?>" name="<?= e($field) ?>" rows="4"<?= $dir ? ' dir="' . $dir . '"' : '' ?><?= $disabled ? ' disabled' : '' ?>><?= e($v) ?></textarea><?= field_error($field) ?></div>
  <?php else: ?>
    <div class="field col-6"><label for="s_<?= e($field) ?>"><?= e(__($label)) ?></label>
      <input id="s_<?= e($field) ?>" type="<?= ['time' => 'time', 'url' => 'url', 'email' => 'email'][$type] ?? 'text' ?>" name="<?= e($field) ?>" value="<?= e($v) ?>"<?= $dir ? ' dir="' . $dir . '"' : '' ?><?= $disabled ? ' disabled' : '' ?>>
      <?php if (\App\Core\Lang::has($label . '_help')): ?><small class="help"><?= e(__($label . '_help')) ?></small><?php endif; ?><?= field_error($field) ?></div>
  <?php endif; ?>
<?php endforeach; ?>
