<?php
use App\Core\Auth;
use App\Core\DB;
$rows = DB::all('SELECT o.*, p.name_en AS to_name FROM ownership_history o LEFT JOIN parties p ON p.id = o.to_party_id WHERE o.horse_id = ? ORDER BY o.event_date DESC, o.id DESC', [$h['id']]);
$sens = Auth::can('horses', 'sensitive');
?>
<section class="card">
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('common.from')) ?></th><th><?= e(__('common.to')) ?></th><?php if ($sens): ?><th class="num"><?= e(__('horses.price')) ?></th><?php endif; ?><th><?= e(__('common.status')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?><tr><td><?= e(fmt_date($o['event_date'])) ?></td><td><?= e(__('ownership.type_' . $o['event_type'])) ?></td><td><?= e($o['from_label'] ?? '—') ?></td><td><?= e($o['to_name'] ?? 'SK Arabians') ?></td><?php if ($sens): ?><td class="num"><?= money($o['price_qar']) ?></td><?php endif; ?><td><?= status_badge($o['status'], 'ownership.status') ?></td>
    <td><?php if ($o['document_id']): ?><a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $o['document_id'])) ?>"><?= e(__('studio.type_transfer')) ?></a><?php elseif ($o['status'] === 'completed' && in_array($o['event_type'], ['sale', 'transfer'], true) && \App\Services\Studio::canCreate('transfer')): ?><a class="btn btn-xs" href="<?= e(url('/portal/studio/new/transfer?record_id=' . $o['id'])) ?>"><?= e(__('studio.type_transfer')) ?></a><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
