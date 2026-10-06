<?php
use App\Core\DB;
$kids = DB::all("SELECT o.id, o.name_en, o.sex, o.category, o.dob, o.born_at_sk, s.name_en AS sire, d.name_en AS dam FROM horses o LEFT JOIN horses s ON s.id = o.sire_id LEFT JOIN horses d ON d.id = o.dam_id WHERE (o.sire_id = ? OR o.dam_id = ?) AND o.deleted_at IS NULL ORDER BY o.dob DESC", [$h['id'], $h['id']]);
?>
<section class="card">
  <?php if ($kids): ?><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('horses.category')) ?></th><th><?= e(__('horses.dob')) ?></th><th><?= e(__($h['sex'] === 'female' ? 'horse.sire' : 'horse.dam')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($kids as $k): ?><tr><td data-label="<?= e(__('common.name')) ?>"><a href="<?= e(url('/portal/horses/' . $k['id'])) ?>"><?= e($k['name_en']) ?></a></td><td data-label="<?= e(__('horses.category')) ?>"><?= e(__('horse.cat_' . $k['category'])) ?></td><td data-label="<?= e(__('horses.dob')) ?>"><?= e(fmt_date($k['dob'])) ?></td><td><?= e($h['sex'] === 'female' ? $k['sire'] : $k['dam']) ?></td><td><?= $k['born_at_sk'] ? '<span class="chip gold">' . e(__('site.born_at_sk_flag')) . '</span>' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><p class="muted"><?= e(__('horses.no_offspring')) ?></p><?php endif; ?>
</section>
