<?php
/**
 * About (version 3): the story beside a photograph that stays in view, the quote, the three pillars, the team.
 */
use App\Core\View;
use App\Services\SiteData;

$story = trim((string) setting('site.story_' . lang(), ''));
$pillars = [];
for ($i = 1; $i <= 3; $i++) {
    if ($t = trim((string) setting('site.pillar' . $i . '_title_' . lang(), ''))) {
        $pillars[] = [$i, $t, trim((string) setting('site.pillar' . $i . '_text_' . lang(), ''))];
    }
}
$storyImg = ($photos ?? [])[0] ?? null;
?>
<?= View::partial('site/partials/page_hero', ['bg' => $storyImg ? SiteData::img((int) $storyImg['main_photo_id']) : null, 'cls' => 'band-photo', 'crumbs' => [[__('site.nav_about'), null]], 'eyebrow' => e(__('site.our_story')), 'title' => __('site.about_title'), 'lead' => __('site.story_quote'), 'ghost' => t_other('site.our_story')]) ?>
<section class="chapter">
  <div class="container">
    <div class="story">
      <?php if ($storyImg): ?><div class="story-media arched round framed wipe reveal"><?php foreach (['bl', 'br'] as $c): ?><svg class="orn-corner <?= $c ?>" aria-hidden="true"><use href="<?= e(asset('img/ornaments.svg')) ?>#corner"></use></svg><?php endforeach; ?><img src="<?= e(SiteData::img((int) $storyImg['main_photo_id'])) ?>" alt="" loading="lazy" decoding="async" width="1200" height="1500"></div><?php endif; ?>
      <div class="story-text" data-ghost="<?= e(t_other('site.our_story')) ?>">
        <div class="reveal"><span class="chapter-index"><b>01</b> <?= e(__('site.our_story')) ?></span></div>
        <h2 class="split reveal"><?= split_title(__('site.story_title'), false, lang() === 'ar' ? 3 : 4) ?></h2>
        <?php if ($story !== ''): ?><div class="prose reveal"><?php foreach (preg_split('/\R{2,}/', $story) as $pi => $p): ?><p<?= $pi === 0 ? ' class="opens"' : '' ?>><?= $pi === 0 ? lede($p) : nl2br(e($p)) ?></p><?php endforeach; ?></div><?php endif; ?>
        <?php if ($pillars): ?>
        <div class="pillars stagger reveal" style="margin-top:0">
          <?php foreach ($pillars as [$n, $t, $txt]): ?><div class="pillar"><span class="num" aria-hidden="true">0<?= $n ?></span><h3><?= e($t) ?></h3><?php if ($txt !== ''): ?><p><?= e($txt) ?></p><?php endif; ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php if ($experts): ?>
<section class="chapter night" aria-labelledby="h-experts">
  <div class="container">
    <div class="chapter-head reveal" data-ghost="<?= e(t_other('site.our_experts')) ?>"><div><span class="chapter-index"><b>02</b> <?= e(__('site.the_team')) ?></span><h2 id="h-experts" class="split"><?= split_title(__('site.our_experts'), false, -1) ?></h2></div></div>
    <div class="experts stagger reveal"><?php foreach ($experts as $x): ?><div class="expert"><?= View::partial('site/partials/expert_photo', ['x' => $x]) ?><h3><?= e(loc($x)) ?></h3><?php if ($role = loc($x, 'public_title') ?: loc($x, 'position')): ?><p><?= e($role) ?></p><?php endif; ?><?php if ($bio = loc($x, 'public_bio')): ?><small><?= e($bio) ?></small><?php endif; ?></div><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
<?= View::partial('site/partials/cta_band') ?>
