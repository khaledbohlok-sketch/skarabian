<?php
use App\Core\Auth;
use App\Core\DB;
$checks = DB::all('SELECT * FROM pregnancy_checks WHERE breeding_record_id = ? AND deleted_at IS NULL ORDER BY check_date DESC', [$br['id']]);
?>
<section class="card">
  <h2><?= e(__('breeding.checks')) ?></h2>
  <?php if ($checks): ?><div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('breeding.result')) ?></th><th><?= e(__('breeding.days_pregnant')) ?></th><th><?= e(__('common.notes')) ?></th></tr></thead><tbody>
  <?php foreach ($checks as $c): ?><tr><td><?= e(fmt_date($c['check_date'])) ?></td><td><?= status_badge($c['result'], 'breeding.result') ?></td><td><?= e($c['days_pregnant']) ?></td><td><?= e($c['notes']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><p class="muted"><?= e(__('breeding.no_checks')) ?></p><?php endif; ?>
  <?php if (Auth::can('horse_breeding', 'create') && in_array($br['status'], ['open', 'pregnant'], true)): ?>
  <form method="post" action="<?= e(url('/portal/breeding-records/' . $br['id'] . '/check')) ?>" class="form-inline mt"><?= csrf_field() ?>
    <input type="date" name="check_date" value="<?= e(date('Y-m-d')) ?>" required style="max-width:170px">
    <select name="result" style="max-width:200px"><?php foreach (['scheduled', 'positive', 'negative', 'inconclusive'] as $r): ?><option value="<?= $r ?>"><?= e(__('breeding.result_' . $r)) ?></option><?php endforeach; ?></select>
    <input type="number" name="days_pregnant" min="0" max="400" placeholder="<?= e(__('breeding.days_pregnant')) ?>" style="max-width:150px">
    <input type="text" name="notes" maxlength="255" placeholder="<?= e(__('common.notes')) ?>">
    <button class="btn btn-primary" type="submit"><?= e(__('breeding.add_check')) ?></button>
  </form>
  <p class="small muted"><?= e(__('breeding.check_help')) ?></p>
  <?php endif; ?>
</section>
