<?php use App\Core\View; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_horses')) ?></div><h1><?= e(__('site.our_horses')) ?></h1><p><?= e(__('site.horses_intro')) ?></p></div></section>
<section class="section"><div class="container">
  <form class="chip-filters" method="get" style="margin-bottom:26px">
    <a class="chip<?= empty($filters['category']) ? ' on' : '' ?>" href="<?= e(site_url('horses')) ?>"><?= e(__('site.all')) ?></a>
    <?php foreach (['stallion' => 'site.f_stallions', 'mare' => 'site.f_mares', 'colt' => 'site.f_colts', 'filly' => 'site.f_fillies', 'foal' => 'site.f_foals', 'gelding' => 'site.f_geldings'] as $k => $l): ?>
      <a class="chip<?= ($filters['category'] ?? '') === $k ? ' on' : '' ?>" href="<?= e(site_url('horses') . '?' . http_build_query(array_merge($filters, ['category' => $k]))) ?>"><?= e(__($l)) ?></a>
    <?php endforeach; ?>
    <select name="sex" class="chip" aria-label="<?= e(__('horse.sex')) ?>"><option value=""><?= e(__('horse.sex')) ?></option><?php foreach (['male', 'female', 'gelding'] as $s): ?><option value="<?= $s ?>"<?= selected($filters['sex'] ?? '', $s) ?>><?= e(__('horse.sex_' . $s)) ?></option><?php endforeach; ?></select>
    <select name="age" class="chip" aria-label="<?= e(__('site.age')) ?>"><option value=""><?= e(__('site.age')) ?></option><?php foreach (['u1' => 'site.age_u1', '1-3' => 'site.age_1_3', '4-10' => 'site.age_4_10', '10+' => 'site.age_10'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($filters['age'] ?? '', $k) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select>
    <?php if ($bloodlines): ?><select name="bloodline" class="chip" aria-label="<?= e(__('horse.bloodline')) ?>"><option value=""><?= e(__('horse.bloodline')) ?></option><?php foreach ($bloodlines as $b): ?><option<?= selected($filters['bloodline'] ?? '', $b) ?>><?= e($b) ?></option><?php endforeach; ?></select><?php endif; ?>
    <?php if (!empty($filters['category'])): ?><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><?php endif; ?>
    <button class="chip on" type="submit"><?= e(__('site.apply')) ?></button>
  </form>
  <?php if ($horses): ?>
    <div class="horse-grid"><?php foreach ($horses as $h): ?><?= View::partial('site/partials/horse_card', ['h' => $h]) ?><?php endforeach; ?></div>
  <?php else: ?><div class="empty-note"><?= e(__('site.no_horses')) ?></div><?php endif; ?>
</div></section>
