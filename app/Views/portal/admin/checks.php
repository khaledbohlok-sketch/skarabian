<?php
/** Owner's system check: findings in the live data and data fixes, each previewed before its button is pressed. */
$n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
$typeLabel = ['bill' => 'bills.singular', 'bill_payment' => 'payments.singular', 'invoice' => 'invoices.singular', 'purchase_order' => 'po.singular'];
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p class="muted"><?= e(__('checks.intro')) ?></p></div></div>
<?php if (!$ready): ?><div class="alert alert-warning"><?= e(__('checks.needs_update')) ?> <a href="<?= e(url('/portal/settings#database-update')) ?>"><?= e(__('settings.db_apply')) ?></a></div><?php endif; ?>

<section class="card" id="numbers">
  <h2>1 · <?= e(__('checks.numbers_title')) ?></h2>
  <p class="muted"><?= e(__('checks.numbers_help')) ?></p>
  <?php if ($duplicates): ?><div class="alert alert-error"><?= e(__('numbers.duplicates')) ?> <?= e(implode(', ', array_column($duplicates, 1))) ?></div>
  <?php else: ?><p><span class="pill ok"><?= e(__('numbers.no_duplicates')) ?></span></p><?php endif; ?>
  <?php if ($deleted): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('checks.what_deleted')) ?></th><th><?= e(__('trash.item')) ?></th><th><?= e(__('common.date')) ?></th></tr></thead><tbody>
  <?php foreach ($deleted as $d): ?><tr><td data-label="<?= e(__('checks.what_deleted')) ?>"><b><?= e(__($typeLabel[$d['record_type']] ?? 'common.record')) ?></b></td><td data-label="<?= e(__('trash.item')) ?>" dir="ltr"><?= e($d['label']) ?></td><td class="nowrap" data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($d['deleted_at'], true)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <p><a class="btn btn-sm" href="<?= e(url('/portal/number-register')) ?>"><?= e(__('nav.numbers')) ?></a></p>
</section>

<section class="card" id="due">
  <h2>5 · <?= e(__('checks.due_title')) ?> <span class="muted">(<?= count($noDue) ?>)</span></h2>
  <p class="muted"><?= e(__('checks.due_help')) ?></p>
  <?php if ($noDue): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('bills.number')) ?></th><th><?= e(__('bills.party')) ?></th><th><?= e(__('bills.bill_date')) ?></th><th><?= e(__('checks.due_will_be')) ?></th><th class="num"><?= e(__('bills.remaining')) ?></th></tr></thead><tbody>
  <?php foreach (array_slice($noDue, 0, 60) as $b): ?><tr<?= $b['due'] < date('Y-m-d') && (float) $b['open'] > 0 ? ' class="row-alert"' : '' ?>>
    <td data-label="<?= e(__('bills.number')) ?>"><a href="<?= e(url('/portal/bills/' . $b['id'])) ?>" dir="ltr"><?= e($b['number']) ?></a></td><td data-label="<?= e(__('bills.party')) ?>"><?= e($b['party'] ?? '—') ?></td>
    <td class="nowrap" data-label="<?= e(__('bills.bill_date')) ?>"><?= e(fmt_date($b['bill_date'])) ?></td><td class="nowrap" data-label="<?= e(__('checks.due_will_be')) ?>"><?= e(fmt_date($b['due'])) ?></td>
    <td class="num" data-label="<?= e(__('bills.remaining')) ?>"><?= money($b['open']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if (count($noDue) > 60): ?><p class="muted small"><?= e(__('checks.and_more', ['n' => count($noDue) - 60])) ?></p><?php endif; ?>
  <form method="post" data-confirm="<?= e(__('checks.due_confirm', ['n' => count($noDue)])) ?>"><?= csrf_field() ?><input type="hidden" name="fix" value="fill_due"><button class="btn btn-primary" type="submit"><?= e(__('checks.due_fill', ['n' => count($noDue)])) ?></button></form>
  <?php else: ?><p><span class="pill ok"><?= e(__('checks.nothing_to_do')) ?></span></p><?php endif; ?>
</section>

<section class="card" id="payroll">
  <h2>6 · <?= e(__('checks.payroll_title')) ?></h2>
  <p class="muted"><?= e(__('checks.payroll_help')) ?></p>
  <?php if ($future): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('payroll.period')) ?></th><th><?= e(__('common.status')) ?></th><th class="num"><?= e(__('nav.employees')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($future as $r): ?><tr><td data-label="<?= e(__('payroll.period')) ?>"><a href="<?= e(url('/portal/payroll/' . $r['id'])) ?>"><?= e(fmt_month($r['period'] . '-01')) ?></a></td><td data-label="<?= e(__('common.status')) ?>"><?= status_badge($r['status'], 'payroll.status') ?></td>
    <td class="num" data-label="<?= e(__('nav.employees')) ?>"><?= (int) $r['employees'] ?></td>
    <td><form method="post" class="inline" data-confirm="<?= e(__('checks.payroll_confirm', ['period' => fmt_month($r['period'] . '-01')])) ?>"><?= csrf_field() ?><input type="hidden" name="fix" value="payroll_draft"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-primary" type="submit"><?= e(__('checks.payroll_back')) ?></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><p><span class="pill ok"><?= e(__('checks.nothing_to_do')) ?></span></p><?php endif; ?>
</section>

<section class="card" id="leave">
  <h2>4 · <?= e(__('checks.leave_title')) ?> <span class="pill <?= $leaveMode === 'calculated' ? 'ok' : 'warn' ?>"><?= e(__('checks.mode_' . $leaveMode)) ?></span></h2>
  <p class="muted"><?= e(__('checks.leave_help')) ?></p>
  <?php if ($leave): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('common.name')) ?></th><th class="num"><?= e(__('checks.per_year')) ?></th><th class="num"><?= e(__('checks.typed_now')) ?></th><th><?= e(__('checks.opening')) ?></th><th class="num"><?= e(__('checks.earned')) ?></th><th class="num"><?= e(__('checks.used')) ?></th><th class="num"><?= e(__('checks.new_balance')) ?></th></tr></thead><tbody>
  <?php foreach ($leave as $l): ?><tr><td data-label="<?= e(__('common.name')) ?>"><a href="<?= e(url('/portal/employees/' . $l['id'])) ?>"><?= e($l['name']) ?></a></td><td class="num" data-label="<?= e(__('checks.per_year')) ?>"><?= e($n($l['per_year'])) ?></td>
    <td class="num" data-label="<?= e(__('checks.typed_now')) ?>"><?= e($n($l['typed'])) ?></td><td class="nowrap" data-label="<?= e(__('checks.opening')) ?>"><?= e($n($l['open'])) ?> · <?= e(fmt_date($l['since'])) ?></td>
    <td class="num" data-label="<?= e(__('checks.earned')) ?>"><?= e($n($l['earned'])) ?></td><td class="num" data-label="<?= e(__('checks.used')) ?>"><?= e($n($l['used'])) ?></td><td class="num" data-label="<?= e(__('checks.new_balance')) ?>"><b><?= e($n($l['new'])) ?></b></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($leaveMode === 'typed'): ?>
    <form method="post" data-confirm="<?= e(__('checks.leave_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="fix" value="leave_calculated"><button class="btn btn-primary" type="submit"><?= e(__('checks.leave_switch')) ?></button></form>
  <?php else: ?>
    <form method="post" data-confirm="<?= e(__('checks.leave_back_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="fix" value="leave_typed"><button class="btn" type="submit"><?= e(__('checks.leave_back')) ?></button></form>
  <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card" id="roles">
  <h2>8 · <?= e(__('checks.roles_title')) ?></h2>
  <p class="muted"><?= e(__('checks.roles_help')) ?></p>
  <?php $roles = array_filter($roles, fn ($r) => $r['rename'] || ($r['view_only_perms'] && empty($r['read_only'])) || $r['restrict_country']); ?>
  <?php if ($roles): ?>
  <div class="table-wrap"><table class="table compact"><thead><tr><th><?= e(__('users.role')) ?></th><th class="num"><?= e(__('nav.users')) ?></th><th><?= e(__('checks.finding')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($roles as $r): ?><tr>
    <td data-label="<?= e(__('users.role')) ?>"><a href="<?= e(url('/portal/roles/' . $r['id'])) ?>"><?= e(loc($r, 'name')) ?></a></td><td class="num" data-label="<?= e(__('nav.users')) ?>"><?= (int) $r['users'] ?></td>
    <td data-label="<?= e(__('checks.finding')) ?>"><?php if ($r['rename']): ?><?= e(__('checks.role_misspelled', ['name' => $r['rename']])) ?><br><?php endif; ?>
      <?php if ($r['view_only_perms'] && empty($r['read_only'])): ?><?= e(__('checks.role_only_view')) ?><br><?php endif; ?>
      <?php if ($r['restrict_country']): ?><?= e(__('checks.role_country', ['country' => $r['restrict_country']])) ?><?php endif; ?></td>
    <td class="actions-cell">
      <?php if ($r['rename']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="fix" value="role_rename"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-primary" type="submit"><?= e(__('checks.rename')) ?></button></form><?php endif; ?>
      <?php if ($ready && $r['view_only_perms'] && empty($r['read_only'])): ?><form method="post" class="inline" data-confirm="<?= e(__('checks.view_only_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="fix" value="role_view_only"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm" type="submit"><?= e(__('checks.make_view_only')) ?></button></form><?php endif; ?>
    </td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><p><span class="pill ok"><?= e(__('checks.nothing_to_do')) ?></span></p><?php endif; ?>
</section>

<section class="card" id="security">
  <h2>9 · 10 · 11 · <?= e(__('checks.security_title')) ?></h2>
  <ul class="alert-list">
    <li><span><?= e(__('checks.twofa_line', ['n' => $no2fa, 'blocked' => $blocked2fa])) ?></span><a class="btn btn-sm" href="<?= e(url('/portal/settings/security')) ?>"><?= e(__('nav.security')) ?></a></li>
    <li><span><?= e(__($ownerSecond ? 'checks.owner_second_on' : 'checks.owner_second_off', ['n' => $owners])) ?></span><a class="btn btn-sm" href="<?= e(url('/portal/settings/security')) ?>"><?= e(__('nav.security')) ?></a></li>
    <li><span><?= e(!$offsite['dest'] ? __('settings.offsite_none') : ($offsite['late'] ? __('backups.offsite_late') : __('backups.offsite_ok', ['dest' => (string) $offsite['dest'], 'date' => fmt_date((string) $offsite['last'], true)]))) ?></span><a class="btn btn-sm" href="<?= e(url('/portal/settings/backups')) ?>"><?= e(__('nav.backups')) ?></a></li>
  </ul>
</section>
