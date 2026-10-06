<?php
$isTpl = $cfg['mode'] === 'template';
$rec = $cfg['record'] ?? null;
?>
<script type="application/json" id="studio-data"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="studio-ed studio-page">
  <div class="app">
    <div class="studio-bar">
      <div class="sb-title">
        <a class="crumb" href="<?= e(url($isTpl ? '/portal/studio/templates' : '/portal/studio')) ?>">← <?= e(__($isTpl ? 'studio.templates' : 'nav.studio')) ?></a>
        <h1><?= e($title) ?></h1>
      </div>
      <?php if (!$isTpl && $rec && $rec['source']): ?>
        <div class="sb-record">
          <label for="recPick"><?= e(__('studio.fill_from')) ?></label>
          <div class="picker-row"><select id="recPick" data-picker="<?= e($rec['source']) ?>">
            <option value=""><?= e(__('common.search_choose')) ?></option>
            <?php if ($rec['id']): ?><option value="<?= (int) $rec['id'] ?>" selected><?= e($rec['label'] ?? '#' . $rec['id']) ?></option><?php endif; ?>
          </select></div>
        </div>
      <?php endif; ?>
      <div class="sb-actions">
        <label class="sb-lh"><span><?= e(__('studio.letterhead')) ?></span>
          <select id="lhSel"><option value="2"><?= e(__('studio.lh_v2')) ?></option><option value="1"><?= e(__('studio.lh_v1')) ?></option></select></label>
        <?php if ($isTpl): ?>
          <button type="button" class="btn btn-primary" id="saveBtn"><?= e(__('studio.save_template')) ?></button>
        <?php elseif ($cfg['numbered']): ?>
          <button type="button" class="btn btn-primary" id="saveBtn" data-confirm="<?= e(__('studio.confirm_issue')) ?>"><?= e(__('studio.issue')) ?></button>
        <?php else: ?>
          <button type="button" class="btn" id="pdfBtn"><?= icon('download') ?> PDF</button>
          <button type="button" class="btn btn-primary" id="printBtn"><?= icon('print') ?> <?= e(__('common.print')) ?></button>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$cfg['canEdit']): ?><div class="alert alert-info studio-note"><?= e(__('studio.readonly_note')) ?></div><?php endif; ?>
    <?php if ($isTpl): ?><div class="alert alert-info studio-note"><?= e(__('studio.template_note')) ?></div><?php endif; ?>
    <div class="main">
      <div class="editor" id="editor" aria-label="<?= e(__('studio.form')) ?>"></div>
      <div class="canvas" id="canvas" aria-label="<?= e(__('studio.preview')) ?>"><div class="pages" id="pages"></div></div>
    </div>
  </div>
</div>
