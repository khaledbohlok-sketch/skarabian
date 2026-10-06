<?php use App\Core\Auth; ?>
<script type="application/json" id="studio-data"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="studio-ed studio-page studio-view">
  <div class="app">
    <div class="studio-bar">
      <div class="sb-title">
        <a class="crumb" href="<?= e(url('/portal/studio')) ?>">← <?= e(__('nav.studio')) ?></a>
        <h1><?= e($doc['ref_no']) ?> <?= $doc['is_void'] ? '<span class="badge badge-cancelled">' . e(__('studio.void')) . '</span>' : '' ?></h1>
        <p class="muted small"><?= e($doc['title']) ?> · <?= e(fmt_date($doc['doc_date'])) ?> · <?= e(__('studio.by', ['name' => $doc['author'] ?? '—'])) ?></p>
      </div>
      <div class="sb-actions">
        <?php if ($record): ?><a class="btn" href="<?= e(url($record['url'])) ?>"><?= e($record['label']) ?></a><?php endif; ?>
        <?php if (!$doc['is_void']): ?>
          <button type="button" class="btn" id="pdfBtn"><?= icon('download') ?> PDF</button>
          <button type="button" class="btn" id="shareBtn"><?= e(__('studio.share')) ?></button>
          <button type="button" class="btn btn-primary" id="printBtn"><?= icon('print') ?> <?= e(__('common.print')) ?></button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($doc['is_void']): ?><div class="alert alert-error studio-note"><?= e(__('studio.void_note')) ?></div><?php endif; ?>
    <div class="main">
      <div class="editor studio-side">
        <section class="sec"><h3><?= e(__('studio.document')) ?></h3>
          <dl class="dl">
            <div><dt><?= e(__('studio.ref')) ?></dt><dd><?= e($doc['ref_no']) ?></dd></div>
            <div><dt><?= e(__('studio.type')) ?></dt><dd><?= e(__('studio.type_' . $doc['doc_type'])) ?> (<?= e(strtoupper($doc['lang'])) ?>)</dd></div>
            <div><dt><?= e(__('common.date')) ?></dt><dd><?= e(fmt_date($doc['doc_date'])) ?></dd></div>
            <div><dt><?= e(__('studio.verify_link')) ?></dt><dd><a href="<?= e($cfg['verifyUrl']) ?>" target="_blank" rel="noopener"><?= e(__('studio.open_verify')) ?></a></dd></div>
          </dl>
        </section>
        <?php if (!$doc['is_void'] && (Auth::can('studio', 'print') || Auth::isOwner())): ?>
        <section class="sec"><h3><?= e(__('studio.send_email')) ?></h3>
          <form id="mailForm" method="post" action="<?= e(url('/portal/studio/' . $doc['id'] . '/share')) ?>" class="mail-form">
            <?= csrf_field() ?>
            <div class="f"><label for="mailTo"><?= e(__('common.email')) ?></label><input id="mailTo" type="email" name="email" required dir="ltr" placeholder="name@example.com"></div>
            <button class="btn btn-primary" type="submit"><?= e(__('studio.send_pdf')) ?></button>
          </form>
          <p class="hint"><?= e(__('studio.share_help')) ?></p>
        </section>
        <?php endif; ?>
        <section class="sec"><h3><?= e(__('studio.more')) ?></h3>
          <div class="quick">
            <?php if ($canNew): ?><a class="btn small" href="<?= e(url('/portal/studio/new/' . $doc['doc_type'] . '?from=' . $doc['id'])) ?>"><?= e(__('studio.new_version')) ?></a><?php endif; ?>
            <a class="btn small" href="<?= e(url('/portal/studio/' . $doc['id'] . '/print')) ?>" target="_blank"><?= e(__('studio.print_view')) ?></a>
          </div>
          <?php if ($canVoid): ?>
          <form method="post" action="<?= e(url('/portal/studio/' . $doc['id'] . '/void')) ?>" class="void-form" data-confirm="<?= e(__('studio.confirm_void')) ?>">
            <?= csrf_field() ?>
            <div class="f"><label for="voidReason"><?= e(__('studio.void_reason')) ?></label><input id="voidReason" type="text" name="reason" maxlength="255"></div>
            <button class="btn btn-danger" type="submit"><?= e(__('studio.void_doc')) ?></button>
          </form>
          <?php endif; ?>
        </section>
      </div>
      <div class="canvas" id="canvas"><div class="pages" id="pages"></div></div>
    </div>
  </div>
</div>
