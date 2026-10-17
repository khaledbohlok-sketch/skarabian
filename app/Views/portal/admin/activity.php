<?php
use App\Core\Auth;
use App\Core\Lang;

$fKeys = ['user_id', 'action', 'module', 'from', 'to'];
$active = count(array_filter(array_intersect_key($_GET, array_flip($fKeys)), fn ($v) => $v !== '' && $v !== null));
$filtered = $active || ($_GET['q'] ?? '') !== '';
$secondary = ['<a class="btn" role="menuitem" href="' . e(url('/portal/activity/verify')) . '">' . icon('shield') . ' <span>' . e(__('activity.verify')) . '</span></a>'];
if (Auth::can('activity', 'export')) {
    $secondary[] = '<a class="btn" role="menuitem" href="' . e(query_url(['export' => 'xlsx', 'page' => null])) . '">' . icon('download') . ' <span>Excel</span></a>';
}
$actionLabel = fn (string $a) => Lang::has('activity.a_' . $a) ? __('activity.a_' . $a) : $a;
?>
<?= \App\Core\View::partial('portal/partials/list_head', [
    'title' => __('nav.activity'), 'count' => plural((int) $total, 'count.record'), 'sub' => e(__('activity.intro')),
    'search' => true, 'placeholder' => __('activity.search_ph'), 'filters' => $active, 'secondary' => $secondary,
]) ?>
<form method="get" class="filters card<?= $active ? ' open' : ' is-collapsed' ?>" id="list-filters" data-collapsible action="<?= e(url(current_path())) ?>">
  <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
  <label class="f-item"><span><?= e(__('activity.user')) ?></span><select name="user_id"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($_GET['user_id'] ?? '', $id) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('activity.action')) ?></span><select name="action"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= selected($_GET['action'] ?? '', $a) ?>><?= e($actionLabel($a)) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('activity.module')) ?></span><select name="module"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"<?= selected($_GET['module'] ?? '', $m) ?>><?= e(Lang::has('roles.m_' . $m) ? __('roles.m_' . $m) : $m) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('common.from')) ?></span><input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>"></label>
  <label class="f-item"><span><?= e(__('common.to')) ?></span><input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>"></label>
  <?php if (!empty($_GET['per'])): ?><input type="hidden" name="per" value="<?= (int) $per ?>"><?php endif; // the chosen page size stays ?>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button><?php if ($filtered): ?><a class="btn ghost" href="<?= e(url(current_path())) ?>"><?= e(__('common.reset')) ?></a><?php endif; ?></div>
</form>
<?php if ($rows): ?>
<div class="table-wrap"><table class="table compact">
  <thead><tr><th class="nowrap"><?= e(__('common.date')) ?></th><th><?= e(__('activity.user')) ?></th><th><?= e(__('activity.action')) ?></th><th><?= e(__('activity.summary')) ?></th><th class="nowrap">IP</th></tr></thead>
  <tbody><?php foreach ($rows as $a): ?><tr class="<?= in_array($a['action'], ['access_denied', 'login_failed', 'account_locked'], true) ? 'row-alert' : '' ?>">
    <td class="is-title nowrap" data-label="<?= e(__('common.date')) ?>"><a href="<?= e(url('/portal/activity/' . $a['id'])) ?>"><?= e(fmt_date($a['created_at'], true)) ?></a></td>
    <td data-label="<?= e(__('activity.user')) ?>"><?= e($a['user_name'] ?? '—') ?></td>
    <?php $sum = activity_summary($a); $tagged = str_starts_with($sum, '[self-approved] ');
          $self = $tagged || $a['action'] === 'auto_approve'; // the Owner deciding their own request, or their own bill over the limit ?>
    <td data-label="<?= e(__('activity.action')) ?>"><?= e($actionLabel($a['action'])) ?></td>
    <td class="c-text" data-label="<?= e(__('activity.summary')) ?>"><?= $self ? '<span class="badge badge-pending">' . e(__('activity.self_approved')) . '</span> ' : '' ?><?= e(mb_strimwidth($tagged ? substr($sum, 16) : $sum, 0, 90, '…')) ?></td>
    <td class="nowrap<?= $a['ip'] ? '' : ' is-empty' ?>" data-label="IP" dir="ltr"><?= e($a['ip']) ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => $pages, 'per' => $per, 'total' => (int) $total]) ?>
<?php else: ?>
  <?= \App\Core\View::partial('portal/partials/empty', ['icon' => $filtered ? 'search' : 'history', 'message' => __($filtered ? 'ui.empty_filtered' : 'common.no_records'), 'clear' => $filtered ? current_path() : null]) ?>
<?php endif; ?>
