<?php
$horseId = $horseId ?? null; $type = $type ?? 'general';
?>
<form class="form" method="post" action="<?= e(site_url('inquiry')) ?>" id="inquiry">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e(current_path()) ?>">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <?php if ($horseId): ?><input type="hidden" name="horse_id" value="<?= (int) $horseId ?>"><?php endif; ?>
  <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  <?php if (!empty($heading)): ?><h3><?= e($heading) ?></h3><?php endif; ?>
  <div class="row2">
    <label><?= e(__('site.f_name')) ?><input type="text" name="name" required maxlength="120" value="<?= e(old('name')) ?>" autocomplete="name"></label>
    <label><?= e(__('site.f_country')) ?><input type="text" name="country" maxlength="80" value="<?= e(old('country')) ?>" autocomplete="country-name"></label>
  </div>
  <div class="row2">
    <label><?= e(__('site.f_email')) ?><input type="email" name="email" maxlength="150" value="<?= e(old('email')) ?>" autocomplete="email" dir="ltr"></label>
    <label><?= e(__('site.f_phone')) ?><input type="tel" name="phone" maxlength="40" value="<?= e(old('phone')) ?>" autocomplete="tel" dir="ltr"></label>
  </div>
  <?php if ($type === 'general'): ?>
  <label><?= e(__('site.f_topic')) ?>
    <select name="type"><option value="general"><?= e(__('site.topic_general')) ?></option><option value="visit"><?= e(__('site.topic_visit')) ?></option><option value="media"><?= e(__('site.topic_media')) ?></option></select>
  </label>
  <?php endif; ?>
  <label><?= e(__('site.f_message')) ?><textarea name="message" rows="5" required maxlength="4000"><?= e(old('message', $prefill ?? '')) ?></textarea></label>
  <button class="btn btn-gold" type="submit"><?= e(__('site.send_inquiry')) ?></button>
  <p style="margin:0;font-size:12px;color:var(--muted)"><?= e(__('site.privacy_note')) ?></p>
</form>
