<?php
use App\Core\Session;

$horseId = $horseId ?? null; $type = $type ?? 'general';
// the message after sending (thanks, or what to fix) is shown here: the visitor comes back at the form (#inquiry), not
// at the top of the page, so the layout has none left to show
$notes = Session::takeFlash();
?>
<form class="form" method="post" action="<?= e(site_url('inquiry')) ?>" id="inquiry">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e(current_path()) ?>">
  <?php if ($type !== 'general'): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
  <?php if ($horseId): ?><input type="hidden" name="horse_id" value="<?= (int) $horseId ?>"><?php endif; ?>
  <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  <?php if (!empty($heading)): ?><h2 class="form-title"><?= e($heading) ?></h2><?php endif; ?>
  <?php foreach ($notes as $f): ?><div class="notice notice-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
  <?php // Floating labels: the field first, then its label (CSS shows the label inside the empty field and lifts it on
        // focus or once filled — the label stays visible, never only a placeholder). placeholder=" " lets CSS tell an
        // empty field (:placeholder-shown) from a filled one. ?>
  <div class="row2">
    <label class="fl"><input type="text" name="name" required maxlength="120" value="<?= e(old('name')) ?>" autocomplete="name" placeholder=" "><span><?= e(__('site.f_name')) ?></span></label>
    <label class="fl"><input type="text" name="country" maxlength="80" value="<?= e(old('country')) ?>" autocomplete="country-name" placeholder=" "><span><?= e(__('site.f_country')) ?></span></label>
  </div>
  <div class="row2">
    <label class="fl"><input type="email" name="email" maxlength="150" value="<?= e(old('email')) ?>" autocomplete="email" dir="ltr" placeholder=" "><span><?= e(__('site.f_email')) ?></span></label>
    <label class="fl"><input type="tel" name="phone" maxlength="40" value="<?= e(old('phone')) ?>" autocomplete="tel" dir="ltr" placeholder=" "><span><?= e(__('site.f_phone')) ?></span></label>
  </div>
  <?php if ($type === 'general'): ?>
  <label><?= e(__('site.f_topic')) ?>
    <select name="type"><option value="general"><?= e(__('site.topic_general')) ?></option><option value="visit"><?= e(__('site.topic_visit')) ?></option><option value="media"><?= e(__('site.topic_media')) ?></option></select>
  </label>
  <?php endif; ?>
  <label class="fl fl-area"><textarea name="message" rows="5" required maxlength="4000" placeholder=" "><?= e(old('message', $prefill ?? '')) ?></textarea><span><?= e(__('site.f_message')) ?></span></label>
  <button class="btn btn-gold" type="submit"><?= e(__('site.send_inquiry')) ?></button>
  <p class="form-note"><?= e(__('site.privacy_note')) ?></p>
</form>
