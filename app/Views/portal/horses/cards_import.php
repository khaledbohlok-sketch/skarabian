<?php
/** Owner: import of the horse information cards (7 October 2026) — what it will add, then one button. */
$p = $preview;
$rows = $p ? [
    ['cards.row_sk', $p['horses']],
    ['cards.row_other', $p['others']],
    ['cards.row_links', $p['links']],
    ['cards.row_shows', $p['shows']],
    ['cards.row_results', $p['results']],
    ['cards.row_cards', $p['cards']],
    ['cards.row_notes', $p['notes']],
    ['cards.row_embryos', $p['embryos']],
    ['cards.row_carried', $p['carried']],
] : [];
$work = $p && \App\Services\HorseCardsImport::hasWork($p);
$fieldLabel = fn (string $f) => __(match ($f) {
    'color_id' => 'horse.color', 'breed_id' => 'horse.breed', 'breeder' => 'horse.breeder', 'origin_country' => 'horses.origin_country', 'location_id' => 'horses.location',
    'is_external', 'owner_type', 'owner_party_id' => 'cards.f_sk', 'category', 'category_locked' => 'horses.category', 'notes' => 'common.notes',
    'breeding_stallion' => 'horses.breeding_stallion', 'born_at_sk' => 'cards.f_born_at_sk', 'dob' => 'horses.dob', default => 'horses.' . $f,
});
?>
<div class="page-head">
  <div>
    <a class="crumb" href="<?= e(url('/portal/horses')) ?>"><?= icon('arrow-left', 'i-16 flip-rtl') ?> <?= e(__('nav.horses')) ?></a>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e(__('cards.intro')) ?></p>
  </div>
</div>

<?php if (!$hasFile): ?>
  <div class="alert alert-warning"><?= e(__('cards.no_file')) ?></div>
<?php elseif (!$ready): ?>
  <div class="alert alert-warning"><?= e(__('cards.need_update')) ?> <a href="<?= e(url('/portal/settings#database-update')) ?>"><?= e(__('settings.db_apply')) ?></a></div>
<?php endif; ?>

<?php if ($done): ?>
  <div class="alert alert-info"><?= e(__('cards.imported_on', ['date' => fmt_date($done['at'] ?? '', true), 'name' => $done['by'] ?? '—'])) ?> <a href="<?= e(url('/portal/horses')) ?>"><?= e(__('cards.open_horses')) ?></a></div>
<?php endif; ?>

<?php if ($p): ?>
<section class="card">
  <h2><?= e(__($work ? 'cards.preview_title' : 'cards.nothing_title')) ?></h2>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('cards.what')) ?></th><th class="num"><?= e(__('cards.col_new')) ?></th><th class="num"><?= e(__('cards.col_existing')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as [$label, $n]): ?>
      <tr><td data-label="<?= e(__('cards.what')) ?>"><?= e(__($label)) ?></td><td class="num" data-label="<?= e(__('cards.col_new')) ?>"><b><?= (int) $n['new'] ?></b></td><td class="num" data-label="<?= e(__('cards.col_existing')) ?>"><?= (int) $n['existing'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small"><?= e(__('cards.preview_help')) ?></p>
</section>

<?php if ($p['conflicts']): ?>
<section class="card">
  <h2><?= e(__('cards.conflicts_title')) ?> <span class="muted">(<?= count($p['conflicts']) ?>)</span></h2>
  <p class="muted small"><?= e(__('cards.conflicts_help')) ?></p>
  <ul class="small"><?php foreach ($p['conflicts'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<?php if ($work): ?>
<form method="post" action="<?= e(url('/portal/horses/cards-import')) ?>" class="card" data-confirm="<?= e(__('cards.confirm')) ?>">
  <?= csrf_field() ?>
  <div class="field field-check"><label><input type="checkbox" name="publish" value="1"> <?= e(__('cards.publish')) ?></label><small class="help"><?= e(__('cards.publish_help')) ?></small></div>
  <button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(__('cards.button')) ?></button>
</form>
<?php endif; ?>

<section class="card">
  <h2><?= e(__('cards.horses_title', ['n' => count($p['sk'])])) ?></h2>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.status')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($p['sk'] as $h): ?>
      <tr>
        <td data-label="<?= e(__('common.name')) ?>"><?php if ($h['status'] !== 'new'): ?><a href="<?= e(url('/portal/horses/' . (int) $h['id'])) ?>"><?= e($h['name']) ?></a><?php else: ?><?= e($h['name']) ?><?php endif; ?></td>
        <td data-label="<?= e(__('common.status')) ?>">
          <?php if ($h['status'] === 'new'): ?><span class="pill ok"><?= e(__('cards.status_new')) ?></span>
          <?php elseif ($h['status'] === 'filled'): ?><span class="pill"><?= e(__('cards.status_filled')) ?></span> <small class="muted"><?= e(implode(', ', array_unique(array_map($fieldLabel, $h['filled'])))) ?></small>
          <?php else: ?><span class="pill"><?= e(__('cards.status_same')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
