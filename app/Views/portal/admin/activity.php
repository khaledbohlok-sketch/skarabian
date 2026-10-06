<?php use App\Core\Auth; ?>
<div class="page-head"><div><h1><?= e(__('nav.activity')) ?></h1><p class="muted"><?= e(__('activity.intro')) ?> · <?= e(__('common.n_records', ['n' => number_format($total)])) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/portal/activity/verify')) ?>"><?= e(__('activity.verify')) ?></a>
  <?php if (Auth::can('activity', 'export')): ?><a class="btn" href="<?= e(query_url(['export' => 'xlsx', 'page' => null])) ?>"><?= icon('download') ?> Excel</a><?php endif; ?></div></div>
<form method="get" class="filters card">
  <label class="f-search"><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('activity.search_ph')) ?>"></label>
  <label class="f-item"><span><?= e(__('activity.user')) ?></span><select name="user_id"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($_GET['user_id'] ?? '', $id) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('activity.action')) ?></span><select name="action"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= selected($_GET['action'] ?? '', $a) ?>><?= e(\App\Core\Lang::has('activity.a_' . $a) ? __('activity.a_' . $a) : $a) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('activity.module')) ?></span><select name="module"><option value=""><?= e(__('common.all')) ?></option><?php foreach ($modules as $m): ?><option value="<?= e($m) ?>"<?= selected($_GET['module'] ?? '', $m) ?>><?= e(\App\Core\Lang::has('roles.m_' . $m) ? __('roles.m_' . $m) : $m) ?></option><?php endforeach; ?></select></label>
  <label class="f-item"><span><?= e(__('common.from')) ?></span><input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>"></label>
  <label class="f-item"><span><?= e(__('common.to')) ?></span><input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>"></label>
  <div class="f-buttons"><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
</form>
<div class="table-wrap"><table class="table compact">
  <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('activity.user')) ?></th><th><?= e(__('activity.action')) ?></th><th><?= e(__('activity.summary')) ?></th><th>IP</th></tr></thead>
  <tbody><?php foreach ($rows as $a): ?><tr class="<?= in_array($a['action'], ['access_denied', 'login_failed', 'account_locked'], true) ? 'row-alert' : '' ?>">
    <td><a href="<?= e(url('/portal/activity/' . $a['id'])) ?>"><?= e(fmt_date($a['created_at'], true)) ?></a></td><td><?= e($a['user_name'] ?? '—') ?></td>
    <td><?= e(\App\Core\Lang::has('activity.a_' . $a['action']) ? __('activity.a_' . $a['action']) : $a['action']) ?></td><td><?= e(mb_strimwidth((string) $a['summary'], 0, 90, '…')) ?></td><td dir="ltr"><?= e($a['ip']) ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => max(1, (int) ceil($total / 50))]) ?>
