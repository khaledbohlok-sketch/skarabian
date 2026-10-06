<?php
use App\Core\DB;
$rows = DB::all('SELECT * FROM activity_log WHERE record_type = ? AND record_id = ? ORDER BY id DESC LIMIT 200', [$recordType, $recordId]);
?>
<section class="card">
  <table class="table compact">
    <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('activity.user')) ?></th><th><?= e(__('activity.action')) ?></th><th><?= e(__('activity.changes')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $old = $r['old_values'] ? json_decode($r['old_values'], true) : []; $new = $r['new_values'] ? json_decode($r['new_values'], true) : []; ?>
      <tr><td><?= e(fmt_date($r['created_at'], true)) ?></td><td><?= e($r['user_name']) ?></td><td><?= e(__('activity.a_' . $r['action'])) ?></td>
      <td class="small"><?php if ($r['action'] === 'update'): foreach ($new as $k => $v): ?><div><code><?= e($k) ?></code>: <del><?= e(is_scalar($old[$k] ?? null) ? $old[$k] : json_encode($old[$k] ?? null)) ?></del> → <ins><?= e(is_scalar($v) ? $v : json_encode($v)) ?></ins></div><?php endforeach; else: ?><?= e($r['summary']) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
