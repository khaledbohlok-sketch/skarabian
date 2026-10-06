<?php
use App\Core\DB;
$horses = DB::all("SELECT h.id, h.name_en, h.category, a.assigned_at FROM horse_assignments a JOIN horses h ON h.id = a.horse_id WHERE a.employee_id = ? AND h.deleted_at IS NULL ORDER BY h.name_en", [$emp['id']]);
?>
<section class="card"><?php if ($horses): ?><ul class="alert-list"><?php foreach ($horses as $h): ?><li><a href="<?= e(url('/portal/horses/' . $h['id'])) ?>"><?= e($h['name_en']) ?></a><span class="muted small"><?= e(__('horse.cat_' . $h['category'])) ?> · <?= e(fmt_date($h['assigned_at'])) ?></span></li><?php endforeach; ?></ul>
<?php else: ?><p class="muted"><?= e(__('employees.no_horses')) ?></p><?php endif; ?><p class="small muted"><?= e(__('employees.assign_help')) ?></p></section>
