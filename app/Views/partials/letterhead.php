<?php
/** Official SK Arabians letterhead. $version: 1 = logo top-left with "Sk.Arabian" under it; 2 = logo with Arabic name above "Sk.Arabian" beside it. */
$version = (int) ($version ?? 2);
?>
<header class="lh lh-v<?= $version ?>">
  <?php if ($version === 1): ?>
    <div class="lh-brand lh-stack"><img class="lh-logo" src="<?= e(asset('img/sk-mark.png')) ?>" alt="SK Arabians" style="height:16mm"><span class="lh-en">Sk.Arabian</span></div>
  <?php else: ?>
    <img class="lh-logo" src="<?= e(asset('img/sk-logo.png')) ?>" alt="Sk.Arabian — اس كي ارابيان للتجارة">
  <?php endif; ?>
</header>
<div class="lh-watermark" aria-hidden="true"><img src="<?= e(asset('img/sk-mark.png')) ?>" alt=""></div>
