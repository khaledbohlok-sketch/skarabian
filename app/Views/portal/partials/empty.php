<?php
/**
 * The one empty state. View::partial('portal/partials/empty', [
 *   'icon' => 'search', 'title' => 'optional heading', 'message' => 'text',
 *   'cta' => ['href' => '/portal/x/create', 'label' => 'New'],        // only when the user may create
 *   'clear' => '/portal/x',                                            // "Clear filters" when filters are active
 *   'card' => true ])
 */
$card = $card ?? true;
?>
<div class="empty<?= $card ? ' card' : '' ?>">
  <span class="empty-icon" aria-hidden="true"><?= icon($icon ?? 'search') ?></span>
  <?php if (!empty($title)): ?><p class="empty-title"><?= e($title) ?></p><?php endif; ?>
  <p><?= e($message ?? __('common.no_records')) ?></p>
  <?php if (!empty($cta) || !empty($clear)): ?>
  <div class="empty-actions">
    <?php if (!empty($clear)): ?><a class="btn" href="<?= e(url($clear)) ?>"><?= icon('x') ?> <?= e(__('ui.clear_filters')) ?></a><?php endif; ?>
    <?php if (!empty($cta)): ?><a class="btn btn-primary" href="<?= e(str_starts_with($cta['href'], 'http') ? $cta['href'] : url($cta['href'])) ?>"><?= icon('plus') ?> <?= e($cta['label']) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
