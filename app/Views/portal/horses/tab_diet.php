<?php
use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Resources\DietPlans;

$plan = DB::all("SELECT p.*, i.name_en AS item, i.unit, i.quantity AS stock FROM diet_plans p JOIN inventory_items i ON i.id = p.item_id WHERE p.horse_id = ? AND p.deleted_at IS NULL ORDER BY FIELD(p.feeding,'early_morning','late_morning','afternoon','evening','late_evening'), i.name_en", [$h['id']]);
$byItem = [];
foreach ($plan as $p) { $byItem[$p['item']][$p['feeding']] = $p; $byItem[$p['item']]['_unit'] = $p['unit']; }
$slots = DietPlans::FEEDINGS;
$today = DB::all("SELECT d.feeding, d.item_id FROM diet_logs d WHERE d.horse_id = ? AND d.log_date = CURDATE() AND d.deleted_at IS NULL", [$h['id']]);
$given = [];
foreach ($today as $t) { $given[$t['feeding'] . '-' . $t['item_id']] = true; }
?>
<section class="card">
  <div class="card-head"><h2><?= e(__('diet.plan')) ?></h2>
    <div class="actions">
      <?php if (Auth::can('horse_diet', 'create')): ?><a class="btn btn-sm" href="<?= e(url('/portal/diet-plans/create?horse_id=' . $h['id'])) ?>">+ <?= e(__('diet.add_plan_line')) ?></a><?php endif; ?>
      <?php if (\App\Services\Studio::canCreate('diet')): ?><a class="btn btn-sm" href="<?= e(url('/portal/studio/new/diet?record_id=' . $h['id'])) ?>"><?= icon('print') ?> <?= e(__('studio.type_diet')) ?></a><?php endif; ?>
    </div>
  </div>
  <?php if ($plan): ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('diet.feed_item')) ?></th><?php foreach ($slots as $k => $l): ?><th><?= e(__($l)) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($byItem as $item => $cells): ?>
      <tr><td data-label="<?= e(__('diet.feed_item')) ?>"><strong><?= e($item) ?></strong></td>
      <?php foreach ($slots as $k => $l): $c = $cells[$k] ?? null; ?>
        <td data-label="<?= e(__($l)) ?>"><?php if ($c): ?>
          <a href="<?= e(url('/portal/diet-plans/' . $c['id'])) ?>"><?= e(rtrim(rtrim((string) $c['quantity'], '0'), '.')) ?> <?= e($c['unit']) ?></a>
          <?php if (Auth::can('horse_diet', 'create')): ?>
            <?php if (!empty($given[$k . '-' . $c['item_id']])): ?><span class="tick" title="<?= e(__('diet.given_today')) ?>">✓</span>
            <?php else: ?><form method="post" action="<?= e(url('/portal/horses/' . $h['id'] . '/quick-feed')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int) $c['id'] ?>"><button class="btn btn-xs" type="submit" title="<?= e(__('diet.mark_given')) ?>"><?= e(__('diet.given')) ?></button></form><?php endif; ?>
          <?php endif; ?>
        <?php endif; ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="small muted"><?= e(__('diet.given_help')) ?></p>
  <?php else: ?><p class="muted"><?= e(__('diet.no_plan')) ?></p><?php endif; ?>
</section>
<?= View::partial('portal/crud/related', ['parent' => null, 'row' => $h, 'relKey' => 'diet-logs', 'filter' => ['horse_id' => (int) $h['id']], 'note' => null]) ?>
