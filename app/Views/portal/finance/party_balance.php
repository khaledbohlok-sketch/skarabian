<?php $b = \App\Services\FinanceService::partyBalance((int) $p['id']); ?>
<div class="stat-grid">
  <div class="stat"><div class="k"><?= e(__('parties.owed_to_us')) ?></div><div class="v"><?= money($b['owed_to_us'] ?? 0) ?></div><div class="s"><?= e(__('parties.owed_help')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('parties.we_owe')) ?></div><div class="v"><?= money($b['we_owe'] ?? 0) ?></div><div class="s"><?= e(__('parties.owe_help')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('finance.pending')) ?></div><div class="v"><?= money($b['pending'] ?? 0) ?></div><div class="s"><?= e(__('parties.pending_help')) ?></div></div>
  <div class="stat"><div class="k"><?= e(__('parties.total_business')) ?></div><div class="v"><?= money(($b['income_total'] ?? 0) + ($b['expense_total'] ?? 0)) ?></div><div class="s"><?= e(__('bills.type_income')) ?> <?= money($b['income_total'] ?? 0, 'QAR', false) ?> · <?= e(__('bills.type_expense')) ?> <?= money($b['expense_total'] ?? 0, 'QAR', false) ?></div></div>
</div>
