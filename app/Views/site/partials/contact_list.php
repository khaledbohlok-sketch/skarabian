<?php $wa = preg_replace('/\D/', '', (string) setting('company.whatsapp', '')); ?>
<ul class="contact-list">
  <li><?= icon('pin') ?><span><?= e(setting('company.address_' . lang(), setting('company.address_en'))) ?> · P.O. Box <?= e(setting('company.po_box')) ?></span></li>
  <li><a href="tel:<?= e(setting('company.phone_intl')) ?>"><?= icon('phone') ?><span dir="ltr"><?= e(setting('company.mobile')) ?></span></a></li>
  <?php if ($wa): ?><li><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span>WhatsApp</span></a></li><?php endif; ?>
  <li><a href="mailto:<?= e(setting('company.email')) ?>"><?= icon('mail') ?><span><?= e(setting('company.email')) ?></span></a></li>
</ul>
