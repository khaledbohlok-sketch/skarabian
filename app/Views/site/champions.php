<?php $medalCls = ['gold' => 'medal-gold', 'silver' => 'medal-silver', 'bronze' => 'medal-bronze']; ?>
<section class="page-hero"><div class="container"><div class="crumbs"><a href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a> / <?= e(__('site.nav_champions')) ?></div><h1><?= e(__('site.champions_title')) ?></h1><p><?= e(__('site.champions_intro')) ?></p></div></section>
<section class="section"><div class="container">
  <?php if (!$byYear): ?><div class="empty-note"><?= e(__('site.no_results')) ?></div><?php endif; ?>
  <?php foreach ($byYear as $year => $shows): ?>
    <div class="year-block reveal"><h2><?= e($year) ?></h2>
      <?php foreach ($shows as $key => $rows): $s = $rows[0]; ?>
        <h3 style="font-size:28px;margin-top:22px"><?= e(loc(['name_en' => $s['show_en'], 'name_ar' => $s['show_ar']])) ?> <small style="font-family:var(--font-body);font-size:14px;color:var(--muted)"><?= e(trim(($s['city'] ?? '') . ', ' . ($s['country'] ?? ''), ', ')) ?> · <?= e(fmt_date($s['start_date'])) ?></small></h3>
        <div class="medal-wall" style="margin-top:12px">
          <?php foreach ($rows as $r): ?>
            <div class="medal-card" style="background:var(--mist);border-color:var(--line)"><span class="medal <?= e($medalCls[$r['medal']] ?? 'medal-silver') ?>"><?= $r['medal'] !== 'none' ? '★' : (int) $r['placing'] ?></span>
              <div><strong><?php if ($r['show_on_website']): ?><a href="<?= e(site_url('horses/' . $r['slug'])) ?>"><?= e(loc(['name_en' => $r['horse_en'], 'name_ar' => $r['horse_ar']])) ?></a><?php else: ?><?= e(loc(['name_en' => $r['horse_en'], 'name_ar' => $r['horse_ar']])) ?><?php endif; ?></strong>
              <small style="color:var(--muted);display:block"><?= e(loc($r, 'title') ?: $r['class_name']) ?><?= $r['placing'] ? ' · #' . (int) $r['placing'] : '' ?></small></div></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div></section>
