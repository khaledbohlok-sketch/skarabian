<div class="page-head"><div><h1><?= e(__('semen_stock.record_movement')) ?></h1><p class="muted"><?= e($label) ?> · <?= e(__('semen_stock.current_straws', ['n' => (int) $row['straws']])) ?></p></div>
  <div class="actions"><a class="btn" href="<?= e(url('/portal/semen-stock/' . $row['id'])) ?>"><?= e(__('common.back')) ?></a></div>
</div>
<form method="post" action="<?= e(url('/portal/semen-stock/' . $row['id'] . '/movement')) ?>" class="card record-form" data-autosave="semen-movement:<?= (int) $row['id'] ?>">
  <?= csrf_field() ?>
  <div class="draft-banner" data-draft-banner hidden>
    <span><?= e(__('common.draft_found')) ?></span>
    <button type="button" class="btn btn-sm" data-draft-restore><?= e(__('common.restore')) ?></button>
    <button type="button" class="btn btn-sm ghost" data-draft-dismiss><?= e(__('common.dismiss')) ?></button>
  </div>
  <div class="grid-fields">
    <div class="field col-4">
      <label for="m_type"><?= e(__('common.type')) ?></label>
      <select id="m_type" name="type" data-section-toggle required>
        <option value="in"><?= e(__('semen_stock.type_in')) ?></option>
        <option value="out"><?= e(__('semen_stock.type_out')) ?></option>
        <option value="transfer"><?= e(__('semen_stock.type_transfer')) ?></option>
        <option value="adjustment"><?= e(__('semen_stock.type_adjustment')) ?></option>
      </select>
    </div>
    <div class="field col-4">
      <label for="m_date"><?= e(__('common.date')) ?></label>
      <input id="m_date" type="date" name="movement_date" value="<?= e(date('Y-m-d')) ?>" required>
    </div>
  </div>

  <div data-section-for="type" data-section-value="in" class="grid-fields">
    <div class="field col-4"><label for="m_in_straws"><?= e(__('semen_stock.straws')) ?></label><input id="m_in_straws" type="number" min="1" name="straws_in"></div>
  </div>

  <div data-section-for="type" data-section-value="out" hidden class="grid-fields">
    <div class="field col-3"><label for="m_out_straws"><?= e(__('semen_stock.straws')) ?></label><input id="m_out_straws" type="number" min="1" name="straws_out"></div>
    <div class="field col-3"><label for="m_out_doses"><?= e(__('semen_stock.doses')) ?></label><input id="m_out_doses" type="number" min="0" name="doses"></div>
    <div class="field col-6"><label for="m_recipient"><?= e(__('semen_stock.recipient')) ?></label><input id="m_recipient" type="text" name="recipient" maxlength="150"></div>
    <div class="field col-6"><label for="m_mare"><?= e(__('semen_stock.mare_optional')) ?></label>
      <select id="m_mare" name="mare_id" data-picker="mares"><option value=""><?= e(__('common.search_choose')) ?></option></select>
    </div>
  </div>

  <div data-section-for="type" data-section-value="transfer" hidden class="grid-fields">
    <div class="field col-4"><label for="m_tr_straws"><?= e(__('semen_stock.straws')) ?></label><input id="m_tr_straws" type="number" min="1" name="straws_transfer"></div>
    <div class="field col-8"><label for="m_tr_canister"><?= e(__('semen_stock.transfer_to')) ?></label>
      <select id="m_tr_canister" name="dest_canister_id" data-picker="canisters"><option value=""><?= e(__('common.search_choose')) ?></option></select>
    </div>
  </div>

  <div data-section-for="type" data-section-value="adjustment" hidden class="grid-fields">
    <?php // marked required but no "required" attribute: the browser would then refuse to send the other types, whose sections hide this box ?>
    <div class="field col-4"><label for="m_adj_total"><?= e(__('semen_stock.counted_total')) ?>&nbsp;<span class="req">*</span></label><input id="m_adj_total" type="number" min="0" name="counted_total"></div>
    <div class="field col-8"><label for="m_adj_reason"><?= e(__('semen_stock.reason')) ?></label><input id="m_adj_reason" type="text" name="reason" maxlength="255"></div>
  </div>

  <div class="grid-fields">
    <div class="field col-12"><label for="m_notes"><?= e(__('common.notes')) ?></label><textarea id="m_notes" name="notes" rows="2"></textarea></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(__('common.save')) ?></button></div>
</form>
