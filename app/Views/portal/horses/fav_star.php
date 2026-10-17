<?php
/**
 * A horse's favourite star: a button for staff who may edit horses (one click marks or unmarks it; portal.js does it in
 * place, without JavaScript the form posts and comes back), otherwise just the mark when the horse is a favourite.
 */
$on = !empty($h['is_favorite']);
$name = (string) ($h['name_en'] ?? '');
$label = fn (bool $state) => __($state ? 'horses.unfavorite' : 'horses.favorite') . ($name !== '' ? ': ' . $name : '');
if (\App\Core\Auth::can('horses', 'edit')): ?>
<form method="post" action="<?= e(url('/portal/horses/' . (int) $h['id'] . '/favorite')) ?>" class="fav-form" data-fav data-allow-resubmit><?= csrf_field() ?><button type="submit" class="fav-star<?= $on ? ' on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" title="<?= e($label($on)) ?>" aria-label="<?= e($label($on)) ?>" data-label-on="<?= e($label(true)) ?>" data-label-off="<?= e($label(false)) ?>"><?= $on ? '★' : '☆' ?></button></form>
<?php elseif ($on): ?><span class="fav-star on static" title="<?= e(__('horses.is_favorite')) ?>" aria-label="<?= e(__('horses.is_favorite')) ?>">★</span><?php endif; ?>
