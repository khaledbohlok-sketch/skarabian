<?php if ($embryos): ?>
<div style="overflow-x:auto"><table class="results-table"><thead><tr><th><?= e(__('embryos.code')) ?></th><th><?= e(__('horse.sire')) ?></th><th><?= e(__('embryos.donor')) ?></th><th><?= e(__('embryos.status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($embryos as $em): ?>
  <tr><td><strong><?= e($em['code']) ?></strong><?= $em['name'] ? ' · ' . e($em['name']) : '' ?></td>
  <td><?= $em['sire_public'] ? '<a href="' . e(site_url('horses/' . $em['sire_slug'])) . '">' . e(loc(['name_en' => $em['sire_en'], 'name_ar' => $em['sire_ar']])) . '</a>' : e(loc(['name_en' => $em['sire_en'], 'name_ar' => $em['sire_ar']])) ?></td>
  <td><?= $em['dam_public'] ? '<a href="' . e(site_url('horses/' . $em['dam_slug'])) . '">' . e(loc(['name_en' => $em['dam_en'], 'name_ar' => $em['dam_ar']])) . '</a>' : e(loc(['name_en' => $em['dam_en'], 'name_ar' => $em['dam_ar']])) ?></td>
  <td><?= e(__('embryos.status_' . $em['status'])) ?><?= $em['expected_foaling_date'] ? ' · ' . e(__('embryos.expected_foaling')) . ' ' . e(fmt_date($em['expected_foaling_date'])) : '' ?></td>
  <td><a class="btn btn-line" style="padding:8px 16px" href="<?= e(site_url('for-sale') . '?embryo=' . (int) $em['id'] . '#inquiry') ?>"><?= e(__('site.inquire')) ?></a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<p style="color:var(--muted);font-size:14px"><?= e(__('site.price_on_request')) ?></p>
<?php else: ?><div class="empty-note"><?= e(__('site.no_embryos')) ?></div><?php endif; ?>
