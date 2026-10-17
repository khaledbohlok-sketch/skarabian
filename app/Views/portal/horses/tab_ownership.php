<?php
use App\Core\Auth;
use App\Services\HorseService;
$price = Auth::can('horses', 'sensitive') || Auth::can('finance', 'sensitive');
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('ownership.history')) ?></h2>
    <?php if (Auth::can('horses', 'edit') && !$h['is_external'] && $h['status'] !== 'deceased'): ?><a class="btn btn-sm" href="<?= e(url('/portal/horses/' . $h['id'] . '/transfer')) ?>"><?= icon('refresh') ?> <?= e(__('ownership.transfer')) ?></a><?php endif; ?>
  </div>
  <?php if ($rows): ?>
  <ol class="timeline vertical ownership-tl">
  <?php foreach ($rows as $o): $current = !$o['to_date']; ?>
    <li class="<?= $current ? 'is-current' : '' ?>">
      <time datetime="<?= e((string) $o['from_date']) ?>"><?= e(fmt_date($o['from_date'])) ?><?php if ($o['to_date']): ?> <span aria-hidden="true"><?= arrow('to') ?></span> <?= e(fmt_date($o['to_date'])) ?><?php endif; ?></time>
      <div class="tl-body">
        <span class="tl-kind"><?= e(__('ownership.type_' . $o['transfer_type'])) ?></span>
        <span class="tl-title">
          <?php if ($o['owner_type'] === 'client' && $o['owner_party_id'] && Auth::can('finance')): ?><a href="<?= e(url('/portal/parties/' . $o['owner_party_id'])) ?>"><?= e(HorseService::ownerLabel($o)) ?></a><?php else: ?><b><?= e(HorseService::ownerLabel($o)) ?></b><?php endif; ?>
          <?php if ($current): ?><span class="badge badge-active"><?= e(__('ownership.current')) ?></span><?php endif; ?>
        </span>
        <?php $facts = [];
        if ($price && $o['price'] !== null) { $facts[] = e(__('ownership.price')) . ': ' . money($o['price'], $o['currency']); }
        if ($o['bill_id'] && Auth::can('finance')) { $facts[] = '<a href="' . e(url('/portal/bills/' . $o['bill_id'])) . '">' . e($o['bill_number']) . '</a> ' . status_badge((string) $o['bill_status'], 'bills.status'); }
        if ((string) $o['notes'] !== '') { $facts[] = e((string) $o['notes']); } ?>
        <?php if ($facts): ?><small class="tl-facts"><?= implode('<span class="sep" aria-hidden="true">·</span>', $facts) ?></small><?php endif; ?>
        <?php if ($o['previous'] && Auth::can('horses', 'print')): ?><a class="btn btn-sm tl-action" href="<?= e(url('/portal/horses/' . $h['id'] . '/ownership/' . $o['id'] . '/certificate')) ?>" target="_blank"><?= icon('print') ?> <?= e(__('ownership.certificate')) ?></a><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  </ol>
  <?php else: ?>
    <?= \App\Core\View::partial('portal/partials/empty', ['card' => false, 'icon' => 'history', 'message' => __('common.no_records')]) ?>
  <?php endif; ?>
</section>
