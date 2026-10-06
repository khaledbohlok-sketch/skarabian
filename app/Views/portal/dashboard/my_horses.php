<?php use App\Core\Auth; ?>
<div class="page-head"><div><h1><?= e(__('nav.my_horses')) ?></h1><p class="muted"><?= e(__('dashboard.my_horses_help')) ?></p></div></div>
<?php if (!$horses): ?><div class="card empty"><?= e(__('dashboard.no_assigned')) ?></div><?php endif; ?>
<div class="my-horses">
<?php foreach ($horses as $h): ?>
  <div class="my-horse">
    <a href="<?= e(url('/portal/horses/' . $h['id'])) ?>"><img src="<?= e($h['main_photo_id'] ? url('/portal/files/' . $h['main_photo_id'] . '/thumb') : asset('img/horse-placeholder.svg')) ?>" alt="" loading="lazy"></a>
    <div class="body">
      <h2 style="margin:0"><a href="<?= e(url('/portal/horses/' . $h['id'])) ?>" style="text-decoration:none"><?= e($h['name_en']) ?></a></h2>
      <p class="muted small" style="margin:2px 0 0"><?= e(__('horse.cat_' . $h['category'])) ?><?= $h['loc_en'] ? ' · ' . e(loc(['value_en' => $h['loc_en'], 'value_ar' => $h['loc_ar']], 'value')) : '' ?></p>
      <div class="big-actions">
        <?php if (Auth::can('horse_diet', 'create')): ?><a class="btn" href="<?= e(url('/portal/horses/' . $h['id'] . '?tab=diet')) ?>"><?= icon('feed') ?><?= e(__('horses.log_feeding')) ?></a><?php endif; ?>
        <?php if (Auth::can('horse_notes', 'create')): ?><a class="btn" href="<?= e(url('/portal/horse-notes/create?horse_id=' . $h['id'])) ?>"><?= icon('edit') ?><?= e(__('horses.add_note')) ?></a>
        <form method="post" action="<?= e(url('/portal/files/upload')) ?>" enctype="multipart/form-data" style="display:contents"><?= csrf_field() ?><input type="hidden" name="owner_type" value="horse"><input type="hidden" name="owner_id" value="<?= (int) $h['id'] ?>"><input type="hidden" name="category" value="photo">
          <label class="btn file-btn"><?= icon('camera') ?><?= e(__('horses.add_photo')) ?><input type="file" name="files[]" accept="image/*" capture="environment" data-auto-submit></label></form><?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
