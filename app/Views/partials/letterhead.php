<?php
/** Official SK Arabians letterhead. $version: 1 = logo top-left with "Sk.Arabian" under it; 2 = logo with Arabic name above "Sk.Arabian" beside it. */
$version = (int) ($version ?? 1);
?>
<header class="lh lh-v<?= $version ?>">
  <?php if ($version === 2): ?>
    <div class="lh-brand">
      <img class="lh-logo" src="<?= e(asset('img/sk-mark.png')) ?>" alt="SK Arabians">
      <div class="lh-names"><span class="lh-ar">اس كي ارابيان للتجارة</span><span class="lh-en">Sk.Arabian</span></div>
    </div>
  <?php else: ?>
    <div class="lh-brand lh-stack">
      <img class="lh-logo" src="<?= e(asset('img/sk-mark.png')) ?>" alt="SK Arabians">
      <span class="lh-en">Sk.Arabian</span>
    </div>
  <?php endif; ?>
</header>
<div class="lh-watermark" aria-hidden="true"><img src="<?= e(asset('img/sk-mark.png')) ?>" alt=""></div>
