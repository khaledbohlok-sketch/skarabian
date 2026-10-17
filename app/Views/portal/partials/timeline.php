<section class="card">
  <?php if (!$items): ?><p class="muted"><?= e(__('common.no_activity')) ?></p><?php endif; ?>
  <ol class="timeline">
    <?php foreach ($items as $it): ?>
      <li class="tl-<?= e($it['kind']) ?>">
        <time datetime="<?= e($it['date']) ?>"><?= e(fmt_date($it['date'])) ?></time>
        <div class="tl-body">
          <span class="tl-kind"><?= e(__('timeline.' . $it['kind'])) ?></span>
          <?php if (!empty($it['url'])): ?><a href="<?= e(url($it['url'])) ?>"><?= e($it['title']) ?></a><?php else: ?><?= e($it['title']) ?><?php endif; ?>
          <?php if (!empty($it['detail'])): ?><small><?= $it['detail_html'] ?? e($it['detail']) ?></small><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
