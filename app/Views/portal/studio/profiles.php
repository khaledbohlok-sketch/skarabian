<?php
/**
 * Horse Profile → "Print several horses": tick the horses (all, the favourites, or a choice), what to print and the
 * language; each horse gets its own profile (reference and QR code, on its Documents tab), then all print together.
 * studio-batch.js adds the editor's empty profile ("base") to the form and runs the tick buttons and the counter.
 */
$cats = ['stallion', 'mare', 'colt', 'filly', 'foal'];
?>
<script type="application/json" id="studio-data"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="page-head"><div><a class="crumb" href="<?= e(url('/portal/studio/new/profile')) ?>"><?= arrow('back') ?> <?= e(__('studio.type_profile')) ?></a>
  <h1><?= e($title) ?></h1><p class="muted"><?= e(__('studio.profiles_intro')) ?></p></div></div>
<?php if (!$horses): ?>
<div class="empty card"><p><?= e(__('studio.profiles_no_horses')) ?></p></div>
<?php else: ?>
<form method="post" class="profiles-batch" data-profiles-batch data-max="<?= (int) $max ?>" data-confirm="<?= e(__('studio.profiles_confirm', ['n' => count($horses)])) ?>" data-confirm-tpl="<?= e(__('studio.profiles_confirm', ['n' => '{n}'])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="base" value="">
  <section class="card">
    <div class="pb-head">
      <h2><?= e(__('studio.profiles_horses')) ?> <span class="muted" data-pb-count data-tpl="<?= e(__('studio.profiles_count', ['n' => '{n}', 'total' => count($horses)])) ?>"></span></h2>
      <div class="pb-quick">
        <button type="button" class="btn btn-sm" data-pb="all"><?= e(__('studio.profiles_tick_all')) ?></button>
        <button type="button" class="btn btn-sm" data-pb="fav">★ <?= e(__('studio.profiles_tick_fav')) ?></button>
        <?php foreach ($cats as $c): if (!array_filter($horses, fn ($h) => $h['category'] === $c)) { continue; } ?>
          <button type="button" class="btn btn-sm" data-pb="cat" data-cat="<?= e($c) ?>">+ <?= e(__('horse.cat_' . $c)) ?></button>
        <?php endforeach; ?>
        <button type="button" class="btn btn-sm" data-pb="none"><?= e(__('studio.profiles_tick_none')) ?></button>
      </div>
    </div>
    <div class="pb-list">
      <?php foreach ($horses as $h): ?>
        <label class="pb-horse">
          <input type="checkbox" name="horses[]" value="<?= (int) $h['id'] ?>" data-cat="<?= e((string) $h['category']) ?>"<?= $h['is_favorite'] ? ' data-fav' : '' ?> checked>
          <?php if ($h['main_photo_id']): ?><img src="<?= e(url('/portal/files/' . (int) $h['main_photo_id'] . '/thumb')) ?>" alt="" loading="lazy" width="36" height="36"><?php else: ?><span class="pb-noph" aria-hidden="true"></span><?php endif; ?>
          <span class="pb-name"><b><?= e($h['name_en']) ?><?= $h['is_favorite'] ? ' <span class="fav-star on static" aria-hidden="true">★</span>' : '' ?></b>
            <small><?= e(__('horse.cat_' . $h['category'])) ?><?= $h['dob'] ? ' · ' . e(horse_age((string) $h['dob'])) : '' ?><?= $h['name_ar'] ? ' · <span dir="rtl">' . e($h['name_ar']) . '</span>' : '' ?></small></span>
        </label>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <h2><?= e(__('studio.profiles_parts')) ?></h2>
    <div class="pb-quick">
      <button type="button" class="btn btn-sm" data-pb="parts-all"><?= e(__('studio.profiles_parts_all')) ?></button>
      <button type="button" class="btn btn-sm" data-pb="parts-basic"><?= e(__('studio.profiles_parts_basic')) ?></button>
    </div>
    <div class="pb-parts">
      <?php foreach ($parts as $k => $label): ?>
        <label class="check-chip"><input type="checkbox" name="parts[]" value="<?= e($k) ?>" checked> <?= e(__($label)) ?></label>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="grid-fields">
      <div class="field col-4"><span class="label"><?= e(__('studio.profiles_lang')) ?></span>
        <div class="check-row"><label class="check-chip"><input type="radio" name="lang" value="en"<?= lang() === 'ar' ? '' : ' checked' ?>> English</label><label class="check-chip"><input type="radio" name="lang" value="ar"<?= lang() === 'ar' ? ' checked' : '' ?>> العربية</label></div></div>
      <?php // the letterhead follows the chosen language now; the template's stored version is still sent unchanged ?>
      <input type="hidden" name="letterhead" value="<?= (int) $cfg['letterhead'] ?>">
    </div>
    <p class="muted small"><?= e(__('studio.profiles_note', ['max' => $max])) ?></p>
    <div class="form-actions"><button class="btn btn-primary" type="submit" data-pb-submit><?= icon('print') ?> <?= e(__('studio.profiles_issue')) ?></button></div>
  </section>
</form>
<?php endif; ?>
