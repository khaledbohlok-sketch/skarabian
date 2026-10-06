<?php $wa = preg_replace('/\D/', '', (string) $m['phone']); ?>
<div class="page-head"><div><a class="muted small" href="<?= e(url('/portal/inbox')) ?>">‹ <?= e(__('nav.inbox')) ?></a><h1><?= e($m['name']) ?></h1>
  <p class="muted"><?= e(__('inbox.type_' . $m['type'])) ?> · <?= e(fmt_date($m['created_at'], true)) ?> · <span class="pill"><?= e(__('inbox.st_' . $m['status'])) ?></span></p></div>
  <?php if ($canEdit): ?><div class="actions">
    <?php if ($m['status'] !== 'closed'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="closed"><button class="btn" type="submit"><?= e(__('inbox.close')) ?></button></form>
    <?php else: ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="read"><button class="btn" type="submit"><?= e(__('inbox.reopen')) ?></button></form><?php endif; ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="new"><button class="btn" type="submit"><?= e(__('inbox.mark_unread')) ?></button></form>
    <?php if ($canDelete): ?><form method="post" class="inline" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger" type="submit"><?= e(__('common.delete')) ?></button></form><?php endif; ?>
  </div><?php endif; ?>
</div>
<div class="grid-2">
  <div class="stack">
    <section class="card"><h2><?= e(__('inbox.message')) ?></h2>
      <div class="msg-body" dir="auto"><?= e($m['message']) ?></div>
      <?php if ($m['horse']): ?><p><?= e(__('inbox.about_horse')) ?>: <a href="<?= e(url('/portal/horses/' . $m['horse_id'])) ?>"><?= e($m['horse']) ?></a></p><?php endif; ?>
    </section>
    <section class="card" id="thread"><h2><?= e(__('inbox.history')) ?></h2>
      <?php if (!$thread): ?><p class="muted"><?= e(__('inbox.no_history')) ?></p><?php else: ?>
      <ul class="thread"><?php foreach ($thread as $t): ?>
        <li class="<?= $t['kind'] ?>"><div class="muted small"><?= e(__('inbox.kind_' . $t['kind'])) ?> · <?= e($t['author'] ?? '—') ?> · <?= e(fmt_date($t['created_at'], true)) ?>
          <?php if ($t['kind'] === 'reply'): ?> · <span dir="ltr"><?= e($t['sent_to']) ?></span> <?= $t['sent_ok'] ? '' : '<span class="pill bad">' . e(__('inbox.not_sent')) . '</span>' ?><?php endif; ?></div>
          <div class="msg-body" dir="auto" style="background:none;padding:4px 0"><?= e($t['body']) ?></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php if ($canEdit): ?>
    <section class="card"><h2><?= e(__('inbox.reply')) ?></h2>
      <?php if ($m['email']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reply">
        <div class="field"><label for="subject"><?= e(__('inbox.subject')) ?></label><input type="text" id="subject" name="subject" value="<?= e(__('inbox.reply_subject')) ?>" maxlength="150"></div>
        <div class="field"><label for="body"><?= e(__('inbox.reply_to', ['email' => $m['email']])) ?></label><textarea id="body" name="body" rows="8" dir="auto" required><?= e(($m['lang'] === 'ar' ? 'مرحباً ' : 'Dear ') . $m['name'] . ',' . "\n\n" . $signature) ?></textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('mail') ?> <?= e(__('inbox.send_reply')) ?></button></div>
      </form>
      <?php else: ?><p class="muted"><?= e(__('inbox.no_email')) ?></p><?php endif; ?>
      <?php if ($wa): ?><p><a class="btn" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('phone') ?> <?= e(__('inbox.whatsapp')) ?></a></p><?php endif; ?>
    </section>
    <section class="card"><h2><?= e(__('inbox.add_note')) ?></h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <div class="field"><label for="note"><?= e(__('inbox.note')) ?></label><textarea id="note" name="body" rows="3" dir="auto" required></textarea><small class="help"><?= e(__('inbox.note_help')) ?></small></div>
        <div class="form-actions"><button class="btn" type="submit"><?= e(__('common.save')) ?></button></div></form>
    </section>
    <?php endif; ?>
  </div>
  <div class="stack">
    <section class="card"><h2><?= e(__('inbox.sender')) ?></h2>
      <dl class="dl">
        <dt><?= e(__('common.name')) ?></dt><dd><?= e($m['name']) ?></dd>
        <?php if ($m['email']): ?><dt><?= e(__('inbox.email')) ?></dt><dd><a href="mailto:<?= e($m['email']) ?>" dir="ltr"><?= e($m['email']) ?></a></dd><?php endif; ?>
        <?php if ($m['phone']): ?><dt><?= e(__('inbox.phone')) ?></dt><dd><a href="tel:<?= e($m['phone']) ?>" dir="ltr"><?= e($m['phone']) ?></a></dd><?php endif; ?>
        <?php if ($m['country']): ?><dt><?= e(__('inbox.country')) ?></dt><dd><?= e($m['country']) ?></dd><?php endif; ?>
        <dt><?= e(__('inbox.language')) ?></dt><dd><?= $m['lang'] === 'ar' ? 'العربية' : 'English' ?></dd>
        <?php if ($m['party']): ?><dt><?= e(__('inbox.contact')) ?></dt><dd><a href="<?= e(url('/portal/parties/' . $m['party_id'])) ?>"><?= e($m['party']) ?></a></dd><?php endif; ?>
      </dl>
      <?php if ($canEdit && !$m['party_id'] && \App\Core\Auth::can('finance', 'create')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="contact"><button class="btn btn-sm" type="submit"><?= e(__('inbox.save_contact')) ?></button></form><?php endif; ?>
    </section>
    <section class="card"><h2><?= e(__('inbox.assigned')) ?></h2>
      <?php if ($canEdit): ?><form method="post" class="form-inline"><?= csrf_field() ?><input type="hidden" name="action" value="assign">
        <select name="assigned_to" aria-label="<?= e(__('inbox.assigned')) ?>"><option value="">—</option><?php foreach ($staff as $uid => $name): ?><option value="<?= (int) $uid ?>"<?= selected((int) $m['assigned_to'], (int) $uid) ?>><?= e($name) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm" type="submit"><?= e(__('common.save')) ?></button></form>
      <?php else: ?><p><?= e($staff[$m['assigned_to']] ?? '—') ?></p><?php endif; ?>
    </section>
    <?php if ($others): ?><section class="card"><h2><?= e(__('inbox.earlier')) ?></h2><ul class="alert-list">
      <?php foreach ($others as $o): ?><li><a href="<?= e(url('/portal/inbox/' . $o['id'])) ?>"><?= e(fmt_date($o['created_at'])) ?> · <?= e(__('inbox.type_' . $o['type'])) ?></a><span class="pill"><?= e(__('inbox.st_' . $o['status'])) ?></span></li><?php endforeach; ?>
    </ul></section><?php endif; ?>
  </div>
</div>
