<?php $wa = site_contact('whatsapp'); ?>
<ul class="contact-list">
  <li><span class="ci" aria-hidden="true"><?= icon('phone') ?></span><a href="tel:<?= e(site_contact('tel')) ?>"><span dir="ltr"><?= e(site_contact('phone')) ?></span></a></li>
  <?php if ($wa): ?><li><span class="ci" aria-hidden="true"><?= icon('whatsapp') ?></span><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('nav.whatsapp')) ?></a></li><?php endif; ?>
  <li><span class="ci" aria-hidden="true"><?= icon('mail') ?></span><a href="mailto:<?= e(site_contact('email')) ?>"><span dir="ltr"><?= e(site_contact('email')) ?></span></a></li>
  <li><span class="ci" aria-hidden="true"><?= icon('pin') ?></span><span><?= e(site_contact('po_box')) ?></span></li>
</ul>
